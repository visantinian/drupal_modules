<?php

namespace Drupal\string_mobile_api\UIItemTypes;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeInterface;
use Drupal\Core\Url;

class STRINGItemTypeNode implements STRINGMobileApiTypeInterface {

  const TYPE_ID = 'ui_type_node';

  public function typeID() {
    return self::TYPE_ID;
  }

  public function typeLabel() {
    return 'Content (node)';
  }

  public function isParent() {
    return false;
  }

  public function contentSelector() {

    return array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => t('Content'),
      '#selection_handler' => 'default',
      '#selection_settings' => array(
        'target_bundles' => array('article', 'page','event_cards'),
      ),
      '#required' => TRUE,
    );
  }

  public function contentLoad($content) {
    return node_load($content);
  }

  public function getContentFromFormInput($value) {
    return $value;
  }

  public function contentPreview($content) {
    $node = node_load($content);
    if (isset($node)) {
      $url = Url::fromRoute('entity.node.canonical', ['node' => $content]);
      return \Drupal::l($node->getTitle(), $url);
    } else {
      return "Error loading content ID:" . $content;
    }
  }
}
