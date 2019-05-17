<?php

/**
 * @file
 * Contains \Drupal\string_mobile_api\Form\STRINGMobileApiVariableDelete.
 */

namespace Drupal\string_mobile_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class STRINGMobileApiVariableDelete extends FormBase implements ContainerInjectionInterface {

  protected $configuration;
  
  protected $variable;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->configuration = $config_factory->getEditable('string_mobile_api.variables');
    $key = $request_stack->getCurrentRequest()->get('variable_name');
    if ($var = $this->configuration->get('variables.' . $key)) {
      $this->variable = $var;
    } else {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
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
    return 'string_mobile_api_variable_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // dpm($this->variable);
    $form['question'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Are you sure want to delete variable ":variable_name"?', [':variable_name' => $this->variable['name']])
    ];
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete variable'),
      '#button_type' => 'primary',
    );
    $form['actions']['cancel'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#submit' => array('::cancelFormSubmit'),
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
    $this->configuration->clear('variables.' . $this->variable['name']);
    $this->configuration->set('timestamp', time());
    $this->configuration->save();
    $form_state->setRedirect('string_mobile_api.variables.collection');
    drupal_set_message($this->t('Variable ":variable_name" deleted.', [':variable_name' => $this->variable['name']]));
  }

  /**
   * {@inheritdoc}
   */
  public function cancelFormSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('string_mobile_api.variables.collection');
  }
}
