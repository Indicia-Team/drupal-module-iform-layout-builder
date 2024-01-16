<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\iform_layout_builder\Indicia\SurveyStructure;
use Drupal\Core\Form\FormStateInterface;

abstract class IndiciaCustomAttributeBlockBase extends IndiciaControlBlockBase {

  abstract protected function getAttrEntityName();

  protected function getControlConfigFields() {
    $surveyStructure = new SurveyStructure();
    $existingAttributes = $surveyStructure->getExistingCustomAttributeCaptions($this->getAttrEntityName());
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $attrAdmin = $user->hasPermission('administer indicia attributes');
    $fieldList = [
      'create_or_existing' => [
        '#title' => 'Attribute',
        '#type' => 'select',
        '#options' => [
          'new' => 'Create a new attribute',
          'existing' => 'Use a pre-existing attribute',
        ],
        '#required' => TRUE,
        '#attributes' => [
          'id' => 'option_create_or_existing',
        ],
      ],
      'existing_attribute_id' => [
        '#title' => 'Existing attribute',
        '#description' => 'Select the pre-existing attribute.',
        '#type' => 'select',
        '#options' => $existingAttributes,
        '#empty_option' => '-Choose an attribute-',
        '#states' => [
          // Show this control only if the option 'Use a pre-existing attribute' is selected above.
          'visible' => [
            ':input[id="option_create_or_existing"]' => ['value' => 'existing'],
          ],
        ],
      ],
      'admin_name' => [
        '#description' => 'Name used to uniquely identify this control to other people building forms.',
      ],
      'admin_description' => [
        '#type' => 'textarea',
        '#description' => 'Description saved for this control on the Indicia warehouse.',
        // @todo Visible when attr admin for existing (but needs to load on existing attr select).
        '#states' => [
          'visible' => [
            ':input[id="option_create_or_existing"]' => ['value' => 'new'],
          ],
        ]
      ],
      'label' => [
        '#title' => 'Form label',
        '#description' => 'Label shown for the control when inputting records.',
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#type' => 'textarea',
        '#description' => 'Tip shown beneath the control.',
      ],
      'lockable' => [
        '#title' => 'Lock icon',
        '#description' => 'Enable the lock icon so the control value can be re-used on the next form submission.',
        '#type' => 'checkbox',
      ],
      'suffix' => [
        '#label' => 'Control suffix',
        '#description' => 'Suffix shown after control (e.g the unit).',
      ],
      'required' => [
        '#description' => 'Tick this box to make inputting a value required.',
        '#type' => 'checkbox',
      ],
      'data_type' => [
        '#title' => 'Attribute type',
        '#type' => 'select',
        '#options' => [
          'B' => 'Boolean (on/off)',
          'I' => 'Integer',
          'F' => 'Float',
          'T' => 'Text',
          'L' => 'Lookup',
        ],
        '#empty_value' => '',
        '#states' => [
          // Show this control only if the option 'Create a new attribute' is selected above.
          'visible' => [
            ':input[id="option_create_or_existing"]' => ['value' => 'new'],
          ],
          'required' => [
            ':input[id="option_create_or_existing"]' => ['value' => 'new'],
          ],
        ],
        '#attributes' => [
          'id' => 'option_data_type',
        ],
      ],
      'text_options_control' => [
        '#title' => 'Text control options',
        '#type' => 'select',
        '#options' => [
          'text_input' => 'Single line',
          'textarea' => 'Multiple lines',
        ],
        '#states' => [
          // Show this control only if the attribute type Text is selected above.
          'visible' => [
            ':input[id="option_data_type"]' => ['value' => 'T'],
          ],
        ],
      ],
      'number_options_min' => [
        '#title' => 'Minimum value allowed',
        '#type' => 'number',
        '#step' => 'any',
        '#states' => [
          // Show this control only if the attribute type is numeric.
          'visible' => [
            ':input[id="option_data_type"]' => [['value' => 'I'], ['value' => 'F']],
          ],
        ],
      ],
      'number_options_max' => [
        '#title' => 'Maximum value allowed',
        '#type' => 'number',
        '#step' => 'any',
        '#states' => [
          // Show this control only if the attribute type is numeric.
          'visible' => [
            ':input[id="option_data_type"]' => [['value' => 'I'], ['value' => 'F']],
          ],
        ],
      ],
      'lookup_options_terms' => [
        '#title' => 'Lookup control terms',
        '#description' => 'Enter one option per line.',
        '#type' => 'textarea',
        '#states' => [
          // Show this control only if the attribute type Lookup is selected.
          'visible' => [
            ':input[id="option_data_type"]' => ['value' => 'L'],
          ],
        ],
      ],
      'lookup_options_control' => [
        '#title' => 'Lookup control',
        '#description' => 'Choose which control to use for presenting the options.',
        '#type' => 'select',
        '#options' => [
          'select' => 'Drop down (select)',
          'radio_group' => 'Radio buttons (allows single choice)',
          'checkbox_group' => 'Checkboxes (allows multiple choices)',
        ],
        '#states' => [
          // Show this control only if the attribute type Lookup is selected.
          'visible' => [
            ':input[id="option_data_type"]' => ['value' => 'L'],
          ],
        ],
      ],
    ];
    if (!$attrAdmin) {
      // Controls only enabled if attribute administrator or creating new control.
      $fieldList['admin_name']['#states'] = [
        'visible' => [
          ':input[id="option_create_or_existing"]' => ['value' => 'new'],
        ],
      ];
      $fieldList['lookup_options_terms']['#states']['enabled'] = [
        ':input[id="option_create_or_existing"]' => ['value' => 'new'],
      ];
      $fieldList['number_options_min']['#states']['enabled'] = [
        ':input[id="option_create_or_existing"]' => ['value' => 'new'],
      ];
      $fieldList['number_options_max']['#states']['enabled'] = [
        ':input[id="option_create_or_existing"]' => ['value' => 'new'],
      ];
    }
    else {
      // Admin name always required if attribute administrator.
      $fieldList['admin_name']['#required'] = TRUE;
    }
    return $fieldList;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('dirty', TRUE);
    parent::blockSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $blockConfig = $this->getConfiguration();
    $this->updateFromWarehouseAttribute($blockConfig);
    return parent::blockForm($form, $form_state);
  }

