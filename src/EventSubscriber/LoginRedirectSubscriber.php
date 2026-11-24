<?php

namespace Drupal\auth_dashboard\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends authenticated Auth0 users to their role dashboard.
 */
class LoginRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a LoginRedirectSubscriber.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', 10],
    ];
  }

  /**
   * Redirects successful Generic OIDC callbacks after role mapping is complete.
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || $request->attributes->get('_route') !== 'openid_connect.redirect_controller_redirect') {
      return;
    }

    $uid = $this->currentUser->id();
    if (!$uid) {
      return;
    }

    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account) {
      return;
    }

    $dashboard_roles = array_values(array_intersect(['member', 'exhibitor'], $account->getRoles()));
    if (count($dashboard_roles) !== 1) {
      return;
    }

    $route = $dashboard_roles[0] === 'member'
      ? 'auth_dashboard.member_dashboard'
      : 'auth_dashboard.exhibitor_dashboard';
    $event->setResponse(new LocalRedirectResponse(
      $this->urlGenerator->generateFromRoute($route),
    ));
  }

}
