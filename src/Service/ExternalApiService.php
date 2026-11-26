<?php

namespace Drupal\auth_dashboard\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Retrieves data from public REST and SOAP demonstration services.
 */
class ExternalApiService {

  /**
   * Constructs an ExternalApiService.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    LoggerChannelFactoryInterface $logger_factory,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->logger = $logger_factory->get('auth_dashboard');
  }

  protected LoggerInterface $logger;

  /**
   * Retrieves a JSON API response.
   */
  public function getJsonPlaceholderUser(): array|NULL {
    try {
      $endpoint = $this->configFactory->get('auth_dashboard.settings')->get('json_endpoint');
      $response = $this->httpClient->request('GET', $endpoint, [
        'timeout' => 10,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      return is_array($data) ? $data : NULL;
    }
    catch (\Throwable $exception) {
      $this->logger->error('REST API request failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Calls the Country Information SOAP service and parses the XML response.
   */
  public function getCountries(): array {
    $soap_body = <<<'XML'
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ListOfCountryNamesByName xmlns="http://www.oorsprong.org/websamples.countryinfo">
    </ListOfCountryNamesByName>
  </soap:Body>
</soap:Envelope>
XML;

    try {
      $response = $this->httpClient->request(
        'POST',
        $this->configFactory->get('auth_dashboard.settings')->get('soap_endpoint'),
        [
          'timeout' => 15,
          'headers' => [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'http://www.oorsprong.org/websamples.countryinfo/ListOfCountryNamesByName',
            'Accept' => 'application/xml, text/xml',
          ],
          'body' => $soap_body,
        ],
      );

      $dom = new \DOMDocument();
      if (!$dom->loadXML((string) $response->getBody())) {
        return [];
      }
      $xpath = new \DOMXPath($dom);
      $countries = [];
      $nodes = $xpath->query('//*[local-name()="tCountryCodeAndName"]');
      if ($nodes) {
        foreach ($nodes as $node) {
          $name_nodes = $xpath->query('./*[local-name()="sName"]', $node);
          $iso_nodes = $xpath->query('./*[local-name()="sISOCode"]', $node);
          $name = $name_nodes && $name_nodes->length ? trim($name_nodes->item(0)->textContent) : '';
          $iso_code = $iso_nodes && $iso_nodes->length ? trim($iso_nodes->item(0)->textContent) : '';
          $countries[] = [
            'name' => $name,
            'iso_code' => $iso_code,
          ];
        }
      }
      return $countries;
    }
    catch (\Throwable $exception) {
      $this->logger->error('SOAP API request failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return [];
    }
  }

}

