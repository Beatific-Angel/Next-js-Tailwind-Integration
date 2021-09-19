<?php

namespace Drupal\client_registration\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;

/**
 * The register client class. Handles the communication with the backend API.
 */
class RegisterClient {

  /**
   * The guzzle http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * RegisterClient constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client factory.
   */
  public function __construct(ClientInterface $http_client) {
    $this->client = $http_client;
  }

  /**
   * Executes the call to the endpoint that registers the new client.
   *
   * @param array $form_data
   *   Form data.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response from the API.
   */
  public function post(array $form_data) {
    return $this->client->post('api/user/register', [
      RequestOptions::JSON => $form_data,
    ]);
  }

  /**
   * Executes the call to the endpoint that validates the entered data.
   *
   * @param array $form_data
   *   Form data.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response from the API.
   */
  public function validate(array $form_data) {
    return $this->client->post('api/user/register/validate', [
      RequestOptions::JSON => $form_data,
    ]);
  }

}
