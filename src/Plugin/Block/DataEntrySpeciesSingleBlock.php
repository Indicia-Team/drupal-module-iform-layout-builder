<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Single Species Input' block.
 *
 * @Block(
 *   id = "data_entry_species_single_block",
 *   admin_label = @Translation("Indicia data entry species single block"),
 *   layout_builder_label = @Translation("Single species"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntrySpeciesSingleBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
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
      'scratchpadListId' => [
        '#title' => 'Limit species available to custom list',
        '#description' => 'List of species available for selection.',
        '#type' => 'select',
        '#empty_option' => '-All species available-',
        'populateOptions' => [
          'table' => 'scratchpad_list',
          'valueField' => 'id',
          'captionField' => 'title',
        ],
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
    $connection = iform_get_connection_details();
    $readAuth = \data_entry_helper::get_read_auth($connection['website_id'], $connection['password']);
    $blockConfig = $this->getConfiguration();
    $configFieldList = $this->getControlConfigFields();
    $ctrlOptions = [
      'table' => 'taxa_search',
      'captionField' => 'taxon',
      'valueField' => 'taxa_taxon_list_id',
      'fieldname' => 'occurrence:taxa_taxon_list_id',
      'label' => \lang::get('occurrence:taxa_taxon_list_id'),
      'extraParams' => $readAuth + ['taxon_list_id' => hostsite_get_config_value('iform', 'master_checklist_id', 0)],
    ];
    if (!empty($blockConfig['option_scratchpadListId'])) {
      $ctrlOptions['extraParams']['scratchpad_list_id'] = $blockConfig['option_scratchpadListId'];
    }
    unset($configFieldList['taxonListId']);
    $this->applyBlockConfigToControl($blockConfig, $ctrlOptions);
    try {
      $ctrl = \data_entry_helper::autocomplete($ctrlOptions);
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

}