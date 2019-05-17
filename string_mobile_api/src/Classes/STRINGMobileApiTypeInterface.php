<?php

namespace Drupal\string_mobile_api\Classes;

interface STRINGMobileApiTypeInterface {

  public function typeID(); // string

  public function typeLabel(); // string

  public function isParent(); // bool

  public function contentSelector(); // renderable array

  public function contentLoad($content); //

  public function contentPreview($content);

  public function getContentFromFormInput($value);

}
