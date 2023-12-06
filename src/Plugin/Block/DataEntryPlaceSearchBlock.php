<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use data_entry_helper;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Place Search' block.
 *
 * @Block(
 *   id = "data_entry_place_search_block",
 *   admin_label = @Translation("Indicia data entry place search block"),
 *   layout_builder_label = @Translation("Place search box"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntryPlaceSearchBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => $this->t('Map search'),
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
        '#helpText' => 'Search for a named place to find it on the map.'
      ],
      'preferredArea' => [
        '#title' => 'Preferred region',
        '#description' => 'Region name (e.g. county) to help the place search prioritise locations with similar names.',
      ],
      'preferredCountry' => [
        '#title' => 'Preferred country',
        '#description' => 'Country name to help the place search prioritise locations with similar names.',
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
    $ctrl = data_entry_helper::georeference_lookup([
      'driver' => 'nominatim',
      'georefPreferredArea' => empty($blockConfig['preferredArea']) ? '' : $blockConfig['preferredArea'],
      'georefCountry' => empty($blockConfig['preferredCountry']) ? '' : $blockConfig['preferredCountry'],
    ]);
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
    ];
  }

}