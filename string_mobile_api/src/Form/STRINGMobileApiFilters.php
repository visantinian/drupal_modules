<?php

/**
 * @file
 * Contains \Drupal\string_mobile_api\Form\STRINGMobileApiFilters.
 */

namespace Drupal\string_mobile_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

  // Config form is a classic form like any other : see form generators for more information.

class STRINGMobileApiFilters extends ConfigFormBase {

  /*
  **
  * Returns a unique string identifying the form.
  *
  * @return string
  *   The unique string identifying the form.
  */
  public function getFormId() {
    return 'string_mobile_api_filters_form';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['string_mobile_api.filters', 'string_mobile_api.settings'];
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
    $config = $this->config('string_mobile_api.filters');

    $entityTypes = _string_mobile_api_entity_types();

    foreach ($entityTypes as $entityType => $entityTypeName) {
      $form[$entityType] = [
        '#type' => 'details',
        '#title' => t($entityTypeName),
        '#description' => '<p>' . t("All %entityTypeName entities that match any restriction rule will be excluded from Mobile API response.", ['%entityTypeName' => $entityTypeName]). '</p>',
      ];

      $params = _string_mobile_api_entity_restriction_params($entityType);

      if (empty($params)) {
        $form[$entityType]['#description'] = '<p>' . t("No filters available") . '</p>';
      } else {
        $setings = $config->get($entityType);
        foreach ($params as $key => $values) {
          $form[$entityType][$key] = [
            '#type' => 'checkboxes',
            '#title' => t('Restrict the next %parameter', ['%parameter' => $values['label']]),
            '#options' => $values['options'],
            '#default_value' => $setings[$key]['values'],
          ];
        }
      }
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
    $filtersConfig = $this->config('string_mobile_api.filters');

    $entityTypes = _string_mobile_api_entity_types();
    
    $values = $form_state->getValues();

    foreach ($entityTypes as $entityType => $entityTypeName) {
      if (!empty($values[$entityType])) {
        $configUpdated = false;
        foreach ($values[$entityType] as $parameter => $submitted) {
          $values = array_filter($submitted);
          if ($filtersConfig->get($entityType . '.' . $parameter . '.values') != $values) {
            $filtersConfig->set($entityType . '.' . $parameter . '.values', $values);
            $configUpdated = true;
          }
        }
        if ($configUpdated) {
          $settingsConfig = $this->config('string_mobile_api.settings');
          $settingsConfig->set('view_modes.'. $entityType . '.changed', time());
          $settingsConfig->save();
        }
      }
    }

    $filtersConfig->save();

    drupal_set_message(t('Configuration saved'));
  }
}
