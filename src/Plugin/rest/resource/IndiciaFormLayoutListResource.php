<?php

namespace Drupal\iform_layout_builder\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource for Iform_layout_builder form lists.
 *
 * @RestResource(
 *   id = "indicia_form_layout_list",
 *   label = @Translation("Indicia form layout list"),
 *   uri_paths = {
 *     "canonical" = "/iform_layout_builder/form_layout"
 *   }
 * )
 */
class IndiciaFormLayoutListResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of available forms.
   */
  public function get() {
    $response = [];
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'iform_layout_builder_form')
      ->execute();
    $nodes = Node::loadMultiple($nids);
    foreach ($nodes as $node) {
      $formDetail = [
        'id' => $node->id(),
        'title' => $node->getTitle(),
        'survey_id' => $node->field_survey_id->value,
        'type' => $node->field_form_type->value . '_species_form',
      ];
      $description = $node->body->value;
      if ($description) {
        $formDetail['description'] = $description;
      }
      $response[] = $formDetail;
    }
    return new ResourceResponse($response);
  }

}