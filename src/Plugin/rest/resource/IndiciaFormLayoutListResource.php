<?php

namespace Drupal\iform_layout_builder\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  private $entityTypeManager;

  private $aliasManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager, AliasManagerInterface $aliasManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
    $this->aliasManager = $aliasManager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('path_alias.manager'),
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of available forms.
   */
  public function get() {
    iform_load_helpers(['report_helper']);
    $data = [];
    $nids = \Drupal::entityQuery('node')
      // accessCheck FALSE - see https://drupal.stackexchange.com/questions/251864/logicexception-the-controller-result-claims-to-be-providing-relevant-cache-meta.
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', 'iform_layout_builder_form')
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    // Maybe a better way to do this by asking IndiciaFormLayoutResource for its path?
    $href = \Drupal::request()->getSchemeAndHttpHost() . $this->routes()->get('indicia_form_layout_list.GET')->getPath();

    $groupPages = $this->getGroupPages();

    foreach ($nodes as $node) {
      $nid = $node->id();
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $alias = ltrim($this->aliasManager->getAliasByPath("/node/$nid"), '/');
      if (empty($node->field_available_for_groups->value) || array_key_exists($alias, $groupPages)) {
        $formDetail = [
          'id' => $node->id(),
          'title' => $node->getTitle(),
          'survey_id' => $node->field_survey_id->value,
          'type' => $node->field_form_type->value . '_species_form',
          'href' => "$href/" . $node->id(),
        ];
        if (array_key_exists($alias, $groupPages)) {
          $formDetail['groups'] = $groupPages[$alias];
        }
        $description = $node->body->value;
        if ($description) {
          $formDetail['description'] = strip_tags($description);
        }
        $data[] = $formDetail;
      }
    }
    $response = new ResourceResponse($data);
    $response->getCacheableMetadata()->addCacheTags(['node_list:iform_layout_builder_form']);
    return $response;
  }

  private function getGroupPages() {
    $user = $this->entityTypeManager->getStorage('user')->load(\Drupal::currentUser()->id());
    $conn = iform_get_connection_details();
    $readAuth = \report_helper::get_read_auth($conn['website_id'], $conn['password']);
    $pageData = \report_helper::get_report_data([
      'dataSource' => 'library/group_pages/group_pages_for_user',
      'readAuth' => $readAuth,
      'extraParams' => [
        'currentUser' => $user->field_indicia_user_id->value,
      ],
    ]);
    $groupPages = [];
    foreach($pageData as $page) {
      if (!array_key_exists($page['path'], $groupPages)) {
        $groupPages[$page['path']] = [];
      }
      $groupPages[$page['path']][] = $page['group_title'];
    }
    return $groupPages;
  }

}
