<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Map' block for data entry.
 *
 * @Block(
 *   id = "data_entry_map_block",
 *   admin_label = @Translation("Indicia data entry map block"),
 *   layout_builder_label = @Translation("Map for locating record"),
 *   category = @Translation("Indicia form control")
 * )
 */
class DataEntryMapBlock extends IndiciaControlBlockBase {

  protected function getControlConfigFields() {
    $config = \Drupal::config('iform.settings');
    $layers = [
      'osm' => 'OpenStreetMap',
      'otm' => 'OpenTopoMap',
    ];
    $layerDescription = 'Choose the layers that you would like available on the layer selection tool.';
    if (!empty($config->get('google_api_key'))) {
      $layers += [
        'google_streets' => 'Google Streets',
        'google_satellite' => 'Google Satellite',
        'google_hybrid' => 'Google Hybrid',
        'google_physical' => 'Google Physical',
        'dynamicOSMGoogleSat' => 'Dynamic (OpenStreetMap > Google Satellite)',
      ];
    }
    if (!empty($config->get('os_api_key'))) {
      $layers += [
        'os_outdoor' => 'Ordnance Survey Outdoor',
        'os_road' => 'Ordnance Survey Road',
        'os_light' => 'Ordnance Survey Light',
        'os_night' => 'Ordnance Survey Night',
        'os_leisure' => 'Ordnance Survey Leisure',
      ];
    }
    if (!empty($config->get('google_api_key')) && !empty($config->get('os_api_key'))) {
      $layers['dynamicOSGoogleSat'] = 'Dynamic (OpenStreetMap > Ordnance Survey Leisure > Google Satellite)';
    }
    else {
      $layerDescription .= ' The site administrator can enable additional layers by adding API keys on the IForm module settings page.';
    }
    asort($layers);
    return [
      'customLocation' => [
        '#title' => 'Customise the initial map location',
        '#type' => 'checkbox',
        '#description' => 'Tick to set a specific map location for this form. If not ticked then the default from ' .
          'the Indicia settings page will be used.',
        '#attributes' => [
          'id' => 'option_custom_location',
        ],
      ],
      'initialLat' => [
        '#title' => 'Initial latitude',
        '#type' => 'number',
        '#step' => 'any',
        '#description' => 'Number of degrees North, or negative for South.',
        '#states' => [
          // Show this control only if the option 'Customise the initial map location' is checked above.
          'visible' => [
            ':input[id="option_custom_location"]' => ['checked' => TRUE],
          ],
        ],
        '#default_value' => round($config->get('map_centroid_lat'), 5),
      ],
      'initialLon' => [
        '#title' => 'Initial longitude',
        '#type' => 'number',
        '#step' => 'any',
        '#description' => 'Number of degrees East, or negative for West.',
        '#states' => [
          // Show this control only if the option 'Customise the initial map location' is checked above.
          'visible' => [
            ':input[id="option_custom_location"]' => ['checked' => TRUE],
          ],
        ],
        '#default_value' => round($config->get('map_centroid_long'), 5),
      ],
      'initialZoom' => [
        '#title' => 'Initial zoom',
        '#type' => 'number',
        '#min' => 1,
        '#max' => 18,
        '#step' => 1,
        '#description' => 'Zoom level between 1 and 18.',
        '#states' => [
          // Show this control only if the option 'Customise the initial map location' is checked above.
          'visible' => [
            ':input[id="option_custom_location"]' => ['checked' => TRUE],
          ],
        ],
        '#default_value' => $config->get('map_zoom'),
      ],
      'customLayers' => [
        '#title' => 'Customise the available map layers',
        '#type' => 'checkbox',
        '#description' => 'Tick to choose the layers available on the map, otherwise the site-wide defaults will be used.',
        '#attributes' => [
          'id' => 'option_custom_layers',
        ],
      ],
      'defaultLayer' => [
        '#title' => 'Default map layer',
        '#description' => 'Which layer should appear when the map is used for the first time?',
        '#type' => 'select',
        '#options' => $layers,
        '#empty_option' => '-Not specified-',
        '#states' => [
          // Show this control only if the option 'Customise the available map layers' is checked above.
          'visible' => [
            ':input[id="option_custom_layers"]' => ['checked' => TRUE],
          ],
        ],
      ],
      'layers' => [
        '#title' => 'Available map layers',
        '#type' => 'checkboxes',
        '#options' => $layers,
        '#description' => $layerDescription,
        '#default_value' => ['osm'],
        '#states' => [
          // Show this control only if the option 'Customise the available map layers' is checked above.
          'visible' => [
            ':input[id="option_custom_layers"]' => ['checked' => TRUE],
          ],
        ],
      ],
      'graticule' => [
        '#title' => 'Show graticule overlay',
        '#type' => 'checkbox',
        '#description' => 'Show a grid square overlay.',
      ],
      'statusBar' => [
        '#title' => 'Show map status bar',
        '#type' => 'checkbox',
        '#description' => 'Show a status bar with help for picking a grid reference.',
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
    iform_load_helpers(['map_helper']);
    $connection = iform_get_connection_details();
    $readAuth = \map_helper::get_read_auth($connection['website_id'], $connection['password']);
    $blockConfig = $this->getConfiguration();
    $config = \Drupal::config('iform.settings');
    $presetLayers = [];
    if (isset($blockConfig['option_customLayers']) && $blockConfig['option_customLayers'] === 1) {
      $presetLayers = $blockConfig['option_layers'];
    }
    if (empty($presetLayers)) {
      if (!empty($config->get('google_api_key'))) {
        $presetLayers[] = 'google_satellite';
        $presetLayers[] = 'google_streets';
      }
      $presetLayers[] = 'osm';
      sort($presetLayers);
    }
    else {
      // Convert block config format to simple array of layers.
      $presetLayers = array_keys(array_filter($presetLayers, function ($v) {
        return $v !== 0;
      }));
    }
    $ctrlOptions = [
      'readAuth' => $readAuth,
      'presetLayers' => $presetLayers,
      'editLayer' => TRUE,
      'layers' => [],
      'initial_lat' => $config->get('map_centroid_lat'),
      'initial_long' => $config->get('map_centroid_long'),
      'initial_zoom' => $config->get('map_zoom'),
      'width' => '100%',
      'height' => '500',
      'standardControls' => ['layerSwitcher', 'panZoomBar', 'fullscreen'],
      'rememberPos' => FALSE,
    ];
    if (isset($blockConfig['option_graticule']) && $blockConfig['option_graticule'] === 1) {
      $ctrlOptions[] = 'graticule';
    }
    if (isset($blockConfig['option_statusBar']) && $blockConfig['option_statusBar'] === 1) {
      $ctrlOptions['gridRefHint'] = TRUE;
    }
    if (isset($blockConfig['option_customLocation']) && $blockConfig['option_customLocation'] === 1) {
      if (!empty($blockConfig['option_initialLat'])) {
        $ctrlOptions['initial_lat'] = $blockConfig['option_initialLat'];
      }
      if (!empty($blockConfig['option_initialLon'])) {
        $ctrlOptions['initial_long'] = $blockConfig['option_initialLon'];
      }
      if (!empty($blockConfig['option_initialZoom'])) {
        $ctrlOptions['initial_zoom'] = $blockConfig['option_initialZoom'];
      }
    }
    try {
      $ctrl = \map_helper::map_panel($ctrlOptions);
    }
    catch (\Exception $e) {
      $ctrl = '<div class="alert alert-warning">Invalid control: ' . $e->getMessage() . '</div>';
    }
    return [
      '#markup' => new FormattableMarkup($ctrl, []),
      '#attached' => [
        'library' => [
          'iform_layout_builder/block.data_entry.map',
        ],
      ],
      '#cache' => [
        // No cache please.
        'max-age' => 0,
      ],
    ];

  }

}