<?php

declare(strict_types=1);

namespace SimpleSAML\Module\casserver\Controller\Traits;

use SimpleSAML\Module\casserver\Cas\ServiceValidator;
use SimpleSAML\Module\casserver\Cas\TicketValidator;
use Symfony\Component\HttpFoundation\Request;

trait UrlTrait
{
  /**
   * @deprecated
   * @see ServiceValidator
   * @param string $service
   * @param array $legal_service_urls
   * @return bool
   */
  public function checkServiceURL(string $service, array $legal_service_urls): bool
  {
    //delegate to ServiceValidator until all references to this can be cleaned up
    $config = Configuration::loadFromArray(['legal_service_urls' => $legal_service_urls]);
    $serviceValidator = new ServiceValidator($config);
    return $serviceValidator->checkServiceURL($service) !== null;
  }


  /**
   * @param string $parameter
   * @return string
   */
  public function sanitize(string $parameter): string
  {
    return TicketValidator::sanitize($parameter);
  }


  /**
   * Parse the query Parameters from $_GET global and return them in an array.
   *
   * @param   array|null  $sessionTicket
   * @param   Request     $request
   *
   * @return array
   */
  public function parseQueryParameters(?array $sessionTicket, Request $request): array
  {
    $forceAuthn = isset($_GET['renew']) && $_GET['renew'];
    $sessionRenewId = $sessionTicket ? $sessionTicket['renewId'] : null;

    $query = [];

    if ($sessionRenewId && $forceAuthn) {
      $query['renewId'] = $sessionRenewId;
    }

    if (isset($_REQUEST['service'])) {
      $query['service'] = $_REQUEST['service'];
    }

    if (isset($_REQUEST['TARGET'])) {
      $query['TARGET'] = $_REQUEST['TARGET'];
    }

    if (isset($_REQUEST['method'])) {
      $query['method'] = $_REQUEST['method'];
    }

    if (isset($_REQUEST['renew'])) {
      $query['renew'] = $_REQUEST['renew'];
    }

    if (isset($_REQUEST['gateway'])) {
      $query['gateway'] = $_REQUEST['gateway'];
    }

    if (\array_key_exists('language', $_GET)) {
      $query['language'] = \is_string($_GET['language']) ? $_GET['language'] : null;
    }

    if (isset($_REQUEST['debugMode'])) {
      $query['debugMode'] = $_REQUEST['debugMode'];
    }

    return $query;
  }

  /**
   * @param   Request  $request
   *
   * @return array
   */
  public function getRequestParams(Request $request): array
  {
    $params = [];
    if ($request->isMethod('GET')) {
      $params = $request->query->all();
    } elseif ($request->isMethod('POST')) {
      $params = $request->request->all();
    }

    return $params;
  }
}