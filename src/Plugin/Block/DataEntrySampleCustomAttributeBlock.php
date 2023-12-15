<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use data_entry_helper;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Sample custom attribute' block for data entry.
 *
 * @Block(
 *   id = "data_entry_sample_custom_attribute_block",
 *   admin_label = @Translation("Indicia data entry custom sample value block"),
 *   category = @Translation("Indicia form control")*
 * )
 */
class DataEntrySampleCustomAttributeBlock extends IndiciaCustomAttributeBlockBase {

  protected function getAttrEntityName() {
    return 'sample';
  }

  protected function getControlConfigFields() {
    $r = parent::getControlConfigFields();
    $r['child_sample_attribute'] = [
      '#title' => 'Child sample attribute',
      '#description' => 'Show this control for each separate child (pinpoint) sample location.',
      '#type' => 'checkbox',
    ];
    // Hide control apart from for multiplace forms.
    $node = $this->getCurrentNode();
    if ($node->field_form_type->value !== 'multiplace') {
      $r['child_sample_attribute']['#access'] = FALSE;
    }
    return $r;
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
    $blockConfig = $this->getConfiguration();
    iform_load_helpers(['data_entry_helper']);
    return [
      '#markup' => new FormattableMarkup($this->getControl($blockConfig), []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];

  }

}