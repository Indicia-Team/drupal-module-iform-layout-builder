<?php

namespace Drupal\iform_layout_builder\Plugin\rest\resource;

use Drupal\core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  private $entityTypeManager;

  private $aliasManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager, AliasManagerInterface $aliasManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
    $this->aliasManager = $aliasManager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager'),
    );
  }

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
    $node = $this->entityTypeManager->getStorage('node')->load($id);
    if (!$node || $node->getType() !== 'iform_layout_builder_form') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found');
    }
    $data = [
      'id' => $node->id(),
      'title' => $node->getTitle(),
      'survey_id' => (integer) $node->field_survey_id->value,
      'type' => $this->getFormTypeLabel($node),
      'subtype' => NULL,
      'created_by_uid' => (integer) $node->getOwnerID(),
      'created_by_id' => (integer) $node->getOwner()->field_indicia_user_id->value,
      'created_on' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'c'),
      'is_published' => $node->isPublished(),
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
    $subsampleControls = [];
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
          'field_name' => $this->getControlFieldName($blockConfig, $node),
        ];
        if (!empty($blockConfig['option_data_type'])) {
          $blockConfig['data_type'] = $this->getVerboseDataType($blockConfig['option_data_type']);
        }
        $fieldConfig['control_type'] = $this->getControlType($blockConfig);

        if (!$node->isPublished() && substr($fieldConfig['type'], -17) === '_custom_attribute') {
          $this->addUnpublishedAttrInfo($blockConfig);
        }
        $this->cleanupUnwantedBlockConfigProperties($blockConfig);
        foreach ($blockConfig as $key => $value) {
          // Tidy - remove option_* or option_existing_* prefixes from block
          // config.
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
        if (!in_array($fieldConfig['type'], ['species_list', 'species_multiplace'])) {
          $fieldConfig['validation'] = [
            'required' => $this->getIsRequired($fieldConfig),
          ];
          unset($fieldConfig['required']);
        }
        if (isset($fieldConfig['data_type']) && $fieldConfig['data_type'] === 'lookup') {
          $this->addTermsToFieldConfig($fieldConfig);
        }
        if ($fieldConfig['type'] === 'species_single') {
          $this->formatSingleSpeciesControl($fieldConfig);
        }
        $this->formatAdditionalValidation($fieldConfig);
        // Remove empties.
        $fieldConfig = array_filter($fieldConfig, fn($value) => !is_null($value) && $value !== '');
        // Add control to grid, or top level of form as appropriate.
        if ($fieldConfig['type'] === 'occurrence_custom_attribute' && $data['type'] !== 'single_species_form') {
          $gridCustomAttributes[$weight] = $fieldConfig;
        }
        elseif ($fieldConfig['type'] === 'sample_custom_attribute' && !empty($fieldConfig['child_sample_attribute']) && $data['type'] === 'multiplace_species_form') {
          $subsampleControls[$weight] = $fieldConfig;
        }
        else {
          // Multiply the weight by 2 to allow space for extra controls, e.g.
          // if sref requires an sref system control.
          $regions[$asArray['region']][$weight * 2] = $fieldConfig;
          if ($fieldConfig['type'] === 'spatial_ref') {
            $this->addSrefSystemControl($fieldConfig, $regions, $asArray['region'], $weight);
          }
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
            // Species multiplace puts list into a sub-sample.
            if ($fieldConfig['type'] === 'species_multiplace') {
              $fieldConfig['type'] = 'species_list';
              $subsampleControls[] = [
                'control_type' => 'hidden',
                'spatial_system' => $fieldConfig['spatial_system'],
                'field_name' => 'sample:sample_method_id',
                'default_value' => (integer) $node->field_child_sample_method_id->value,
                'validation' => ['required' => TRUE],
              ];
              unset($fieldConfig['spatial_system']);
              // Move the species list control inside the sub-sample.
              $subsampleControls[] = $fieldConfig;
              $fieldConfig = [
                'type' => 'sub_samples',
                'controls' => array_values($subsampleControls),
              ];
            }
          }
        }
      }
    }
    // Cleanup if unnecessary.
    if (empty($data['subtype'])) {
      unset($data['subtype']);
    }
    if (empty($_GET['layout'])) {
      // Flatten response to ordered list of controls.
      $allControls = [
        [
          'control_type' => 'hidden',
          'field_name' => 'sample:survey_id',
          'default_value' => (integer) $node->field_survey_id->value,
          'validation' => ['required' => TRUE],
        ],
        [
          'control_type' => 'hidden',
          'field_name' => 'sample:input_form',
          'default_value' => trim($this->aliasManager->getAliasByPath("/node/$id"), '/'),
        ]
      ];
      if ($node->field_sample_method_id->value) {
        $allControls[] = [
          'control_type' => 'hidden',
          'field_name' => 'sample:sample_method_id',
          'default_value' => (integer) $node->field_sample_method_id->value,
          'validation' => ['required' => TRUE],
        ];
      }
      foreach ($formSections as &$section) {
        foreach ($section['components'] as &$regionControlList) {
          ksort($regionControlList);
          if (empty($_GET['layout'])) {
            $allControls = array_merge($allControls, $regionControlList);
          }
        }
      }
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
   * Format the validation section of a control.
   *
   * @param array $fieldConfig
   *   Configuration of the control being formatted.
   */
  private function formatAdditionalValidation(array &$fieldConfig) {
    if (isset($fieldConfig['number_options_min'])) {
      $fieldConfig['validation']['min'] = $fieldConfig['number_options_min'];
      unset($fieldConfig['number_options_min']);
    }
    if (isset($fieldConfig['number_options_max'])) {
      $fieldConfig['validation']['max'] = $fieldConfig['number_options_max'];
      unset($fieldConfig['number_options_max']);
    }
    if ($fieldConfig['type'] === 'date_picker') {
      $fieldConfig['validation']['allow_future'] = FALSE;
    }
  }

  /**
   * Append a control for the spatial ref system.
   *
   * @param array $fieldConfig
   *   Configuration of the spatial ref field.
   * @param array $regions
   *   Regions data for the output, which the control will be inserted into.
   * @param string $region
   *   Region name to add the control to.
   * @param int $weight
   *   Weight of the spatial ref control, so the system control can go after.
   */
  private function addSrefSystemControl(array $fieldConfig, array &$regions, $region, $weight) {
    $systems = \Drupal::config('iform.settings')->get('spatial_systems');
    $systemList = explode(',', $systems);
    if (!empty($fieldConfig['system']) && array_key_exists($fieldConfig['system'], $systemList)) {
      // Replace array with single chosen value.
      $systemList = $fieldConfig['system'];
    }
    if (count($systemList) === 1) {
      // Single coordinate system, so embed a hidden.
      $regions[$region][$weight * 2 + 1] = [
        'type' => 'spatial_ref_system',
        'control_type' => 'hidden',
        'field_name' => 'sample:entered_sref_system',
        'default_value' => $systemList[0],
        'validation' => ['required' => TRUE],
      ];
    }
    elseif (count($systemList) > 1) {
      // Multiple coordinate systems, so embed a select.
      $regions[$region][$weight * 2 + 1] = [
        'type' => 'spatial_ref_system',
        'control_type' => 'select',
        'field_name' => 'sample:entered_sref_system',
        'options' => $systemList,
        'lockable' => TRUE,
        'validation' => ['required' => TRUE],
      ];
    }
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
      $terms = \helper_base::get_population_data([
        'table' => 'termlists_term',
        'extraParams' => $readAuth + [
          // Form editors get the uncached terms view, users get cached terms
          // table.
          'view' => !empty($_SESSION['iform_layout_builder-no_termlist_cache']) ? 'list' : 'cache',
          'termlist_id' => $fieldConfig['termlist_id'],
          'orderby' => 'sort_order, term',
          'preferred' => 't',
          'columns' => 'id,term,parent_id,preferred_image_path',
        ],
      ]);
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
   * Format the configuration returned for a single species control.
   *
   * @param array $fieldConfig
   *   Configuration for the control which will be modified.
   */
  private function formatSingleSpeciesControl(array &$fieldConfig) {
    if (empty($fieldConfig['scratchpad_list_id'])) {
      $fieldConfig['taxon_list_id'] = (integer) \Drupal::config('iform.settings')->get('master_checklist_id');
    }
    else {
      $fieldConfig['limit_taxa_to'] = $this->getScratchpadTaxonNames($fieldConfig['scratchpad_list_id'], TRUE);
      // If only a single species available, convert to a hidden input.
      if (count($fieldConfig['limit_taxa_to']) === 1) {
        $fieldConfig = [
          'type' => 'species_single',
          'control_type' => 'hidden',
          'default_value' => (integer) $fieldConfig['limit_taxa_to'][0]['taxa_taxon_list_id'],
          'required' => TRUE,
        ];
      }
    }
    $fieldConfig['field_name'] = 'occurrence:taxa_taxon_list_id';
  }

  /**
   * Add info to unpublished custom attributes.
   *
   * When a layout is on an unpublished node, nothing gets saved to the
   * warehouse. Add enough info to the custom attribute response so that the
   * app gets a valid response (albeit with 0 for the various IDs). Allows
   * preparation of unpublished forms that can be viewed in the app.
   *
   * @param array $blockConfig
   *   Block configuraiton which gets modified.
   */
  private function addUnpublishedAttrInfo(array &$blockConfig) {

    if (empty($blockConfig['option_existing_attribute_id'])) {
      $blockConfig['option_existing_attribute_id'] = 0;
    }
    if ($blockConfig['option_data_type'] === 'L' && empty($blockConfig['option_existing_termlist_id'])) {
      $blockConfig['option_existing_termlist_id'] = 0;
      if (!empty($blockConfig['option_lookup_options_terms'])) {
        $termsText = str_replace("\r\n", "\n", $blockConfig['option_lookup_options_terms']);
        $termsText = str_replace("\r", "\n", $termsText);
        $terms = explode("\n", trim($termsText));
        $blockConfig['option_terms'] = [];
        foreach ($terms as $term) {
          $blockConfig['option_terms'][] = [
            'id' => 0,
            'term' => $term,
          ];
        }
      }
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
  private function formatSpeciesListControl(array &$fieldConfig, array $gridCustomAttributes) {
    if ($fieldConfig['species_list_mode'] === 'scratchpadList') {
      $fieldConfig['preload_taxa'] = $this->getScratchpadTaxonNames($fieldConfig['preloaded_scratchpad_list_id'], TRUE);
      // Unset irrelevant options for this mode.
      unset($fieldConfig['species_to_add_list_type']);
      unset($fieldConfig['additional_species_scratchpad_list_id']);
      // Tidy as bool.
      $fieldConfig['allow_additional_species'] = !empty($fieldConfig['allow_additional_species']);
      if ($fieldConfig['allow_additional_species']) {
        $speciesExtraInfo = [
          'taxon_list_id' => (integer) \Drupal::config('iform.settings')->get('master_checklist_id'),
        ];
      }
    }
    else {
      // Start with an empty list.
      $fieldConfig['allow_additional_species'] = TRUE;
      if ($fieldConfig['species_to_add_list_type'] === 'scratchpadList') {
        // Attach the loaded scratchpad list to the species input control in
        // gridCustomAttributes.
        $speciesExtraInfo = [
          'limit_taxa_to' => $this->getScratchpadTaxonNames($fieldConfig['additional_species_scratchpad_list_id'], FALSE),
        ];
      }
      elseif ($fieldConfig['species_to_add_list_type'] === 'all') {
        $speciesExtraInfo = [
          'taxon_list_id' => \Drupal::config('iform.settings')->get('master_checklist_id'),
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
    return $blockConfig['required'] ?? in_array($blockConfig['type'], [
      'date_picker',
      'spatial_ref',
      'species_single',
    ]);
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
   * @param \Drupal\core\Entity\EntityInterface $node
   *   The form node.
   *
   * @return string
   *   Verbose label for the type of data entry form.
   */
  private function getFormTypeLabel(EntityInterface $node) {
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
  private function getControlFieldName(array $blockConfig, $node) {
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
        if (!$node->isPublished() && empty($blockConfig['option_existing_attribute_id'])) {
          return 'occAttr:0';
        }
        return "occAttr:$blockConfig[option_existing_attribute_id]";

      case 'place_search':
        return NULL;

      case 'sample_comment':
        return 'sample:comment';

      case 'sample_custom_attribute':
        if (!$node->isPublished() && empty($blockConfig['option_existing_attribute_id'])) {
          return 'smpAttr:0';
        }
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
          'validation' => [
            'required' => TRUE,
          ],
        ], $speciesExtraInfo);
      }
      if (!$after && !empty($fieldConfig['absence_column'])) {
        $r[] = [
          'type' => 'absence',
          'field_name' => 'occurrence:comment:zero_abundance',
          'control_type' => 'checkbox',
          'label' => 'Absence',
          'validation' => [
            'required' => FALSE,
          ],
        ];
      }
      if ($after && !empty($fieldConfig['spatial_ref_per_row'])) {
        $r[] = [
          'type' => 'spatial_ref',
          'field_name' => 'sample:entered_sref',
          'control_type' => 'text',
          'label' => 'Spatial ref',
          'validation' => [
            'required' => FALSE,
          ],
        ];
      }
      if ($after && !empty($fieldConfig['comments_column'])) {
        $r[] = [
          'type' => 'occurrence_comment',
          'field_name' => 'occurrence:comment',
          'control_type' => 'textarea',
          'label' => 'Comment',
          'validation' => [
            'required' => FALSE,
          ],
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
          'validation' => [
            'required' => FALSE,
          ],
        ];
      }
      if ($after && !empty($fieldConfig['media_column'])) {
        $r[] = [
          'type' => 'occurrence_photos',
          'label' => 'Photos',
          'validation' => [
            'required' => FALSE,
          ],
        ];
      }
    }
    return $r;
  }

}
