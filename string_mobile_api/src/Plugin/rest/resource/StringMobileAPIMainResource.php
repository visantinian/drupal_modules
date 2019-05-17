<?php

namespace Drupal\string_mobile_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for database watchdog log entries.
 *
 * @RestResource(
 *   id = "string_mobile_api_main",
 *   label = @Translation("string: Main app structure"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/structure",
 *      
 *   }
 * )
 */
class StringMobileAPIMainResource extends ResourceBase {

  /**
   * Responds to GET requests.
   *
   * Returns a watchdog log entry for the specified ID.
   *
   * @param int $id
   *   The ID of the watchdog log entry.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the log entry.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the log entry was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when no log entry was provided.
   */
  public function get() {

    $cs = \Drupal::request()->query->get('_cs');

    return $this->mainStructure($cs);
  }

  protected function mainStructure($cs) {
    $items = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->loadMultiple();

    $results = [];
    foreach ($items as $id => $item) {
      if ($item->isEnabled()) {
        $results[$id]['id'] = $id;
        $results[$id]['uuid'] = $item->uuid();
        $results[$id]['label'] = $item->label();
        $results[$id]['subtitle'] = empty($item->getSubtitle()) ? '' : $item->getSubtitle();
        $results[$id]['order'] = $item->getOrder();
        $results[$id]['parent'] = empty($item->getParent()) ? '' : $item->getParent();
        $results[$id]['type'] = $item->getType();
        $results[$id]['content'] = str_replace(PHP_EOL, '', $item->getContent());
        $results[$id]['image'] = $item->getImage();
      }
    }

    $type = \Drupal::request()->query->get('type');
    if (empty($type)) {
      $type = 'flat';
    }
    if ($type == 'tree') {
      $tmp = [];
      foreach($results as $result) {
        $result['children'] = [];
        $tmp[$result['parent']][] = $result;
      }
      $tree = $this->createTree($tmp, $tmp[0]);
      $results = $tree;
    }

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $checksum = md5(json_encode($results));

    if (!empty($cs) && $cs == $checksum) {
      $results = [];
    }

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['checksum' => $checksum, 'results' => 1, 'data' => $results]);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  protected function createTree(&$list, $parent){
      $tree = array();
      foreach ($parent as $k=>$l){
          if(isset($list[$l['id']])){
              $l['children'] = $this->createTree($list, $list[$l['id']]);
          }
          $tree[] = $l;
      } 
      return $tree;
  }

}
