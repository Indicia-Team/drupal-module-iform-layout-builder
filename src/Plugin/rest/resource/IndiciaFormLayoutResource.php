<?php

namespace Drupal\iform_layout_builder\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource for Iform_layout_builder forms.
 *
 * @RestResource(
 *   id = "indicia_form_layout",
 *   label = @Translation("Indicia form layout"),
 *   uri_paths = {
 *     "canonical" = "/iform_layout_builder/indicia_form_layout/{id}"
 *   }
 * )
 */
class IndiciaFormLayoutResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @param int $id
   *   The ID of the node to fetch, or returns a summary of all form nodes if
   *   not specified.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the form object.
   */
  public function get($id = NULL) {
    $node = node::load($id);
    $response = [
      'title' => $node->getTitle(),
      'description' => $node->body->value,
      'survey_id' => $node->field_survey_id->value,
      'type' => $node->field_form_type->value,
      'input_form' => \Drupal::service('path_alias.manager')->getAliasByPath("/node/$id"),
      'form_sections' => [],
    ];
    $sections = $node->get('layout_builder__layout')->getSections();
    foreach ($sections as $section) {
      $components = $section->getComponents();
      $regions = [];
      foreach ($components as $component) {
        $asArray = $component->toArray();
        $blockConfig = $asArray['configuration'];
        $weight = (int) $asArray['weight'];
        if (!isset($regions[$asArray['region']])) {
          $regions[$asArray['region']] = [];
        }
        $blockConfig = array_merge([
          'type' => $blockConfig['id'],
        ], $blockConfig);
        unset($blockConfig['id']);
        unset($blockConfig['context_mapping']);
        unset($blockConfig['provider']);
        unset($blockConfig['label']);
        unset($blockConfig['label_display']);
        unset($blockConfig['option_existing_attributes_website_id']);
        unset($blockConfig['option_create_or_existing']);
        unset($blockConfig['option_lookup_options_terms']);
        if (!isset($blockConfig['option_data_type']) || $blockConfig['option_data_type'] !== 'T') {
          unset($blockConfig['option_text_options_control']);
        }
        if (!isset($blockConfig['option_data_type']) || $blockConfig['option_data_type'] !== 'L') {
          unset($blockConfig['option_lookup_options_terms']);
          unset($blockConfig['option_existing_termlist_id']);
        }
        if (!isset($blockConfig['option_data_type']) || !in_array($blockConfig['option_data_type'], ['I', 'F'])) {
          unset($blockConfig['option_number_options_min']);
          unset($blockConfig['option_number_options_max']);
        }
        if (isset($blockConfig['option_data_type']) && $blockConfig['option_data_type'] === 'L' && !empty($blockConfig['option_existing_termlist_id'])) {
          \iform_load_helpers(['helper_base']);
          $conn = \iform_get_connection_details();
          $readAuth = \helper_base::get_read_auth($conn['website_id'], $conn['password']);
          $terms = \helper_base::get_population_data(array(
            'table' => 'termlists_term',
            'extraParams' => $readAuth + [
              'view' => 'cache',
              'termlist_id' => $blockConfig['option_existing_termlist_id'],
              'orderby' => 'sort_order, term',
              'preferred' => 't',
              'columns' => 'id,term,parent_id,preferred_image_path',
            ],
          ));
          $blockConfig['terms'] = $terms;
        }
        $regions[$asArray['region']][$weight] = $blockConfig;
      }
      $response['form_sections'][] = [
        'layout_type' => $section->getLayoutId(),
        'label' => $section->getLayoutSettings()['label'],
        'components' => $regions,
      ];


    }
    return new ResourceResponse($response);
  }

}