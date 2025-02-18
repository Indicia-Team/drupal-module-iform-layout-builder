<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for control blocks that allow lists of species to be input.
 */
abstract class IndiciaSpeciesListBlockBase extends IndiciaControlBlockBase {

  /**
   * Common admin form controls.
   *
   * @var array
   */
  protected $listConfigFormControls = [
    'speciesListMode' => [
      '#title' => 'Starting point',
      '#description' => 'How should the species list behave when initially loaded',
      '#type' => 'select',
      '#options' => [
        'empty' => 'An empty list to add species to',
        'scratchpadList' => 'Pre-populated with a custom species list to tick off',
        'taxonGroup' => 'Pre-populated with a taxon group list to tick off',
      ],
      '#required' => TRUE,
      '#empty_option' => '-Please select-',
    ],
    'preloadedScratchpadListId' => [
      '#title' => 'Preloaded custom species list',
      '#description' => 'Species from this custom list will be pre-loaded for ticking in the data entry grid',
      '#type' => 'select',
      '#empty_option' => '-Please select-',
      'populateOptions' => [
        'table' => 'scratchpad_list',
        'valueField' => 'id',
        'captionField' => 'title',
      ],
      '#states' => [
        // Show this control when the options require a custom species list to be chosen.
        'visible' => [
          ':input[name="settings[option_speciesListMode]"]' => ['value' => 'scratchpadList'],
        ],
        'enabled' => [
          ':input[name="settings[option_speciesListMode]"]' => ['value' => 'scratchpadList'],
        ],
        'required' => [
          ':input[name="settings[option_speciesListMode]"]' => ['value' => 'scratchpadList'],
        ],
      ],
    ],
    'preloadedTaxonGroupId' => [
      '#title' => 'Preloaded taxon group',
      '#description' => 'Species from this group will be pre-loaded for ticking in the data entry grid.',
      '#type' => 'select',
      '#empty_option' => '-Please select-',
      'populateOptions' => [
        'table' => 'taxon_group',
        'valueField' => 'id',
        'captionField' => 'title',
      ],
      '#states' => [
        // Show this control when the options require a taxon group list to be chosen.
        'visible' => [
          ':input[name="settings[option_speciesListMode]"]' => ['value' => 'taxonGroup'],
        ],
        'enabled' => [
          ':input[name="settings[option_speciesListMode]"]' => ['value' => 'taxonGroup'],
        ],
        'required' => [
          ':input[name="settings[option_speciesListMode]"]' => ['value' => 'taxonGroup'],
        ],
      ],
    ],
    'allowAdditionalSpecies' => [
      '#title' => 'Allow extra species to be added to bottom of list',
      '#description' => 'Allow extra species to be added to the pre-loaded checklist.',
      '#type' => 'checkbox',
      '#states' => [
        // Show this control the options require a custom species list to be
        // chosen.
        'visible' => [
          ':input[name="settings[option_speciesListMode]"]' => [
            ['value' => 'scratchpadList'],
            'or',
            ['value' => 'taxonGroup'],
          ],
        ],
        'enabled' => [
          ':input[name="settings[option_speciesListMode]"]' => [
            ['value' => 'scratchpadList'],
            'or',
            ['value' => 'taxonGroup'],
          ],
        ],
      ],
    ],
    'speciesToAddListType' => [
      '#title' => 'What species can be added',
      '#description' => 'Select the limitations that will be applied to available species when adding extra species to the bottom of the grid.',
      '#type' => 'select',
      '#options' => [
        'all' => 'Any species from the master checklist',
        'scratchpadList' => 'A custom list of species',
        'taxonGroup' => 'Any species from a selected taxon group in the master checklist',
      ],
      '#empty_option' => '-Please select-',
      '#states' => [
        // Show this control only if the option 'Start with an empty list to
        // add species to' is checked above.
        'visible' => [
          [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
          'or',
          [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
        ],
        'enabled' => [
          [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
          'or',
          [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
        ],
        'required' => [
          [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
          'or',
          [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
        ],
      ],
    ],
    'additionalSpeciesScratchpadListId' => [
      '#title' => 'Custom list for species to allow addition of',
      '#description' => 'List of species available for addition to the data entry grid.',
      '#type' => 'select',
      '#empty_option' => '-Please select-',
      'populateOptions' => [
        'table' => 'scratchpad_list',
        'valueField' => 'id',
        'captionField' => 'title',
      ],
      '#states' => [
        // Show this control when the options require a custom species list to
        // be chosen.
        'visible' => [
          [
            [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
            'or',
            [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
          ],
          ':input[name="settings[option_speciesToAddListType]"]' => ['value' => 'scratchpadList'],
        ],
        'enabled' => [
          [
            [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
            'or',
            [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
          ],
          ':input[name="settings[option_speciesToAddListType]"]' => ['value' => 'scratchpadList'],
        ],
        'required' => [
          [
            [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
            'or',
            [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
          ],
          ':input[name="settings[option_speciesToAddListType]"]' => ['value' => 'scratchpadList'],
        ],
      ],
    ],
    'additionalSpeciesTaxonGroupId' => [
      '#title' => 'Taxon group for species to allow addition of',
      '#description' => 'Only allow species from this group',
      '#type' => 'select',
      '#empty_option' => '-Any-',
      'populateOptions' => [
        'table' => 'taxon_group',
        'valueField' => 'id',
        'captionField' => 'title',
      ],
      '#states' => [
        // Show this control when the options require a custom species list to
        // be chosen.
        'visible' => [
          [
            [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
            'or',
            [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
          ],
          ':input[name="settings[option_speciesToAddListType]"]' => ['value' => 'taxonGroup'],
        ],
        'enabled' => [
          [
            [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
            'or',
            [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
          ],
          ':input[name="settings[option_speciesToAddListType]"]' => ['value' => 'taxonGroup'],
        ],
        'required' => [
          [
            [':input[name="settings[option_speciesListMode]"]' => ['value' => 'empty']],
            'or',
            [':input[name="settings[option_allowAdditionalSpecies]"]' => ['checked' => TRUE]],
          ],
          ':input[name="settings[option_speciesToAddListType]"]' => ['value' => 'taxonGroup'],
        ],
      ],
    ],
    'rowInclusionMode' => [
      '#title' => 'Records are created for a row when',
      '#description' => 'Determines the method used to determine whether a record is created for a row in the grid.',
      '#type' => 'select',
      '#options' => [
        'checkbox' => 'The "Present" box is checked.',
        'hasData' => 'The "Present" box is checked or if any of the attribute cells are filled in.',
      ],
      '#states' => [
        // Show this control the options require a custom species list to be
        // chosen.
        'visible' => [
          ':input[name="settings[option_speciesListMode]"]' => [
            ['value' => 'scratchpadList'],
            'or',
            ['value' => 'taxonGroup'],
          ],
        ],
        'enabled' => [
          ':input[name="settings[option_speciesListMode]"]' => [
            ['value' => 'scratchpadList'],
            'or',
            ['value' => 'taxonGroup'],
          ],
        ],
      ],
    ],
    'absenceColumn' => [
      '#title' => 'Include an absence checkbox column',
      '#description' => 'If checked, then an Absence checkbox is added to each row so the recorder can explicitly control which are records of absence.',
      '#type' => 'checkbox',
    ],
    'commentsColumn' => [
      '#title' => 'Allow comments for each record',
      '#type' => 'checkbox',
      '#description' => 'Tick to add a column to the list for inputting a comment against each record.',
      '#default_value' => TRUE,
    ],
    'mediaColumn' => [
      '#title' => 'Allow images upload',
      '#type' => 'checkbox',
      '#description' => 'Tick to add a column to the list for uploading images to support each record.',
      '#default_value' => TRUE,
    ],
    'sensitivityColumn' => [
      '#title' => 'Allow sensitivity to be set for each record',
      '#type' => 'checkbox',
      '#description' => 'Tick to add a column to the list for inputting a sensitivity blur against each record.',
      '#default_value' => FALSE,
    ],
  ];

  /**
   * Prepare the options for a species_checklist control from block config.
   */
  protected function getSpeciesChecklistControlOptions($blockConfig) {
    $connection = iform_get_connection_details();
    $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
    $configFieldList = $this->getControlConfigFields();
    $node = $this->getCurrentNode();
    $ctrlOptions = [
      'columns' => 1,
      'extraParams' => $readAuth,
      'editTaxaNames' => TRUE,
      'survey_id' => $node ? $node->field_survey_id->value : NULL,
      'occurrenceComment' => isset($blockConfig["option_commentsColumn"]) && $blockConfig["option_commentsColumn"] === 1,
      'occurrenceImages' => isset($blockConfig["option_mediaColumn"]) && $blockConfig["option_mediaColumn"] === 1,
      'occurrenceSensitivity' => isset($blockConfig["option_sensitivityColumn"]) && $blockConfig["option_sensitivityColumn"] === 1,
      'lookupListId' => hostsite_get_config_value('iform', 'master_checklist_id', 0),
      'absenceCol' => isset($blockConfig["option_absenceColumn"]) && $blockConfig["option_absenceColumn"] === 1,
    ];




    if ($blockConfig['option_speciesListMode'] === 'scratchpadList' && !empty($blockConfig['option_preloadedScratchpadListId'])) {
      // Load the whole list, but the getPreloadScratchpadListControl will
      // ensure we only preload the correct list.
      //$ctrlOptions['lookupListId'] = hostsite_get_config_value('iform', 'master_checklist_id', 0);
    }
    if ($blockConfig['option_speciesListMode'] === 'taxon_group_id' && !empty($blockConfig['option_preloadedTaxonGroupId'])) {
      // @todo TaxonGroup filtered preloaded list
    }
    if ($blockConfig['option_speciesListMode'] !== 'empty') {
      if (empty($blockConfig["option_allowAdditionalSpecies"])) {
        // Disallow additional taxa row.
        $ctrlOptions['allowAdditionalTaxa'] = FALSE;
      }
      if (!empty($blockConfig['option_rowInclusionMode'])) {
        $ctrlOptions['rowInclusionMode'] = $blockConfig['option_rowInclusionMode'];
      }
    }

    if ($blockConfig['option_speciesToAddListType'] === 'scratchpadList' && !empty($blockConfig['option_additionalSpeciesScratchpadListId'])) {
      // Results when searching need to be filtered.
      $ctrlOptions['extraParams']['scratchpad_list_id'] = $blockConfig['option_additionalSpeciesScratchpadListId'];
    }
    elseif ($blockConfig['option_speciesToAddListType'] === 'taxonGroup' && !empty($blockConfig['option_additionalSpeciesTaxonGroupId'])) {
      // Results when searching need to be filtered.
      $ctrlOptions['extraParams']['taxon_group_id'] = $blockConfig['option_additionalSpeciesTaxonGroupId'];
    }

    foreach (array_keys($configFieldList) as $opt) {
      if (isset($blockConfig["option_$opt"])) {
        $ctrlOptions[$opt] = $blockConfig["option_$opt"];
      }
    }
    return $ctrlOptions;
  }

  /**
   * If preloading a scratchpad list, add the code required to the page.
   */
  protected function getPreloadScratchpadListControl($blockConfig, &$ctrlOptions) {
    if (isset($blockConfig['option_speciesListMode']) && $blockConfig['option_speciesListMode'] === 'scratchpadList') {
      require_once \data_entry_helper::client_helper_path() . 'prebuilt_forms/extensions/misc_extensions.php';
      $connection = iform_get_connection_details();
      $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
      $ctrlOptions['rowInclusionCheck'] = 'checkbox';
      return \extension_misc_extensions::load_species_list_from_scratchpad(
        ['read' => $readAuth],
        [],
        NULL,
        [
          'scratchpad_list_id' => $blockConfig['option_preloadedScratchpadListId'],
          'tickAll' => FALSE,
          'showMessage' => FALSE,
        ]
      );
    }
    return '';
  }

  /**
   * Validation - tidy unnecessary form values due to state.
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if ($values['option_speciesListMode'] === 'empty') {
      $form_state->setValue('option_rowInclusionMode', NULL);
      $form_state->setValue('option_preloadedScratchpadListId', NULL);
      $form_state->setValue('option_preloadedTaxonGroupId', NULL);
    }
    else {
      // Preloading a list.
      if (!$values['option_allowAdditionalSpecies']) {
        $form_state->setValue('option_speciesToAddListType', NULL);
      }
      if ($values['option_speciesListMode'] === 'scratchpadList') {
        $form_state->setValue('option_additionalSpeciesTaxonGroupId', NULL);
      }
      elseif ($values['option_speciesListMode'] === 'taxonGroup') {
        $form_state->setValue('option_additionalSpeciesScratchpadListId', NULL);
      }
    }
    $values = $form_state->getValues();
  }

}
