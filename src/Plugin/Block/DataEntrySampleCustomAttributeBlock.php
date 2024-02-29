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
      '#attributes' => [
        'id' => 'option_child_sample_attribute',
      ],
    ];
    $r['include_in_child_sample_label'] = [
      '#title' => 'Include in child sample label',
      '#description' => 'For mobile app forms, include the value in the label shown for each child sample.',
      '#type' => 'checkbox',
      '#states' => [
        // Show this control only if the option 'Child sample attribute' is checked above.
        'visible' => [
          ':input[id="option_child_sample_attribute"]' => ['checked' => TRUE],
        ],
      ],
    ];
    // Hide control apart from for multiplace forms.
    $node = $this->getCurrentNode();
    if ($node->field_form_type->value !== 'multiplace') {
      $r['child_sample_attribute']['#access'] = FALSE;
      $r['include_in_child_sample_label']['#access'] = FALSE;
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
    if (empty($blockConfig['option_child_sample_attribute'])) {
      iform_load_helpers(['data_entry_helper']);
      return [
        '#markup' => new FormattableMarkup($this->getControl($blockConfig), []),
        '#cache' => [
          // No cache please.
          'max-age' => 0,
        ],
      ];
    }
    elseif ($this->inPreview) {
      // Child sample attribute on layout builders, so needs a placeholder.
      $msg = $this->t(
        'Placeholder for the <strong>@label</strong> input control which is shown when adding a child (pinpoint) sample using the multiplace species input control.',
        ['@label' => $blockConfig['option_label'] ?? $blockConfig['option_admin_name']]
      );
      global $indicia_templates;
      $msgBox = str_replace('{message}', $msg, $indicia_templates['messageBox']);
      return [
        '#markup' => "<div class=\"iform-layout-builder-block-info\">$msgBox</div>",
      ];
    }
    else {
      // On recording form, child sample control will be output automatically
      // when adding child samples.
      return [];
    }

  }

}