  /**
   * Ensures that existing attribute configuration loads latest from warehouse.
   *
   * E.g. if warehouse contains new termlist terms, or a changed admin name
   * then update the local copy.
   */
  private function updateFromWarehouseAttribute($blockConfig) {
    if (isset($blockConfig['option_create_or_existing']) && $blockConfig['option_create_or_existing'] === 'existing') {
      $surveyStructure = new SurveyStructure();
      $existing = $surveyStructure->getAttribute($this->getAttrEntityName(), $blockConfig['option_existing_attribute_id']);
      if ($existing['httpCode'] === 404) {
        \Drupal::logger('iform_layout_builder')->notice('Existing attribute ID not found: @id', ['@id' => $blockConfig['option_existing_attribute_id']]);
        $this->setConfigurationValue('option_create_or_existing', 'new');
      }
      else {
        $this->setConfigurationValue('option_admin_name', $existing['response']['values']['caption']);
        $this->setConfigurationValue('option_data_type', $existing['response']['values']['data_type']);
        $this->setConfigurationValue('option_existing_termlist_id', $existing['response']['values']['termlist_id']);
        if (isset($existing['response']['terms'])) {
          $this->setConfigurationValue('option_lookup_options_terms', implode("\n", $existing['response']['terms']));
        }
      }
    }
  }

  /**
   * Builds the control for a custom attribute.
   *
   * @param array $blockConfig
   *   Block configuration.
   *
   * @return string
   *   Control HTML.
   */
  protected function getControl($blockConfig) {
    iform_load_helpers(['data_entry_helper']);
    $attrPrefix = $this->getAttrEntityName() === 'sample' ? 'smp' : 'occ';
    if ($blockConfig['option_create_or_existing'] === 'new') {
      $fieldname = "{$attrPrefix}Attr-new";
    }
    else {
      $fieldname = "{$attrPrefix}Attr:$blockConfig[option_existing_attribute_id]";
    }
    $ctrlName = 'text_input';
    switch ($blockConfig['option_data_type']) {
      case 'T':
        $ctrlName = empty($blockConfig['option_text_options_control']) ? 'text_input' : $blockConfig['option_text_options_control'];
        break;

      case 'B':
        $ctrlName = 'checkbox';
        break;

      case 'L':
        $ctrlName = empty($blockConfig['option_lookup_options_control']) ? 'select' : $blockConfig['option_lookup_options_control'];
    }
    $ctrlOptions = [
      'fieldname' => $fieldname,
      'label' => "$blockConfig[option_label]",
      'validation' => [],
      'lockable' => !empty($blockConfig["option_lockable"]) ? TRUE : FALSE,
    ];
    // HTML5 number type for numerics.
    if ($blockConfig['option_data_type'] === 'I' || $blockConfig['option_data_type'] === 'F') {
      $ctrlOptions['attributes'] = ['type' => 'number'];
      if (strlen($blockConfig['option_number_options_min']) > 0) {
        $ctrlOptions['attributes']['min'] = $blockConfig['option_number_options_min'];
      }
      if (strlen($blockConfig['option_number_options_max']) > 0) {
        $ctrlOptions['attributes']['max'] = $blockConfig['option_number_options_max'];
      }
      if ($blockConfig['option_data_type'] === 'I') {
        $ctrlOptions['validation'][] = 'integer';
      }
      elseif ($blockConfig['option_data_type'] === 'F') {
        $ctrlOptions['attributes']['step'] = 'any';
      }
    }
    if (!empty($blockConfig['option_suffix'])) {
      $ctrlOptions['afterControl'] = $blockConfig['option_suffix'];
    }
    $helpTexts = [];
    if (!empty($blockConfig['option_helpText'])) {
      $helpTexts[] = $blockConfig['option_helpText'];
    }
    if ($blockConfig['option_create_or_existing'] === 'new') {
      $helpTexts[] = $this->t('This control has not been linked to a warehouse attribute yet.');
    }
    if (count($helpTexts) > 0) {
      $ctrlOptions['helpText'] = implode('<br/>', $helpTexts);
    }
    if ($blockConfig['option_required'] === 1) {
      $ctrlOptions['validation'][] = 'required';
    }
    if ($blockConfig['option_data_type'] === 'L') {
      if (!empty($blockConfig['option_existing_termlist_id'])) {
        $connection = iform_get_connection_details();
        $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
        $ctrlOptions['table'] = 'termlists_term';
        $ctrlOptions['valueField'] = 'id';
        $ctrlOptions['captionField'] = 'term';
        $ctrlOptions['blankText'] = $this->t('-Please select-');
        $ctrlOptions['extraParams'] = $readAuth + [
          'termlist_id' => $blockConfig['option_existing_termlist_id'],
          // Form editors get the uncached terms view, users get cached terms table.
          'view' => !empty($_SESSION['iform_layout_builder-no_termlist_cache']) ? 'list' : 'cache',
          'allow_data_entry' => 't',
          'sharing' => 'editing',
          'orderby' => 'sort_order,term',
        ];
      }
      else {
        $ctrlOptions['lookupValues'] = ['' => '-Will be populated from lookup options-'];
      }
    }
    return \data_entry_helper::$ctrlName($ctrlOptions);
  }
}