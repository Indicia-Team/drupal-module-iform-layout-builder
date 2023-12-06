<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Sample Photos' block.
 *
 * @Block(
 *   id = "data_entry_sample_photos_block",
 *   admin_label = @Translation("Indicia data entry sample photos block"),
 *   layout_builder_label = @Translation("Sample photos input"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntrySamplePhotosBlock extends IndiciaPhotoBlockBase {

  protected function getControlConfigFields() {
    return [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => $this->t('Photos of overall site'),
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
    $ctrl = $this->getControl('sample_medium');
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

}