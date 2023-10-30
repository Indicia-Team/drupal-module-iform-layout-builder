<?php

namespace Drupal\iform_layout_builder\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class IndiciaLayoutBuilderBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  protected $routeMatch;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param Drupal\Core\Routing\RouteMatchInterface $route_match
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  protected function getCurrentNode() {
    // If a normal call, the RouteMatch service is the most reliable way.
    $node = $this->routeMatch->getParameter('node');
    if (!$node && !empty($_SERVER['HTTP_REFERER'])) {
      // Use referrer if inside a layout builder AJAX call.
      if (preg_match('/node\/(?P<nid>\d+)\//', $_SERVER['HTTP_REFERER'], $matches)) {
        $nid = $matches['nid'];
        $node = \Drupal\node\Entity\Node::load($nid);
      }
    }
    return $node;
  }

}