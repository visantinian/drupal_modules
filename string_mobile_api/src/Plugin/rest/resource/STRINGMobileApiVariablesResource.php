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
 *   id = "string_mobile_api_variables",
 *   label = @Translation("string: UI variables"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/variables",
 *   }
 * )
 */
class STRINGMobileApiVariablesResource extends ResourceBase {

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
    // $timestamp = \Drupal::request()->query->get('_ts');
    // if (empty($timestamp)) {
    //   $timestamp = 0;
    // }

    $variables = \Drupal::service('config.factory')->get('string_mobile_api.variables')->get('variables');
    $checksum = md5(json_encode($variables));

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );
    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['checksum' => $checksum, 'results' => 1, 'data' => $variables]);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}
