<?php

namespace Drupal\string_mobile_api\UIItemTypes;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeInterface;

class STRINGItemTypeSystem implements STRINGMobileApiTypeInterface {

  const TYPE_ID = 'ui_type_system';
  
  public function typeID() {
    return self::TYPE_ID;
  }

  public function typeLabel() {
    return 'System code';
  }

  public function isParent() {
    return false;
  }

  public function contentSelector() {
    return array(
      '#type' => 'textfield',
      '#title' => t('ID'),
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
  }

}
