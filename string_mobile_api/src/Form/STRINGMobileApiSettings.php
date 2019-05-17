<?php

/**
 * @file
 * Contains \Drupal\string_mobile_api\Form\STRINGMobileApiSettings.
 */

namespace Drupal\string_mobile_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

  // Config form is a classic form like any other : see form generators for more information.

class STRINGMobileApiSettings extends ConfigFormBase {

  /*
  **
  * Returns a unique string identifying the form.
  *
  * @return string
  *   The unique string identifying the form.
  */
  public function getFormId() {
    return 'string_mobile_api_settings';
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
    $config = $this->config('string_mobile_api.settings');

    $entity_types = _string_mobile_api_entity_types();
    foreach ($entity_types as $type_id => $type_title) {
      $view_modes = \Drupal::service('entity_display.repository')->getViewModes($type_id);
      $options = [];
      foreach ($view_modes as $id => $view_mode) {
        $options[$id] = $view_mode['label'];
      }
      $form['view_modes'][$type_id] = array(
        '#type' => 'select',
        '#options' => $options,
        '#title' => $this->t('View mode for %title', ['%title' => $type_title]),
        '#default_value' => !empty($config->get('view_modes.' . $type_id . '.view_mode')) ? $config->get('view_modes.' . $type_id . '.view_mode') : 'full'
      );
    }

    $form['#tree'] = true;
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save settings'),
      '#button_type' => 'primary',
    );
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('string_mobile_api.settings');
    $values = $form_state->getValue('view_modes');
    foreach ($values as $entity_type => $view_mode) {
      if ($config->get('view_modes.' . $entity_type . '.view_mode') != $view_mode) {
        $config->set('view_modes.'. $entity_type . '.view_mode', $view_mode);
        $config->set('view_modes.'. $entity_type . '.changed', time());
      }
    }
    $config->save();

    drupal_set_message(t('Configuration saved'));
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['string_mobile_api.settings'];
  }

}
