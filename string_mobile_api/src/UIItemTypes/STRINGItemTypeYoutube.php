<?php

namespace Drupal\string_mobile_api\UIItemTypes;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeInterface;

class STRINGItemTypeYoutube implements STRINGMobileApiTypeInterface {
  
  const TYPE_ID = 'ui_type_youtube';
  
  public function typeID() {
    return self::TYPE_ID;
  }

  public function typeLabel() {
    return 'YouTube';
  }

  public function isParent() {
    return false;
  }


  public function contentSelector() {
    return array(
      '#type' => 'textfield',
      '#title' => t('YouTube video ID'),
      '#required' => TRUE,
    );
  }

  public function getContentFromFormInput($value) {
    return $value;
  }

  public function contentLoad($content) {
    return $content;
  }

  public function contentPreview($content) {
    return $content;
  }

}
