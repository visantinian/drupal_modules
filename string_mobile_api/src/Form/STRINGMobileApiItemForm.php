<?php

/**
 * @file
 * Contains Drupal\string_mobile_api\Form\STRINGMobileApiItem.
 */

namespace Drupal\string_mobile_api\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\HtmlCommand;

use Drupal\file\Entity\File;

use Drupal\string_mobile_api\Classes\STRINGMobileApiTypeManager;

/**
 * Class STRINGMobileApiItem.
 *
 * @package Drupal\string_mobile_api\Form
 */
class STRINGMobileApiItemForm extends EntityForm {
  
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $string_mobile_api_item = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $string_mobile_api_item->label(),
      '#description' => $this->t("Label for the UI Item."),
      '#required' => TRUE,
    ];
    $form['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subtitle'),
      '#maxlength' => 255,
      '#default_value' => $string_mobile_api_item->getSubtitle(),
      '#description' => $this->t("You can use HTML here."),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $string_mobile_api_item->id(),
      '#machine_name' => [
        'exists' => '\Drupal\string_mobile_api\Entity\stringMobileApiItem::load',
      ],
      '#disabled' => !$string_mobile_api_item->isNew(),
    ];

    $typeManager = \Drupal::service('string_mobile_api.type_manager');
    $options = $typeManager->getTypesOptions();

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $options,
      '#required' => $string_mobile_api_item->isNew(),
      '#default_value' => $string_mobile_api_item->getType(),
      '#ajax' => [
        'callback' => '::contentSelectorCallback',
        'wrapper' => 'content-wrapper',
        'method' => 'replace',
      ],
      '#disabled' => !$string_mobile_api_item->isNew(),
    ];

    if (!empty($string_mobile_api_item->getImage())) {
      // prevent xss filter
      $form['current_image'] = [
        '#type' => 'inline_template',
        '#template' => "<label>" . $this->t('Current icon') . ":</label> <img width='60px' height='60px' src='{{ data }}' />",
        '#context' => [
          'data' => $string_mobile_api_item->getImage(),
        ],
      ];

      $form['current_image_remove'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Remove'),
        '#default_value' => false
      ];
    }

    $form['icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Icon'),
      '#multiple' => false,
      '#upload_location' => 'public://mobile_icons',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png'],
      ]
    ];

    $form['content_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'content-wrapper'],
    ];

    if (!$string_mobile_api_item->isNew()) {
      $ui_type = $string_mobile_api_item->getType();
    } else {
      $ui_type = $form_state->getValue('type');
    }

    if (!empty($ui_type) ) {
      $type = $typeManager->getType($ui_type);
      $form['content_wrapper']['content'] = $type->contentSelector();
      if (!$string_mobile_api_item->isNew()) {
        $form['content_wrapper']['content']['#default_value'] = $type->contentLoad($string_mobile_api_item->getContent());
      }
    }

    return $form;
  }

  public function contentSelectorCallback(array &$form, FormStateInterface $form_state) {
    return $form['content_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $string_mobile_api_item = $this->entity;
    $fid = $form_state->getValue(['icon',0]);
    if (!empty($fid)) {
      $file = File::load($fid);
      $data = "data:" . $file->getMimeType() . ";base64," . base64_encode(file_get_contents($file->getFileUri()));
      $string_mobile_api_item->set('image', $data);
      $file->delete();
    } else if ($form_state->getValue('current_image_remove') == 1) {
      $string_mobile_api_item->set('image', '');
    }

    $typeManager = \Drupal::service('string_mobile_api.type_manager');
    $type = $typeManager->getType($string_mobile_api_item->getType());
    $content = $type->getContentFromFormInput($form_state->getValue('content'));
    $string_mobile_api_item->set('content', $content);

    $status = $string_mobile_api_item->save();
    if ($status) {
      drupal_set_message($this->t('Saved the %label STRINGMobileApiItem.', [
        '%label' => $string_mobile_api_item->label(),
      ]));
    }
    else {
      drupal_set_message($this->t('The %label STRINGMobileApiItem was not saved.', [
        '%label' => $string_mobile_api_item->label(),
      ]));
    }
    $form_state->setRedirectUrl($string_mobile_api_item->urlInfo('collection'));
  }

}
