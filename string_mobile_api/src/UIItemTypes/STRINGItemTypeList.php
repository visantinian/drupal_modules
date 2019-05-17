<?php

namespace Drupal\string_mobile_api\UIItemTypes;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeInterface;

class STRINGItemTypeList implements STRINGMobileApiTypeInterface {

  const TYPE_ID = 'ui_type_list';
  
  public function typeID() {
    return self::TYPE_ID;
  }

  public function typeLabel() {
    return 'List (menu)';
  }

  public function isParent() {
    return true;
  }

  public function contentSelector() {
    return array(
      '#type' => 'select',
      '#title' => t('Font style'),
      '#options' => array(
        'normal' => t('Normal'),
        'bold' => t('Bold'),
        'italic' => t('Italic'),
        'bolditalic' => t('Bold + Italic')
      ),
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
