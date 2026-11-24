<?php

namespace Drupal\auth_dashboard\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\openid_connect\OpenIDConnectClaims;
use Drupal\openid_connect\Plugin\OpenIDConnectClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Starts the OpenID Connect login flow configured for Auth0.
 */
class Auth0LoginController extends ControllerBase {

  /**
   * Constructs the login controller.
   */
  public function __construct(
    protected ConfigFactoryInterface $authConfigFactory,
    protected OpenIDConnectClientManager $clientManager,
    protected OpenIDConnectClaims $claims,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.openid_connect_client'),
      $container->get('openid_connect.claims'),
    );
  }

  /**
   * Redirects the visitor to Auth0 through the Generic OIDC client.
   */
  public function login(): Response {
    $provider = $this->authConfigFactory->get('openid_connect.settings.generic');
    $settings = $provider->get('settings') ?? [];
    $required_settings = [
      'client_id',
      'client_secret',
      'authorization_endpoint',
      'token_endpoint',
      'userinfo_endpoint',
    ];

    foreach ($required_settings as $key) {
      if (empty($settings[$key])) {
        throw new ServiceUnavailableHttpException(NULL, 'The Auth0 OpenID Connect provider is not fully configured.');
      }
    }

    if (!$provider->get('enabled')) {
      throw new ServiceUnavailableHttpException(NULL, 'The Auth0 OpenID Connect provider is disabled.');
    }

    $client = $this->clientManager->createInstance('generic', $settings);
    // This route is not a suitable post-login destination. The response
    // subscriber sends users with a mapped dashboard role to that dashboard.
    $_SESSION['openid_connect_destination'] = '/';
    $_SESSION['openid_connect_op'] = 'login';

    return $client->authorize($this->claims->getScopes($client));
  }

}
