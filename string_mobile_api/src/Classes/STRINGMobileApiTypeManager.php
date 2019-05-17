<?php

namespace Drupal\string_mobile_api\Classes;

class STRINGMobileApiTypeManager {
  
  protected $itemTypes = array();

  public function addItemType(STRINGMobileApiTypeInterface $type) {
    $this->itemTypes[$type->typeID()] = $type;
    return $this;
  }

  public function getTypesOptions() {
    $result = [];
    foreach ($this->itemTypes as $id => $type) {
      $result[$id] = $type->typeLabel();
    }
    return $result;
  }

  public function getType($typeID) {
    if (!empty($typeID)) {
      return $this->itemTypes[$typeID];
    }

    return false;
  }

}
