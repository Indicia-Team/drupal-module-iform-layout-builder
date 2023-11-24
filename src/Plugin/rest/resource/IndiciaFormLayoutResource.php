<?php

namespace Drupal\iform_layout_builder\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource for single Iform_layout_builder forms.
 *
 * @RestResource(
 *   id = "indicia_form_layout",
 *   label = @Translation("Indicia form layout"),
 *   uri_paths = {
 *     "canonical" = "/iform_layout_builder/form_layout/{id}"
 *   }
 * )
 */
class IndiciaFormLayoutResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @param int $id
   *   The ID of the node to fetch.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the form object.
   */
  public function get($id) {
    $node = node::load($id);
    if (!$node || $node->getType() !== 'iform_layout_builder_form') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found');
    }
    $data = [
      'title' => $node->getTitle(),
      'survey_id' => (integer) $node->field_survey_id->value,
      'type' => $this->getFormTypeLabel($node),
      'subtype' => NULL,
      'data' => [
        'sample:survey_id' => (integer) $node->field_survey_id->value,
        'sample:input_form' => trim(\Drupal::service('path_alias.manager')->getAliasByPath("/node/$id"), '/'),
      ],
      'created_by_uid' => (integer) $node->getOwnerID(),
      'created_by_id' => (integer) $node->getOwner()->field_indicia_user_id->value,
      'created_on' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'c'),
    ];
    if ($node->getEntityType()->isRevisionable()) {
      $data['revision_id'] = (integer) $node->getRevisionId();
      $data['updated_by_uid'] = (integer) $node->getRevisionUser()->id();
      $data['updated_by_id'] = (integer) $node->getRevisionUser()->field_indicia_user_id->value;
      $data['updated_on'] = \Drupal::service('date.formatter')->format($node->getChangedTime(), 'custom', 'c');
    }
    // Optional properties.
    if ($node->body->value) {
      $data['description'] = strip_tags($node->body->value);
    }
    $formSections = [];
    $sections = $node->get('layout_builder__layout')->getSections();
    $gridCustomAttributes = [];
    foreach ($sections as $section) {
      $components = $section->getComponents();
      $regions = [];
      foreach ($components as $component) {
        $asArray = $component->toArray();
        $blockConfig = $asArray['configuration'];
        if ($blockConfig['id'] === 'data_entry_submit_buttons_block') {
          // No need to report the submit buttons block.
          continue;
        }
        $weight = (int) $asArray['weight'];
        if (!isset($regions[$asArray['region']])) {
          $regions[$asArray['region']] = [];
        }
        $fieldConfig = [
          'type' => $this->getTypeFromBlockId($blockConfig['id']),
          'field_name' => $this->getControlFieldName($blockConfig),
        ];
        if (!empty($blockConfig['option_data_type'])) {
          $blockConfig['data_type'] = $this->getVerboseDataType($blockConfig['option_data_type']);
        }
        $ctrlType = $this->getControlType($blockConfig);
        if ($ctrlType) {
          $fieldConfig['control_type'] = $ctrlType;
        }
        $this->cleanupUnwantedBlockConfigProperties($blockConfig);
        foreach ($blockConfig as $key => $value) {
          // Tidy - remove option_* or option_existing_* prefixes from block config.
          $camel = preg_replace('/^option_(existing_)?/', '', $key);
          $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
          // Convert Drupal 0/1 to true/false for booleans.
          if (in_array($snake, ['required', 'lockable', 'allow_vague_dates']) && in_array($value, [0, 1, '0', '1'])) {
            $fieldConfig[$snake] = (integer) $value === 1;
          }
          else {
            $fieldConfig[$snake] = $value;
          }
        }
        if ($fieldConfig['type'] !== 'species_list' && !isset($fieldConfig['required'])) {
          $fieldConfig['required'] = $this->getIsRequired($fieldConfig);
        }
        if (isset($fieldConfig['data_type']) && $fieldConfig['data_type'] === 'lookup') {
          $this->addTermsToFieldConfig($fieldConfig);
        }
        // Remove empties.
        $fieldConfig = array_filter($fieldConfig, fn($value) => !is_null($value) && $value !== '');
        // Add control to grid, or top level of form as appropriate.
        if ($fieldConfig['type'] === 'occurrence_custom_attribute' && $data['type'] !== 'Single species form') {
          $gridCustomAttributes[$weight] = $fieldConfig;
        }
        else {
          $regions[$asArray['region']][$weight] = $fieldConfig;
        }
        // Additional info at top level for species list options.
        if ($fieldConfig['spatial_ref_per_row'] ?? 0 === 1) {
          $data['subtype'] = 'optional_spatial_ref_per_occurrence';
        }
      }
      $formSections[] = [
        'layout_type' => $section->getLayoutId(),
        'label' => $section->getLayoutSettings()['label'],
        'components' => $regions,
      ];
    }
    foreach ($formSections as &$section) {
      foreach ($section['components'] as &$regionControlList) {
        foreach ($regionControlList as &$fieldConfig) {
          // If a species grid, attach the controls list which has to be done
          // at the end.
          if (in_array($fieldConfig['type'], ['species_list', 'species_multiplace'])) {
            $this->formatSpeciesListControl($fieldConfig, $gridCustomAttributes);
          }
        }
      }
    }
    // Cleanup if unnecessary.
    if (empty($data['subtype'])) {
      unset($data['subtype']);
    }

    // Flatten response to ordered list of controls.
    $allControls = [];
    foreach ($formSections as &$section) {
      foreach ($section['components'] as &$regionControlList) {
        ksort($regionControlList);
        if (empty($_GET['layout'])) {
          $allControls = array_merge($allControls, $regionControlList);
        }
      }
    }
    if (empty($_GET['layout'])) {
      $data['controls'] = $allControls;
    }
    else {
      // Layout response keeps structure.
      $data['form_sections'] = $formSections;
    }
    $response = new ResourceResponse($data);
    // Update if node updated.
    $response->addCacheableDependency($node);
    // Update if query parameters changed.
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args:taxon_attributes']);
    $response->getCacheableMetadata()->addCacheContexts(['url.query_args:layout']);
    return $response;
  }

  /**
   * For lookup controls, add the list of available terms.
   *
   * @param array $fieldConfig
   *   Field configuration, which will be updated with the terms if this is a
   *   lookup control.
   */
  private function addTermsToFieldConfig(array &$fieldConfig) {
    if (isset($fieldConfig['data_type']) && $fieldConfig['data_type'] === 'lookup' && !empty($fieldConfig['termlist_id'])) {
      \iform_load_helpers(['helper_base']);
      $conn = \iform_get_connection_details();
      $readAuth = \helper_base::get_read_auth($conn['website_id'], $conn['password']);
      $terms = \helper_base::get_population_data(array(
        'table' => 'termlists_term',
        'extraParams' => $readAuth + [
          'view' => 'cache',
          'termlist_id' => $fieldConfig['termlist_id'],
          'orderby' => 'sort_order, term',
          'preferred' => 't',
          'columns' => 'id,term,parent_id,preferred_image_path',
        ],
      ));
      foreach ($terms as &$term) {
        if (empty($term['parent_id'])) {
          unset($term['parent_id']);
        }
        if (empty($term['preferred_image_path'])) {
          unset($term['preferred_image_path']);
        }
      }
      $fieldConfig['terms'] = $terms;
    }
  }

  /**
   * Format the configuration returned for a species list control.
   *
   * @param array $fieldConfig
   *   Configuration for the control which will be modified.
   * @param array $gridCustomAttributes
   *   Occurrence custom attributes loaded from elsewhere on the layout that
   *   need to be inserted into the grid.
   */
  private function formatSpeciesListControl(array &$fieldConfig, $gridCustomAttributes) {
    if ($fieldConfig['species_list_mode'] === 'scratchpadList') {
      // @todo Preload the scratchpad list
      $fieldConfig['preload_taxa'] = $this->getScratchpadTaxonNames($fieldConfig['preloaded_scratchpad_list_id'], TRUE);
      // Unset irrelevant options for this mode.
      unset($fieldConfig['species_to_add_list_type']);
      unset($fieldConfig['additional_species_scratchpad_list_id']);
      // Tidy as bool.
      $fieldConfig['allow_additional_species'] = !empty($fieldConfig['allow_additional_species']);
    }
    else {
      // Start with an empty list.
      if ($fieldConfig['species_to_add_list_type'] === 'scratchpadList') {
        // @todo Attach the loaded scratchpad list to the species input control in gridCustomAttributes.
        $speciesExtraInfo = [
          'limit_taxa_to' => $this->getScratchpadTaxonNames($fieldConfig['additional_species_scratchpad_list_id'], FALSE)
        ];
      }
      // Unset irrelevant options for this mode.
      unset($fieldConfig['row_inclusion_mode']);
      unset($fieldConfig['allow_additional_species']);
      unset($fieldConfig['preloaded_scratchpad_list_id']);
    }
    $fieldConfig['controls'] = array_merge(
      $this->getGridControls($fieldConfig, FALSE, $speciesExtraInfo ?? []),
      array_values($gridCustomAttributes),
      $this->getGridControls($fieldConfig, TRUE),
    );
    unset($fieldConfig['absence_column']);
    unset($fieldConfig['comments_column']);
    unset($fieldConfig['media_column']);
    unset($fieldConfig['sensitivity_column']);
    unset($fieldConfig['spatial_ref_per_row']);
    unset($fieldConfig['species_list_mode']);
  }

  /**
   * Retrieve the full list of taxon names for a scratchpad list.
   *
   * @param bool $preferred
   *   True to limit to only return preferred names.
   *
   * @return array
   *   List of taxon name data loaded from the warehouse.
   */
  private function getScratchpadTaxonNames($scratchpadId, $preferred) {
    \iform_load_helpers(['report_helper']);
    $connection = iform_get_connection_details();
    $readAuth = \report_helper::get_read_auth($connection['website_id'], $connection['password']);
    return \report_helper::get_report_data([
      'dataSource' => 'library/taxa/taxa_for_scratchpad',
      'extraParams' => [
        'scratchpad_list_id' => $scratchpadId,
        // @todo Allow configuration of language codes.
        'language_codes' => 'lat,eng',
        'preferred' => $preferred ? 't' : 'f',
        'taxattrs' => preg_match('/^[0-9]+(,[0-9]+)*$/', $_GET['taxon_attributes'] ?? '') ? $_GET['taxon_attributes'] : '',
        // @todo Extra filter options, e.g. include children.
      ],
      'readAuth' => $readAuth,
    ]);
  }

  /**
   * Clean up unwanted block configuration.
   *
   * Removes properties from a blocks' config that we don't need to pass to the
   * API.
   *
   * @param array $blockConfig
   *   Loaded block config which will be modified by removal of properties.
   */
  private function cleanupUnwantedBlockConfigProperties(array &$blockConfig) {
    unset($blockConfig['id']);
    unset($blockConfig['context_mapping']);
    unset($blockConfig['provider']);
    unset($blockConfig['label']);
    unset($blockConfig['label_display']);
    unset($blockConfig['mode']);
    unset($blockConfig['option_create_or_existing']);
    unset($blockConfig['option_data_type']);
    unset($blockConfig['option_existing_attributes_website_id']);
    unset($blockConfig['option_lookup_options_terms']);
    unset($blockConfig['option_lookup_options_control']);
    unset($blockConfig['option_text_options_control']);
    if (!isset($blockConfig['data_type']) || $blockConfig['data_type'] !== 'lookup') {
      unset($blockConfig['option_existing_termlist_id']);
    }
    if (!isset($blockConfig['data_type']) || !in_array($blockConfig['data_type'], ['integer', 'float'])) {
      unset($blockConfig['option_number_options_min']);
      unset($blockConfig['option_number_options_max']);
    }
  }

  private function getIsRequired(array $blockConfig) {
    return in_array($blockConfig['type'], ['date', 'spatial_ref']);
  }

  /**
   * Obtains a control type from the Drupal block ID.
   *
   * Simply removes the prefix and suffix from the block name to leave the
   * relevant bit.
   *
   * @param string $id
   *   Drupal block ID.
   *
   * @return string
   *   Control class name.
   */
  private function getTypeFromBlockId($id) {
    return preg_replace(['/^data_entry_/', '/_block$/'], '', $id);
  }

  /**
   * Identifies the type of control to use for a block.
   *
   * @param array $blockConfig
   *   Saved block configuration.
   *
   * @return string|NULL
   *   Control type name, or NULL if not applicable.
   */
  private function getControlType(array $blockConfig) {
    if (!empty($blockConfig['data_type'])) {
      switch ($blockConfig['data_type']) {
        case 'text':
          return empty($blockConfig['option_text_options_control']) ? 'text' : str_replace('text_input', 'text', $blockConfig['option_text_options_control']);

        case 'boolean':
          return 'checkbox';

        case 'lookup':
          return empty($blockConfig['option_lookup_options_control']) ? 'select' : $blockConfig['option_lookup_options_control'];

        case 'float':
        case 'integer':
          return 'number';

        case 'date':
          return 'date';

        default:
          return NULL;
      }
    }
    else {
      switch ($this->getTypeFromBlockId($blockConfig['id'])) {
        case 'date_picker':
          return 'date';

        case 'location':
          // Map from mode to control type required.
          return ['name' => 'text', 'id_select' => 'select', 'id_autocomplete' => 'autocomplete'][$blockConfig['option_mode']];

        case 'occurrence_comment':
          return 'textarea';

        case 'sample_comment':
          return 'textarea';

        case 'spatial_ref':
          return 'text';

        case 'species_single':
          return 'autocomplete';

        default:
          return NULL;
      }
    }
  }

  /**
   * Returns a readable label for the type of form.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The form node.
   *
   * @return string
   *   Verbose label for the type of data entry form.
   */
  private function getFormTypeLabel(Node $node) {
    if (!in_array($node->field_form_type->value, ['single', 'list', 'multiplace'])) {
      Throw new \Exception(t('Unrecognised form type @type', ['@type' => $node->field_form_type->value]));
    }
    return $node->field_form_type->value . '_species_form';
  }

  /**
   * Retrieve the database field for posting from a control.
   *
   * @param array $blockConfig
   *   Block configuration for the control.
   *
   * @return string
   *   Indicia field name, e.g. occurrence:comment.
   */
  private function getControlFieldName(array $blockConfig) {
    switch ($this->getTypeFromBlockId($blockConfig['id'])) {
      case 'date_picker':
        return 'sample:date';

      case 'location':
        if (!empty($blockConfig['option_mode']) && $blockConfig['option_mode'] === 'name') {
          return 'sample:location_name';
        }
        else {
          return 'sample:location_id';
        }

      case 'map':
        return 'sample:geom';

      case 'occurrence_comment':
        return 'occurrence:comment';

      case 'occurrence_custom_attribute':
        return "occAttr:$blockConfig[option_existing_attribute_id]";

      case 'place_search':
        return NULL;

      case 'sample_comment':
        return 'sample:comment';

      case 'sample_custom_attribute':
        return "smpAttr:$blockConfig[option_existing_attribute_id]";

      case 'spatial_ref':
        return 'sample:entered_sref';

      case 'species_list':
      case 'species_multiplace':
      case 'species_single':
        return NULL;

      default:
        // Should not occur.
        return 'Unknown';
    }
  }

  private function getVerboseDataType($dataTypeCode) {
    $mappings = [
      'I' => 'integer',
      'F' => 'float',
      'T' => 'text',
      'D' => 'date',
      'V' => 'vague_date',
      'B' => 'boolean',
      'L' => 'lookup',
    ];
    return $mappings[$dataTypeCode] ?? $dataTypeCode;
  }

  private function getGridControls(array $fieldConfig, $after, $speciesExtraInfo = []) {
    $r = [];
    if (in_array($fieldConfig['type'], ['species_list', 'species_multiplace'])) {
      if (!$after) {
        $r[] = array_merge([
          'type' => 'species',
          'field_name' => 'occurrence:taxa_taxon_list_id',
          'control_type' => 'autocomplete',
          'label' => 'Species',
          'required' => true,
        ], $speciesExtraInfo);
      }
      if (!$after && !empty($fieldConfig['absence_column'])) {
        $r[] = [
          'type' => 'absence',
          'field_name' => 'occurrence:comment:zero_abundance',
          'control_type' => 'checkbox',
          'label' => 'Absence',
          'required' => false,
        ];
      }
      if ($after && !empty($fieldConfig['spatial_ref_per_row'])) {
        $r[] = [
          'type' => 'spatial_ref',
          'field_name' => 'sample:entered_sref',
          'control_type' => 'text',
          'label' => 'Spatial ref',
          'required' => false,

        ];
      }
      if ($after && !empty($fieldConfig['comments_column'])) {
        $r[] = [
          'type' => 'occurrence_comment',
          'field_name' => 'occurrence:comment',
          'control_type' => 'textarea',
          'label' => 'Comment',
          'required' => false,
        ];
      }
      if ($after && !empty($fieldConfig['sensitivity_column'])) {
        $r[] = [
          'type' => 'sensitivity',
          'field_name' => 'occurrence:sensitivity_precision',
          'control_type' => 'select',
          'label' => 'Sensitivity',
          'terms' => [
            '100' => 'Blur to 100m',
            '1000' => 'Blur to 1km',
            '2000' => 'Blur to 2km',
            '10000' => 'Blur to 10km',
            '100000' => 'Blur to 100km',
          ],
          'required' => false,
        ];
      }
      if ($after && !empty($fieldConfig['media_column'])) {
        $r[] = [
          'type' => 'occurrence_photos',
          'label' => 'Photos',
          'required' => false,
        ];
      }
    }
    return $r;
  }

}
