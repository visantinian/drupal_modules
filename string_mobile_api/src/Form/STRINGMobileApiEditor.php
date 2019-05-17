<?php

/**
 * @file
 * Contains \Drupal\string_mobile_api\Form\STRINGMobileApiEditor.
 */

namespace Drupal\string_mobile_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Render\FormattableMarkup;

class STRINGMobileApiEditor extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'string_mobile_api_ui_editor_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $items = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->loadMultiple();
    usort($items, array($this, 'sortOrder'));

    $form['string_ui_items'] = array(
      '#type' => 'table',
      '#header' => array('label' => t('Label'),'image' => t('Icon'), 'type' => t('Type'), 'content' => t('Content'), 'enabled' => t('Enabled'), 'weight' => t('Weight'), ['data' => t('Operations'),'colspan' => 3]),
      '#empty' => t('There are no items yet. <a href=\'@add-url\'>Add an item</a>.', array(
        '@add-url' => Url::fromRoute('entity.string_mobile_api_item.add_form'),
      )),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'string-ui-item-order-weight',
        ),
        array(
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'string-ui-item-parent',
          'subgroup' => 'string-ui-item-parent',
          'source' => 'string-ui-item-id',
          'hidden' => TRUE,
        ),
      ),
    );

    foreach ($items as $item) {
      $id = $item->id();
      $form['string_ui_items'][$id]['#weight'] = $item->getOrder();
      $form['string_ui_items'][$id]['#attributes']['class'][] = 'draggable';

      $typeManager = \Drupal::service('string_mobile_api.type_manager');
      $type = $typeManager->getType($item->getType());
      if (!$type->isParent()) {
        $form['string_ui_items'][$id]['#attributes']['class'][] = 'tabledrag-leaf';
      } 

      $depth = $item->getParentDepth();
      // $isActive = $item->isEnabled() ? 'active' : 'not active';
      $form['string_ui_items'][$id]['label'] = array(
        '#plain_text' => $item->getLabel(),
        'indent' => $depth > 0 ? ['#theme' => 'indentation', '#size' => $depth] : [],
      );

      if (!empty($item->getImage())) {
        // prevent xss filter
        $form['string_ui_items'][$id]['image'] = [
          '#type' => 'inline_template',
          '#template' => "<img width='60px' height='60px' src='{{ data }}' />",
          '#context' => [
            'data' => $item->getImage(),
          ],
        ];
      } else {
        $form['string_ui_items'][$id]['image'] = array(
          '#plain_text' => '',
        );
  
      }

      

      $form['string_ui_items'][$id]['type'] = array(
        '#plain_text' => $type->typeLabel(),
      );

      $form['string_ui_items'][$id]['content'] = array(
        '#markup' => $type->contentPreview($item->getContent()),
      );

      $form['string_ui_items'][$id]['enabled'] = array(
        '#type' => 'checkbox',
        '#title_display' => 'invisible',
        '#title' => t('Enabled'),
        '#default_value' => $item->getEnabled(),
      );

      $form['string_ui_items'][$id]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $item->getLabel())),
        '#title_display' => 'invisible',
        '#default_value' => $item->getOrder(),
        '#attributes' => array('class' => array('string-ui-item-order-weight')),
      );

      $form['string_ui_items'][$id]['operations'] = array(
        '#type' => 'operations',
        '#links' => array(),
      );

      $form['string_ui_items'][$id]['operations']['#links']['edit'] = array(
        'title' => t('Edit'),
        'url' => Url::fromRoute('entity.string_mobile_api_item.edit_form', array('string_mobile_api_item' => $id)),
      );
      $form['string_ui_items'][$id]['operations']['#links']['delete'] = array(
        'title' => t('Delete'),
        'url' => Url::fromRoute('entity.string_mobile_api_item.delete_form', array('string_mobile_api_item' => $id)),
      );

      $form['string_ui_items'][$id]['id'] = array(
        '#type' => 'hidden',
        '#value' => $id,
        '#attributes' => array('class' => array('string-ui-item-id')),
      );

      $form['string_ui_items'][$id]['parent'] = array(
        '#type' => 'hidden',
        '#title' => t('Parent for @title', array('@title' => $item->getLabel())),
        '#title_display' => 'invisible',
        '#value' => $item->getParent(),
        '#attributes' => array('class' => array('string-ui-item-parent')),
      );

      $form['actions'] = array('#type' => 'actions');
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save changes'),
        '#button_type' => 'primary',
      );
    }

    return $form;
  }

  private function sortOrder($a, $b) {
    return $b->getOrder() < $a->getOrder();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // dpm($form_state->getValue('string_ui_items'));
    $idx = 0;
    foreach ($form_state->getValue('string_ui_items') as $id => $value) {
      $idx++;
      $item = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->load($id);
      $item->set('order', $idx);
      $item->set('parent', $value['parent']);
      $item->set('enabled', $value['enabled']);
      $item->save();
    }
  }
}
