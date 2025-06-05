<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Location' block.
 *
 * @Block(
 *   id = "data_entry_location_block",
 *   admin_label = @Translation("Indicia data entry location block"),
 *   layout_builder_label = @Translation("Location input"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntryLocationBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => $this->t('Location'),
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
      ],
      'lockable' => [
        '#title' => 'Lock icon',
        '#description' => 'Enable the lock icon so the control value can be re-used on the next form submission.',
        '#type' => 'checkbox',
      ],
      'mode' => [
        '#title' => 'Mode',
        '#type' => 'select',
        '#options' => [
          'name' => 'Text input for ad-hoc location name',
          'id_select' => 'A drop-down list of sites',
          'id_autocomplete' => 'A site search box',
        ],
        '#attributes' => [
          'id' => 'option_mode',
        ],
        '#default_value' => 'name',
        '#required' => TRUE,
      ],
      'locationTypeId' => [
        '#title' => 'Location type',
        '#type' => 'select',
        'populateOptions' => [
          'table' => 'termlists_term',
          'valueField' => 'id',
          'captionField' => 'term',
          'extraParams' => [
            'view' => 'cache',
            'termlist_title' => 'Location types',
            // @todo Extra filter here to limit types suitable.
          ],
        ],
        '#states' => [
          // Hide this option for the 'name' mode where it is irrelevant.
          'invisible' => [
            ':input[id="option_mode"]' => ['value' => 'name'],
          ],
        ],
      ],
      'includeActivitySites' => [
        '#title' => 'Include sites linked to the activity.',
        '#description' => 'Include sites that are available to the activity in the search results.',
        '#type' => 'checkbox',
        '#states' => [
          // Hide this option for the 'name' mode where it is irrelevant.
          'invisible' => [
            ':input[id="option_mode"]' => ['value' => 'name'],
          ],
        ],
        '#default_value' => TRUE,
      ],
      'includeMySites' => [
        '#title' => 'Include the users created sites (My Sites).',
        '#description' => 'Include sites that were created by the user in the search results.',
        '#type' => 'checkbox',
        '#states' => [
          // Hide this option for the 'name' mode where it is irrelevant.
          'invisible' => [
            ':input[id="option_mode"]' => ['value' => 'name'],
          ],
        ],
        '#default_value' => TRUE,
      ],
      'saveUnfoundNameAsAdhoc' => [
        '#title' => 'Save unfound names as ad-hoc location',
        '#description' => 'If the site name given cannot be found in the list of locations then ' .
          'save it as an ad-hoc sample location name.',
        '#type' => 'checkbox',
        '#states' => [
          // Show this control only if the mode is autocomplete.
          'visible' => [
            ':input[id="option_mode"]' => ['value' => 'id_autocomplete'],
          ],
        ],
        '#default_value' => TRUE,
      ],
      'allowSaveToMySites' => [
        '#title' => 'Allow user to save new site names to My Sites',
        '#description' => 'If the site name given is for a new site then a button is shown allowing them to save to My Sites.',
        '#type' => 'checkbox',
        '#states' => [
          // Show this control only if the mode is autocomplete.
          'visible' => [
            ':input[id="option_mode"]' => ['value' => 'id_autocomplete'],
          ],
        ],
        '#default_value' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    iform_load_helpers(['data_entry_helper']);
    $blockConfig = $this->getConfiguration();
    try {
      switch ($blockConfig['option_mode']) {
        case 'name':
          $ctrl = $this->getLocationNameCtrl($blockConfig);
          break;

        case 'id_select':
          $ctrl = $this->getLocationSelectCtrl($blockConfig);
          break;

        case 'id_autocomplete':
          $ctrl = $this->getLocationAutocompleteCtrl($blockConfig);
          break;
      }
    }
    catch (\Exception $e) {
      $ctrl = '<div class="alert alert-warning">Invalid control: ' . $e->getMessage() . '</div>';
    }
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

  private function getLocationNameCtrl($blockConfig) {
    $ctrlOptions = [
      'label' => $blockConfig["option_label"],
      'helpText' => $blockConfig["option_helpText"],
      'fieldname' => 'sample:location_name',
      'lockable' => !empty($blockConfig["option_lockable"]) ? TRUE : FALSE,
    ];
    return \data_entry_helper::text_input($ctrlOptions);
  }

  private function getLocationSelectCtrl($blockConfig) {
    $connection = iform_get_connection_details();
    $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
    $ctrlOptions = [
      'label' => $blockConfig["option_label"],
      'helpText' => $blockConfig["option_helpText"],
      'table' => NULL,
      'report' => 'library/locations/my_sites_lookup_include_type',
      'extraParams' => $readAuth,
      'blankText' => $this->t('-Select site-'),
      'lockable' => !empty($blockConfig["option_lockable"]) ? TRUE : FALSE,
    ];
    if (!empty($blockConfig['option_locationTypeId'])) {
      $ctrlOptions['extraParams']['location_type_id'] = $blockConfig['option_locationTypeId'];
    }
    $userId = hostsite_get_user_field('indicia_user_id');
    if ($blockConfig['option_includeMySites'] === 1 && !empty($userId)) {
      $ctrlOptions['extraParams']['user_id'] = $userId;
    }
    if ($blockConfig['option_includeActivitySites'] === 1 && !empty($_GET['group_id'])) {
      $ctrlOptions['extraParams']['group_id'] = $_GET['group_id'];
    }
    return \data_entry_helper::location_select($ctrlOptions);
  }

  private function getLocationAutocompleteCtrl($blockConfig) {
    $connection = iform_get_connection_details();
    $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
    $ctrlOptions = [
      'label' => $blockConfig["option_label"],
      'helpText' => $blockConfig["option_helpText"],
      'table' => NULL,
      'report' => 'library/locations/my_sites_lookup_include_type',
      'extraParams' => $readAuth,
      // As we don't have an easy way to handle more values like species data.
      'numValues' => 200,
      // Allow user to save to My Sites
      'allowCreate' => $blockConfig['option_allowSaveToMySites'] === 1,
      'useLocationName' => $blockConfig['option_saveUnfoundNameAsAdhoc'] === 1,
    ];
    if (!empty($blockConfig['option_locationTypeId'])) {
      $ctrlOptions['extraParams']['group_id'] = $blockConfig['option_locationTypeId'];
    }
    $userId = hostsite_get_user_field('indicia_user_id');
    if ($blockConfig['option_includeMySites'] === 1 && !empty($userId)) {
      $ctrlOptions['extraParams']['user_id'] = $userId;
    }
    if ($blockConfig['option_includeActivitySites'] === 1 && !empty($_GET['group_id'])) {
      $ctrlOptions['extraParams']['group_id'] = $_GET['group_id'];
    }
    return \data_entry_helper::location_autocomplete($ctrlOptions);
  }

}
