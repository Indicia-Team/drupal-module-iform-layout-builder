<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Occurrence Comment' block.
 *
 * @Block(
 *   id = "data_entry_occurrence_comment_block",
 *   admin_label = @Translation("Indicia data entry occurrence comment block"),
 *   layout_builder_label = @Translation("Occurrence comment input"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntryOccurrenceCommentBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => 'Record comment',
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
      'fieldname' => 'occurrence:comment',
    ];
    $ctrl = \data_entry_helper::textarea($ctrlOptions);;
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

}