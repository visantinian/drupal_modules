<?php

/**
 * @file
 * Contains \Drupal\string_mobile_api\Form\STRINGSMobileApiVariableAdd.
 */

namespace Drupal\string_mobile_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class STRINGSMobileApiVariableAdd extends FormBase implements ContainerInjectionInterface {

  protected $configuration;

  protected $variable;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->configuration = $config_factory->getEditable('string_mobile_api.variables');
    $key = $request_stack->getCurrentRequest()->get('variable_name');
    if (isset($key)) {
      if ($var = $this->configuration->get('variables.' . $key)) {
        $this->variable = $var;
      } else {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack')
    );
  }
  
  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'string_mobile_api_variable_add_form';
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
    $form['name'] = array(
      '#type' => 'machine_name',
      '#title' => $this->t('Name'),
      '#default_value' => isset($this->variable['name']) ? $this->variable['name'] : '',
      // '#disabled' => isset($this->variable),
      '#maxlength' => 64,
      '#description' => $this->t('A unique name for this variable. It must only contain lowercase letters, numbers, and underscores.'),
      '#machine_name' => array(
        'exists' => array($this, 'variable_exists'),
      ),
      '#required' => TRUE,
    );

    $form['value'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#maxlength' => 255,
      '#default_value' => isset($this->variable['value']) ? $this->variable['value'] : '',
      '#required' => TRUE,
    );

    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#maxlength' => 800,
      '#default_value' => isset($this->variable['description']) ? $this->variable['description'] : '',
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save variable'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}
    
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = [
      'name' => $form_state->getValue('name'),
      'value' => $form_state->getValue('value'),
      'description' => $form_state->getValue('description'),
    ];

    $this->configuration->set('variables.' . $values['name'], $values);
    $this->configuration->set('timestamp', time());
    $this->configuration->save();
    drupal_set_message($this->t('Variable saved.'));
    $form_state->setRedirect('string_mobile_api.variables.collection');
  }

  public function variable_exists($key) {
    if (empty($this->configuration->get('variables.' . $key))) {
      return false;
    }
    return true;
  }

}
