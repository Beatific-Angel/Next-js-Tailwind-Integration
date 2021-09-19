<?php

namespace Drupal\client_registration\ContainerParameters;

use Drupal\container_parameters\Mapping\Environment\EnvironmentMappingBase;

/**
 * This class maps environment values for the API to the Service Container.
 */
class HttpClientMapping extends EnvironmentMappingBase {

  /**
   * The prefix for the parameter names to add in the Service Container.
   *
   * @var string
   */
  public static $prefix = 'env.client_registration.';

  /**
   * {@inheritdoc}
   */
  public function environmentMapping(): array {
    return [
      'base_uri' => 'API_BASE_URI',
    ];
  }

}
