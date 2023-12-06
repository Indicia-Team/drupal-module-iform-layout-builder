<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Date Picker' block.
 *
 * @Block(
 *   id = "data_entry_date_picker_block",
 *   admin_label = @Translation("Indicia data entry date picker block"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntryDatePickerBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => $this->t('Date'),
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
      ],
      'allowVagueDates' => [
        '#title' => 'Enable entry of vague dates',
        '#type' => 'checkbox',
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
      'fieldname' => 'sample:date',
    ];
    $this->applyBlockConfigToControl($blockConfig, $ctrlOptions);
    try {
      $ctrl = \data_entry_helper::date_picker($ctrlOptions);
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