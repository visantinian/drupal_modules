<?php

namespace Drupal\string_mobile_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

class STRINGMobileApiItemVariablesList extends ControllerBase implements ContainerInjectionInterface {

  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configuration = $config_factory->get('string_mobile_api.variables');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }
  
  public function build() {
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => t('UI Variables'),
      '#rows' => [],
      '#empty' => t('There is no variables yet.'),
    ];

    $variables = $this->configuration->get('variables');
    foreach ($variables as $variable) {
      $build['table']['#rows'][$variable['name']] = $this->buildRow($variable);
    }
    
    // // Only add the pager if a limit is specified.
    // if ($this->limit) {
    //   $build['pager'] = [
    //     '#type' => 'pager',
    //   ];
    // }
    return $build;
  }

  protected function buildHeader() {
    return [t('Name'), t('Value'), t('Description'), t('Operations')];
  }

  protected function buildRow($variable) {
    $operations = [
      '#type' => 'operations'
    ];

    $operations['#links'][] = [
      'type' => 'link',
      'title' => $this->t('Edit'),
      'url' => Url::fromRoute('string_mobile_api.variables.edit')->setRouteParameters(['variable_name' => $variable['name']])
    ];
    $operations['#links'][] = [
      'type' => 'link',
      'title' => $this->t('Delete'),
      'url' => Url::fromRoute('string_mobile_api.variables.delete')->setRouteParameters(['variable_name' => $variable['name']])
    ];

    return [
      'name' => $variable['name'], 
      'value' => $variable['value'], 
      'description' => $variable['description'], 
      'operations' => render($operations)];
  }



  public function toApiArray($item) {
    return $item->toApiArray();
  }
  
}
