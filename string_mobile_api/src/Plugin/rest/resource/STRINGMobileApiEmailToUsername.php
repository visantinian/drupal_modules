<?php

namespace Drupal\string_mobile_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Drupal\user\Entity\User;
use \Drupal\user\UserAuth;


/**
 *
 * @RestResource(
 *   id = "string_mobile_api_auth_email_to_username",
 *   label = @Translation("string: User email to username"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/auth/email_to_username",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/auth/email_to_username"
 *   }
 * )
 */
class STRINGMobileApiEmailToUsername extends ResourceBase {

  public function post($data) {
    $email = trim($data['email']);
    if (empty($email)) {
      throw new BadRequestHttpException(t('Email is required'));
    }

    if (!\Drupal::service('email.validator')->isValid($email)) {
      throw new BadRequestHttpException(t('The email address %mail is not valid.', array('%mail' => $email)));
    }

    if ($user = user_load_by_mail($email)) {
      $response_data['email'] = $email;
      $response_data['username'] = $user->getAccountName();

      $build = array(
        '#cache' => array(
          'contexts' => ['url.query_args'],
          'max-age' => 0,
        ),
      );
  
      $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
      $response = new ResourceResponse(['results' => 1, 'data' => $response_data]);
      $response->addCacheableDependency($cache_metadata);
  
      return $response;
    } else {
      throw new NotFoundHttpException(t('Account not found'));
    }
  }
}
