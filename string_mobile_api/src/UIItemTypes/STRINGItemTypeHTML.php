<?php

namespace Drupal\string_mobile_api\UIItemTypes;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeInterface;

class STRINGItemTypeHTML implements STRINGMobileApiTypeInterface {
  
  const TYPE_ID = 'ui_type_html';
  
  public function typeID() {
    return self::TYPE_ID;
  }

  public function typeLabel() {
    return 'HTML';
  }

  public function isParent() {
    return false;
  }

  public function contentSelector() {
    return array(
      '#type' => 'text_format',
      '#title' => t('HTML'),
      '#required' => TRUE,
      '#format' => 'full_html',
      '#allowed_formats' => [
        'full_html'
      ],
    );
  }

  public function getContentFromFormInput($value) {
    return $value['value'];
  }

  public function contentLoad($content) {
    return $content;
  }

  public function contentPreview($content) {
    return $content;
    // return "<a href='$content'>$content</a>";
    // $url = Url::fromUri($content);
    // return \Drupal::l(t($content), $url);
  }

}
