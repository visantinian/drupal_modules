<?php

/**
 * @file
 * Contains Drupal\string_mobile_api\Controller\STRINGMobileApiItem.
 */

namespace Drupal\string_mobile_api\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Contact.
 */
class STRINGMobileApiItemListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('STRINGMobileApiItem');
    $header['id'] = $this->t('Machine name');
    $header['type'] = $this->t('Type');
    $header['content'] = $this->t('Content');
    $header['parent'] = $this->t('Parent');
    $header['order'] = $this->t('Order');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    $row['id'] = $entity->id();
    $row['type'] = $entity->getType();
    $row['content'] = $entity->getContent();
    $row['parent'] = $entity->getParent();
    $row['order'] = $entity->getOrder();
    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

}
