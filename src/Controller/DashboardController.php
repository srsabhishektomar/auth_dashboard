<?php

namespace Drupal\auth_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\auth_dashboard\Service\ExternalApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays role-specific API dashboards.
 */
class DashboardController extends ControllerBase {

  /**
   * Constructs a DashboardController.
   */
  public function __construct(
    protected ExternalApiService $externalApi,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('auth_dashboard.external_api'),
    );
  }

  /**
   * Returns the member REST dashboard.
   */
  public function member(): array {
    $data = $this->externalApi->getJsonPlaceholderUser();

    if (!is_array($data)) {
      return [
        '#theme' => 'item_list',
        '#title' => $this->t('REST API result'),
        '#items' => [$this->t('The REST service could not be retrieved.')],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('REST API result'),
      '#items' => [
        $this->t('Name: @name', ['@name' => $data['name'] ?? '']),
        $this->t('Email: @email', ['@email' => $data['email'] ?? '']),
        $this->t('Company: @company', ['@company' => $data['company']['name'] ?? '']),
      ],
    ];
  }

  /**
   * Returns the exhibitor SOAP dashboard.
   */
  public function exhibitor(): array {
    $countries = $this->externalApi->getCountries();

    $items = [];
    foreach ($countries as $country) {
      $items[] = $this->t('@name (@code)', [
        '@name' => $country['name'],
        '@code' => $country['iso_code'],
      ]);
    }

    if (!$items) {
      $items[] = $this->t('The SOAP service could not be retrieved.');
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('SOAP API result'),
      '#items' => $items,
    ];
  }

}

