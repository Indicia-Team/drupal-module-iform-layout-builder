<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use data_entry_helper;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Occurrence custom attribute' block for data entry.
 *
 * @Block(
 *   id = "data_entry_occurrence_custom_attribute_block",
 *   admin_label = @Translation("Indicia data entry custom occurrence value block"),
 *   category = @Translation("Indicia form control")*
 * )
 */
class DataEntryOccurrenceCustomAttributeBlock extends IndiciaCustomAttributeBlockBase {

  protected function getAttrEntityName() {
    return 'occurrence';
  }

  protected function getControlConfigFields() {
    return parent::getControlConfigFields();
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
    $node = $this->getCurrentNode();
    $blockConfig = $this->getConfiguration();
    if ($node === NULL) {
      $msg = $this->t(
        'Placeholder for the <strong>@label</strong> input control.',
        ['@label' => $blockConfig['option_label'] ?? $blockConfig['option_admin_name']]
      );
    }
    elseif ($node->field_form_type->value !== 'single') {
      $txt = <<<TXT
Placeholder for the <strong>@label</strong> input which will appear as a column in the grid for entering a list of
species. Use the edit button which appears in the top right of this panel when you hover over it to change the column's
settings.
TXT;
      $msg = $this->t($txt, ['@label' => $blockConfig['option_label']]);
    }
    if (isset($msg)) {
      // Not outputting a control.
      global $indicia_templates;
      $msgBox = str_replace('{message}', $msg, $indicia_templates['messageBox']);
      return [
        '#markup' => "<div class=\"iform-layout-builder-block-info\">$msgBox</div>",
      ];
    }
    return [
      '#markup' => new FormattableMarkup($this->getControl($blockConfig), []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];

  }

}