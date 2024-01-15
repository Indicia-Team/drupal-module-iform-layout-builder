<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

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
      ],
      '#required' => TRUE,
      '#empty_option' => '-Please select-',
      '#attributes' => [
        'id' => 'option_speciesListMode',
      ],
    ],
    'speciesToAddListType' => [
      '#title' => 'What species can be added',
      '#description' => 'Either allow any species from the master checklist (configured in the Indicia settings) ' .
        'or choose a custom species list to limit the available species to.',
      '#type' => 'select',
      '#options' => [
        'all' => 'Any species from the master checklist',
        'scratchpadList' => 'A custom list of species',
      ],
      '#empty_option' => '-Please select-',
      '#attributes' => [
        'id' => 'option_speciesToAddListType',
      ],
      '#states' => [
        // Show this control only if the option 'Start with an empty list to
        // add species to' is checked above.
        'visible' => [
          ':input[id="option_speciesListMode"]' => ['value' => 'empty'],
        ],
        'required' => [
          ':input[id="option_speciesListMode"]' => ['value' => 'empty'],
        ],
      ],
    ],
    'additionalSpeciesScratchpadListId' => [
      '#title' => 'Custom species list',
      '#description' => 'List of species available for addition to the data entry grid.',
      '#type' => 'select',
      '#empty_option' => '-Please select-',
      'populateOptions' => [
        'table' => 'scratchpad_list',
        'valueField' => 'id',
        'captionField' => 'title',
      ],
      '#states' => [
        // Show this control the options require a custom species list to be chosen
        'visible' => [
          ':input[id="option_speciesListMode"]' => ['value' => 'empty'],
          ':input[id="option_speciesToAddListType"]' => ['value' => 'scratchpadList'],
        ],
        'required' => [
          ':input[id="option_speciesListMode"]' => ['value' => 'empty'],
          ':input[id="option_speciesToAddListType"]' => ['value' => 'scratchpadList'],
        ],
      ],
    ],
    'preloadedScratchpadListId' => [
      '#title' => 'Custom species list',
      '#description' => 'List of species pre-loaded for ticking in the data entry grid.',
      '#type' => 'select',
      '#empty_option' => '-Please select-',
      'populateOptions' => [
        'table' => 'scratchpad_list',
        'valueField' => 'id',
        'captionField' => 'title',
      ],
      '#states' => [
        // Show this control the options require a custom species list to be chosen
        'visible' => [
          ':input[id="option_speciesListMode"]' => ['value' => 'scratchpadList'],
        ],
        'required' => [
          ':input[id="option_speciesListMode"]' => ['value' => 'scratchpadList'],
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
          ':input[id="option_speciesListMode"]' => ['value' => 'scratchpadList'],
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
          ':input[id="option_speciesListMode"]' => ['value' => 'scratchpadList'],
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
    if ($blockConfig['option_speciesListMode'] === 'scratchpadList') {
      // Load the whole list, but the getPreloadScratchpadListControl will
      // ensure we only preload the correct list.
      $ctrlOptions['lookupListId'] = hostsite_get_config_value('iform', 'master_checklist_id', 0);
    }
    if ($blockConfig['option_speciesListMode'] === 'scratchpadList' && empty($blockConfig["option_allowAdditionalSpecies"])) {
      // Disallow additional taxa row.
      $ctrlOptions['allowAdditionalTaxa'] = FALSE;
    }
    if ($blockConfig['option_speciesListMode'] === 'scratchpadList' && !empty($blockConfig['option_rowInclusionMode'])) {
      $ctrlOptions['rowInclusionMode'] = $blockConfig['option_rowInclusionMode'];
    }
    if ($blockConfig['option_speciesListMode'] === 'empty' && $blockConfig['option_speciesToAddListType'] === 'scratchpadList') {
      // Empty list but results when searching need to be filtered.
      $ctrlOptions['extraParams']['scratchpad_list_id'] = $blockConfig['option_additionalSpeciesScratchpadListId'];
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

}