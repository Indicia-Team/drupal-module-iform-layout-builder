<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Spatial ref' block for data entry.
 *
 * @Block(
 *   id = "data_entry_spatial_ref_block",
 *   admin_label = @Translation("Indicia data entry spatial ref input block"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntrySpatialRefBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    $mapSystems = $this->getAvailableMapSystems();
    $fields = [
      'label' => [
        '#description' => 'Label shown for the form control.',
        '#default_value' => $this->t('Map ref.'),
      ],
      'helpText' => [
        '#title' => 'Help text',
        '#description' => 'Tip shown beneath the control.',
      ],
      'lockable' => [
        '#title' => 'Lock icon',
        '#description' => 'Enable the lock icon so the control value can be re-used on the next form submission.',
        '#type' => 'checkbox',
      ],
    ];
    if (count($mapSystems) > 1) {
      $fields['system'] = [
        '#title' => 'Map grid or coordinate system',
        '#description' => 'Which grid reference or coordinate system should the record locations be input using?',
        '#type' => 'select',
        '#empty_option' => '-Allow user to choose-',
        '#options' => $mapSystems,
      ];
    }
    else {
      // Single system available, or default to WGS84 lat long. No need to show
      // option to user.
      $system = count($mapSystems) === 1 ? array_pop($mapSystems) : 4326;
      $fields['system'] = [
        '#type' => 'hidden',
        '#value' => $system,
      ];
    }
    return $fields;
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
    $mapSystems = $this->getAvailableMapSystems();
    if (!empty($blockConfig['option_system']) && array_key_exists($blockConfig['option_system'], $mapSystems)) {
      // Replace array with single chosen value.
      $mapSystems = [$blockConfig['option_system'] => $mapSystems[$blockConfig['option_system']]];
    }
    $ctrlOptions = array(
      'label' => 'Map reference',
      'systems' => $mapSystems,
    );
    $this->applyBlockConfigToControl($blockConfig, $ctrlOptions);
    try {
      $ctrl = \data_entry_helper::sref_and_system($ctrlOptions);
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