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
 *   id = "string_mobile_api_auth",
 *   label = @Translation("string: User Auth"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/auth",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/auth"
 *   }
 * )
 */
class STRINGMobileAPIAuth extends ResourceBase {

  public function post($data) {
    $email = trim($data['email']);
    $password = ($data['password']);
    if (empty($email)) {
      throw new BadRequestHttpException(t('Email is required'));
    }

    if (!\Drupal::service('email.validator')->isValid($email)) {
      throw new BadRequestHttpException(t('The email address %mail is not valid.', array('%mail' => $email)));
    }

    if (empty($password)) {
      throw new BadRequestHttpException(t('Password is required'));
    }

    if ($user = user_load_by_mail($email)) {
      if (\Drupal::service('password')->check($password, $user->getPassword())) {
        $response_data['uid'] = $user->id();
        $response_data['type'] = array_map(function($a) {return $a['value'];}, $user->get('field_registrant_type')->getValue());
        $response_data['uuid'] = $user->get('uuid')->getValue()[0]['value'];
        $response_data['full_name'] = $user->field_registrant_first_name->value . " " . $user->field_registrant_last_name->value;

        $connection = \Drupal::database();
        $sql = "SELECT DISTINCT entity_id  FROM {votingapi_vote} WHERE user_id = :user";
        $querySpeech = $connection->query($sql, [  ':user' => $user->id() ] )->fetchAll();

        $array = [];
        $i = 0;

        $speechLength = count($querySpeech);

        do {
          $querySpeech = json_decode(json_encode($querySpeech), True);
          $array[] = $querySpeech[$i]['entity_id'];
          if ($array[0] != null) {
            $response_data['voted_entities'] = $array;
          }
          $i++;
        } while ($i < $speechLength);




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
    } else {
      throw new NotFoundHttpException(t('Account not found'));
    }
  }
}
