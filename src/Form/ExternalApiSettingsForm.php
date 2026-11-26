<?php

namespace Drupal\auth_dashboard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the external JSON and SOAP API endpoints.
 */
class ExternalApiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'auth_dashboard_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['auth_dashboard.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('auth_dashboard.settings');

    $form['json_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('JSON API endpoint'),
      '#default_value' => $config->get('json_endpoint') ?: 'https://jsonplaceholder.typicode.com/users/1',
      '#required' => TRUE,
    ];
    $form['soap_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('SOAP API endpoint'),
      '#default_value' => $config->get('soap_endpoint') ?: 'https://soap-service-free.mock.beeceptor.com/CountryInfoService.wso',
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('auth_dashboard.settings')
      ->set('json_endpoint', $form_state->getValue('json_endpoint'))
      ->set('soap_endpoint', $form_state->getValue('soap_endpoint'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
