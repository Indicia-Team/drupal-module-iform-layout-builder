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
   *   The ID of the node to fetch, or returns a summary of all form nodes if
   *   not specified.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the form object.
   */
  public function get($id = NULL) {
    $node = node::load($id);
    $response = [
      'title' => $node->getTitle(),
      'survey_id' => $node->field_survey_id->value,
      'type' => $this->getFormTypeLabel($node),
      'subtype' => NULL,
      'data' => [
        'sample:survey_id' => $node->field_survey_id->value,
        'sample:input_form' => trim(\Drupal::service('path_alias.manager')->getAliasByPath("/node/$id"), '/'),
      ],
    ];
    $description = $node->body->value;
    if ($description) {
      $response['description'] = $description;
    }
    // Optional properties.
    if ($node->body->value) {
      $response['description'] = $node->body->value;
    }
    if (empty($_GET['layout'])) {
      $response['controls'] = [];
    }
    else {
      $response['form_sections'] = [];
    }
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
        if (!empty($_GET['layout']) && !isset($regions[$asArray['region']])) {
          $regions[$asArray['region']] = [];
        }
        $fieldConfig = [
          'type' => $this->getTypeFromBlockId($blockConfig['id']),
          'field_name' => $this->getControlFieldName($blockConfig)
        ];
        if (!empty($blockConfig['option_data_type'])) {
          $blockConfig['data_type'] = $this->getVerboseDataType($blockConfig['option_data_type']);
        }
        $ctrlType = $this->getControlType($blockConfig);
        if ($ctrlType) {
          $fieldConfig['control_type'] = $ctrlType;
        }
        $this->cleanupUnwantedProperties($blockConfig);
        // Tidy - remove option_* or option_existint_* prefixes from block config.
        foreach ($blockConfig as $key => $value) {
          $camel = preg_replace('/^option_(existing_)?/', '', $key);
          $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
          $fieldConfig[$snake] = $value;
        }
        $this->addTermsToFieldConfig($fieldConfig);
        // Remove empties.
        $fieldConfig = array_filter($fieldConfig, fn($value) => !is_null($value) && $value !== '');
        // Add control to grid, or top level of form as appropriate.
        if ($fieldConfig['type'] === 'occurrence_custom_attribute' && $response['type'] !== 'Single species form') {
          $gridCustomAttributes[$weight] = $fieldConfig;
        }
        else {
          $regions[$asArray['region']][$weight] = $fieldConfig;
        }
        // Additional info at top level for species list options.
        if ($fieldConfig['spatial_ref_per_row'] ?? 0 === 1) {
          $response['subtype'] = 'optional_spatial_ref_per_occurrence';
        }
      }
      foreach ($regions as &$controlList) {
        foreach ($controlList as &$fieldConfig) {
          // If a species grid, attach the controls list which has to be done
          // at the end.
          if (in_array($fieldConfig['type'], ['species_list', 'species_multiplace'])) {
            $fieldConfig['preload_species_list'] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $fieldConfig['species_list_mode']));
            $fieldConfig['controls'] = array_merge(
              $this->getGridControls($fieldConfig, FALSE),
              array_values($gridCustomAttributes),
              $this->getGridControls($fieldConfig, TRUE),
            );
            unset($fieldConfig['absence_column']);
            unset($fieldConfig['comments_column']);
            unset($fieldConfig['media_column']);
            unset($fieldConfig['sensitivity_column']);
            unset($fieldConfig['spatial_ref_per_row']);
            unset($fieldConfig['species_list_mode']);
            if ($fieldConfig['preload_species_list'] === 'empty') {
              unset($fieldConfig['row_inclusion_mode']);
              unset($fieldConfig['allow_additional_species']);
            }
            elseif ($fieldConfig['preload_species_list'] === 'scratchpad_list') {
              unset($fieldConfig['species_to_add_list_type']);
            }
            // @todo More tidying of non-required species checklist properties depending on the mode.
            // @todo Attach the scratchpad lists.
          }
        }
        if (empty($_GET['layout'])) {
          ksort($controlList);
          $response['controls'] = array_merge($response['controls'], array_values($controlList));
        }
      }
      if (!empty($_GET['layout'])) {
        $response['form_sections'][] = [
          'layout_type' => $section->getLayoutId(),
          'label' => $section->getLayoutSettings()['label'],
          'components' => $regions,
        ];
      }
    }
    // Cleanup if unnecessary.
    if (empty($response['subtype'])) {
      unset($response['subtype']);
    }
    return new ResourceResponse($response);
  }

  /**
   * For lookup controls, add the list of available terms.
   *
   * @param array $fieldConfig
   *   Field configuration, which will be updated with the terms if this is a
   *   lookup control.
   */
  private function addTermsToFieldConfig(array &$fieldConfig) {
    if (isset($fieldConfig['data_type']) && $fieldConfig['data_type'] === 'lookup' && !empty($fieldConfig['existing_termlist_id'])) {
      \iform_load_helpers(['helper_base']);
      $conn = \iform_get_connection_details();
      $readAuth = \helper_base::get_read_auth($conn['website_id'], $conn['password']);
      $terms = \helper_base::get_population_data(array(
        'table' => 'termlists_term',
        'extraParams' => $readAuth + [
          'view' => 'cache',
          'termlist_id' => $fieldConfig['existing_termlist_id'],
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

  private function cleanupUnwantedProperties(array &$blockConfig) {
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
    ];
    return $mappings[$dataTypeCode] ?? $dataTypeCode;
  }

  private function getGridControls(array $fieldConfig, $after) {
    $r = [];
    if (in_array($fieldConfig['type'], ['species_list', 'species_multiplace'])) {
      if (!$after) {
        $r[] = [
          'type' => 'species',
          'field_name' => 'occurrence:taxa_taxon_list_id',
          'control_type' => 'autocomplete',
          'label' => 'Species',
        ];
      }
      if (!$after && !empty($fieldConfig['absence_column'])) {
        $r[] = [
          'type' => 'absence',
          'field_name' => 'occurrence:comment:zero_abundance',
          'control_type' => 'checkbox',
          'label' => 'Absence',
        ];
      }
      if ($after && !empty($fieldConfig['spatial_ref_per_row'])) {
        $r[] = [
          'type' => 'spatial_ref',
          'field_name' => 'sample:entered_sref',
          'control_type' => 'text',
          'label' => 'Spatial ref',
        ];
      }
      if ($after && !empty($fieldConfig['comments_column'])) {
        $r[] = [
          'type' => 'occurrence_comment',
          'field_name' => 'occurrence:comment',
          'control_type' => 'textarea',
          'label' => 'Comment',
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
        ];
      }
      if ($after && !empty($fieldConfig['media_column'])) {
        $r[] = [
          'type' => 'occurrence_photos',
          'label' => 'Photos',
        ];
      }
    }
    return $r;
  }

}