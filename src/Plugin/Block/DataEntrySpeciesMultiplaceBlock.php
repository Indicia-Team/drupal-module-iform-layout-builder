<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a 'Multiplace Species Input' block.
 *
 * @Block(
 *   id = "data_entry_species_multiplace_block",
 *   admin_label = @Translation("Indicia data entry species multiplace block"),
 *   layout_builder_label = @Translation("Grid for entering lists species at different places"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntrySpeciesMultiplaceBlock extends IndiciaSpeciesListBlockBase {

  /**
   * Constructor.
   *
   * Unsets the scratchpad list option for prepopulated species lists as it
   * doesn't work for multiplace forms.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match);
    unset($this->listConfigFormControls['speciesListMode']['#options']['scratchpadList']);
  }

  protected function getControlConfigFields() {
    $mapSystems = $this->getAvailableMapSystems();
    return array_merge([
      'label' => [
        '#description' => 'Label shown for the form control.',
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
      ],
      'spatialSystem' => [
        '#title' => 'Grid reference system',
        '#type' => 'select',
        '#description' => 'Grid reference system used when adding a square to the map.',
        '#options' => $mapSystems,
        '#default_value' => array_keys($mapSystems)[0],
        '#required' => TRUE,
      ],
    ], $this->listConfigFormControls);
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
    $ctrlOptions = $this->getSpeciesChecklistControlOptions($blockConfig);
    $ctrlOptions['spatialSystem'] = $blockConfig["option_spatialSystem"];
    // Copy read auth tokens due to inconsistency in way standard species
    // checklist and multiplace version accept readAuth.
    $ctrlOptions['readAuth'] = [
      'auth_token' => $ctrlOptions['extraParams']['auth_token'],
      'nonce' => $ctrlOptions['extraParams']['nonce'],
    ];
    $node = $this->getCurrentNode();
    if (!empty($node->field_child_sample_method_id->value)) {
      $ctrlOptions['sample_method_id'] = $node->field_child_sample_method_id->value;
    }
    try {
      $preloader = $this->getPreloadScratchpadListControl($blockConfig, $ctrlOptions);
      $ctrl = $preloader . \data_entry_helper::multiple_places_species_checklist($ctrlOptions);
    }
    catch (\Exception $e) {
      $ctrl = '<div class="alert alert-warning">Invalid control: ' . $e->getMessage() . '</div>';
    }
    $msgTxt = $this->t('Placeholder for configuration for the multiple places species list control.');
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