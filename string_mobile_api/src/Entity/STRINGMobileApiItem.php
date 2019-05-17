<?php

/**
 * @file
 * Contains Drupal\string_mobile_api\Entity\STRINGMobileApiItem.
 */

namespace Drupal\string_mobile_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\string_mobile_api\STRINGMobileApiItemInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the Example entity.
 *
 * @ConfigEntityType(
 *   id = "string_mobile_api_item",
 *   label = @Translation("STRINGMobileApiItem"),
 *   handlers = {
 *     "list_builder" = "Drupal\string_mobile_api\Controller\STRINGMobileApiItemListBuilder",
 *     "form" = {
 *       "add" = "Drupal\string_mobile_api\Form\STRINGMobileApiItemForm",
 *       "edit" = "Drupal\string_mobile_api\Form\STRINGMobileApiItemForm",
 *       "delete" = "Drupal\string_mobile_api\Form\STRINGMobileApiItemDeleteForm"
 *     }
 *   },
 *   config_prefix = "string_mobile_api_item",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/string_mobile_api_item/{string_mobile_api_item}",
 *     "delete-form" = "/admin/config/system/string_mobile_api_item/{string_mobile_api_item}/delete",
 *     "collection" = "/admin/config/system/string_mobile_api_item"
 *   }
 * )
 */
class STRINGMobileApiItem extends ConfigEntityBase implements STRINGMobileApiItemInterface {
  /**
   * The STRINGMobileApiItem ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The STRINGMobileApiItem label.
   *
   * @var string
   */
  protected $label;

  /**
   * The STRINGMobileApiItem subtitle.
   *
   * @var string
   */
  protected $subtitle;

  /**
   * The STRINGMobileApiItem order.
   *
   * @var integer
   */
  protected $order;

  /**
   * The STRINGMobileApiItem parent.
   *
   * @var string
   */
  protected $parent;

  /**
   * The STRINGMobileApiItem type.
   *
   * @var string
   */
  protected $type;

  /**
   * The STRINGMobileApiItem content.
   *
   * @var string
   */
  protected $content;

  /**
   * The STRINGMobileApiItem image.
   *
   * @var string
   */
   protected $image;

  /**
   * The STRINGMobileApiItem is enabled.
   *
   * @var string
   */
  protected $enabled;

  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    $items = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->loadMultiple();

    foreach ($entities as $entity) {
      foreach ($items as $item) {
        if ($entity->id() == $item->getParent()) {
          $item->delete();
        }
      }
    }
  }

  public function getId() {
    return $this->id;
  }

  public function getLabel() {
    return $this->label;
  }

  public function getSubtitle() {
    return $this->subtitle;
  }

  public function getOrder() {
    return $this->order;
  }

  public function getParent() {
    return $this->parent;
  }

  public function getType() {
    return $this->type;
  }

  public function getContent() {
    return $this->content;
  }

  public function getImage() {
    return $this->image;
  }

  public function getEnabled() {
    return $this->enabled;
  }

  public function isEnabled() {
    if (!empty($this->parent)) {
      if (!$this->getEnabled()) {
        return false;
      } else {
        $parent = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->load($this->parent);
        return $parent->isEnabled();
      }
    } else {
      return $this->getEnabled();
    }
  }

  public function getParentDepth($depth = 0) {
    if (!empty($this->parent)) {
      $depth++;
      $parent = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->load($this->parent);
      return $parent->getParentDepth($depth);
    } else {
      return $depth;
    }
  }

}
