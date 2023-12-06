<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Sample Comment' block.
 *
 * @Block(
 *   id = "data_entry_sample_comment_block",
 *   admin_label = @Translation("Indicia data entry sample comment block"),
 *   layout_builder_label = @Translation("Sample comment input"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntrySampleCommentBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => $this->t('Overall comment'),
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
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
      'label' => $blockConfig["option_label"],
      'helpText' => $blockConfig["option_helpText"],
      'fieldname' => 'sample:comment',
    ];
    $ctrl = \data_entry_helper::textarea($ctrlOptions);
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

}