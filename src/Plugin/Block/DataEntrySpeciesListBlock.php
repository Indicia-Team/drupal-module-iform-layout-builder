<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Species List' block.
 *
 * @Block(
 *   id = "data_entry_species_list_block",
 *   admin_label = @Translation("Indicia data entry species list block"),
 *   layout_builder_label = @Translation("Grid for entering list of species"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntrySpeciesListBlock extends IndiciaSpeciesListBlockBase {

  protected function getControlConfigFields() {
    return array_merge([
      'label' => [
        '#description' => 'Label shown for the form control.',
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
      ],
    ], $this->listConfigFormControls, [
      'spatialRefPerRow' => [
        '#title' => 'Spatial ref. per row',
        '#description' => 'Tick to allow each species row to optionally have its own unique spatial reference for a precise location.',
        '#type' => 'checkbox',
      ],
    ]);
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
    if (!$node) {
      return [
        '#markup' => new FormattableMarkup('<div>Placeholder for block when not loaded on a node</div>', []),
        '#cache' => [
          // No cache please.
          'max-age' => 0,
        ],
      ];
    }
    iform_load_helpers(['data_entry_helper']);
    $blockConfig = $this->getConfiguration();
    $ctrlOptions = $this->getSpeciesChecklistControlOptions($blockConfig);
    $ctrlOptions['spatialRefPerRow'] = isset($blockConfig["option_spatialRefPerRow"]) && $blockConfig["option_spatialRefPerRow"] === 1;
    $ctrlOptions['subSamplePerRow'] = $ctrlOptions['spatialRefPerRow'];
    $ctrlOptions['speciesControlToUseSubSamples'] = $ctrlOptions['spatialRefPerRow'];
    $ctrlOptions['spatialRefPerRowUseFullscreenMap'] = $ctrlOptions['spatialRefPerRow'];
    // Set occurrence attribute labels from custom attribute block config.
    foreach ($node->get('layout_builder__layout')->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        $asArray = $component->toArray();
        $otherBlockConfig = $asArray['configuration'];
        if ($otherBlockConfig['id'] === 'data_entry_occurrence_custom_attribute_block' && !empty($otherBlockConfig['option_label'])) {
          $ctrlOptions['occAttrOptions'][(string) $otherBlockConfig['option_existing_attribute_id']] = ['label' => $otherBlockConfig['option_label']];
        }
      }
    }
    try {
      $preloader = $this->getPreloadScratchpadListControl($blockConfig, $ctrlOptions);
      $ctrl = $preloader . \data_entry_helper::species_checklist($ctrlOptions);
    }
    catch (\Exception $e) {
      $ctrl = '<div class="alert alert-warning">Invalid control: ' . $e->getMessage() . '</div>';
    }
    $msgTxt = $this->t('Placeholder for configuration for the species list control.');
    $msg = "<div class=\"iform-layout-builder-block-info alert alert-info\">$msgTxt</div>";
    return [
      '#markup' => new FormattableMarkup($msg . $ctrl, []),
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];
  }

}