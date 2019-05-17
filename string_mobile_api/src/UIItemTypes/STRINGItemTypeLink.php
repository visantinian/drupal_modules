<?php

namespace Drupal\string_mobile_api\UIItemTypes;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeInterface;
use Drupal\Core\Url;

class STRINGItemTypeLink implements STRINGMobileApiTypeInterface {
  
  const TYPE_ID = 'ui_type_link';
  
  public function typeID() {
    return self::TYPE_ID;
  }

  public function typeLabel() {
    return 'Link';
  }

  public function isParent() {
    return false;
  }

  public function contentSelector() {
    return array(
      '#type' => 'textfield',
      '#title' => t('URL'),
      '#required' => TRUE,
    );
  }

  public function contentLoad($content) {
    return $content;
  }

  public function getContentFromFormInput($value) {
    return $value;
  }

  public function contentPreview($content) {
    return $content;
    // return "<a href='$content'>$content</a>";
    // $url = Url::fromUri($content);
    // return \Drupal::l(t($content), $url);
  }

}
