<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Occurrence Sensitivity' block.
 *
 * @Block(
 *   id = "data_entry_occurrence_sensitivity_block",
 *   admin_label = @Translation("Indicia data entry occurrence sensitivity block"),
 *   layout_builder_label = @Translation("Occurrence sensitivity input"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntryOccurrenceSensitivityBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'defaultBlur' => [
        '#title' => 'Default blur',
        '#description' => 'If the form should blur records by default, then set the blur value here. The user can override it.',
        '#type' => 'select',
        '#options' => [
          '100' => '100m',
          '1000' => '1km',
          '2000' => '2km',
          '10000' => '10km',
          '100000' => '100km',
        ],
        '#empty_value' => '',
      ],
      'lockable' => [
        '#title' => 'Lock icon',
        '#description' => 'Enable the lock icon so the control value can be re-used on the next form submission.',
        '#type' => 'checkbox',
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
    $ctrlOptions = [
      'lockable' => !empty($blockConfig["option_lockable"]) ? TRUE : FALSE,
    ];
    if (!empty($blockConfig['options_defaultBlur'])) {
      $ctrlOptions['defaultBlur'] = $blockConfig['options_defaultBlur'];
    }
    $ctrl = \data_entry_helper::sensitivity_input($ctrlOptions);
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];

  }

}