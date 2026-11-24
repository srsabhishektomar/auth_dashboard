<?php

namespace Drupal\auth_dashboard\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms local and Auth0 logout.
 */
class Auth0LogoutConfirmForm extends ConfirmFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $authConfigFactory;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected UrlGeneratorInterface $authUrlGenerator;

  /**
   * Constructs the logout confirmation form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    UrlGeneratorInterface $url_generator,
    TranslationInterface $translation,
  ) {
    $this->authConfigFactory = $config_factory;
    $this->authUrlGenerator = $url_generator;
    $this->setStringTranslation($translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('url_generator'),
      $container->get('string_translation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'auth_dashboard_auth0_logout_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to log out of Drupal and Auth0?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if (!$this->getAuth0LogoutUrl()) {
      $form_state->setErrorByName('confirm', $this->t('The Auth0 OpenID Connect provider must be enabled and configured with an HTTPS authorization endpoint and client ID.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $logout_url = $this->getAuth0LogoutUrl();
    if (!$logout_url) {
      $form_state->setErrorByName('confirm', $this->t('The Auth0 OpenID Connect provider is not configured.'));
      return;
    }

    user_logout();
    $form_state->setResponse(new TrustedRedirectResponse($logout_url->toString()));
  }

  /**
   * Builds the Auth0 logout URL from the configured OIDC provider.
   */
  protected function getAuth0LogoutUrl(): ?Url {
    $provider = $this->authConfigFactory->get('openid_connect.settings.generic');
    $settings = $provider->get('settings') ?? [];
    $endpoint = $settings['authorization_endpoint'] ?? '';
    $client_id = $settings['client_id'] ?? '';
    $parts = parse_url($endpoint);

    if (
      !$provider->get('enabled')
      || $client_id === ''
      || !is_array($parts)
      || ($parts['scheme'] ?? NULL) !== 'https'
      || empty($parts['host'])
    ) {
      return NULL;
    }

    $return_to = $this->authUrlGenerator->generateFromRoute('<front>', [], [
      'absolute' => TRUE,
    ]);

    return Url::fromUri('https://' . $parts['host'] . '/v2/logout', [
      'query' => [
        'client_id' => $client_id,
        'returnTo' => $return_to,
      ],
    ]);
  }

}
