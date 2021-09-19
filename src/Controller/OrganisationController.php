<?php

namespace Drupal\client_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Organisation controller class.
 *
 * @package Drupal\client_registration\Controller
 */
class OrganisationController extends ControllerBase {

  /**
   * Register new employee based on organisation slug.
   *
   * @param string $slug
   *   Slug.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the registration page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function register(string $slug): RedirectResponse {

    $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    // Find the organisation with this slug.
    $result = $term_storage->getQuery()
      ->condition('field_register_slug', $slug)
      ->condition('vid', 'organisations')
      ->execute();
    if (count($result) === 1) {
      $nid = $this->config('linked_content.content.registration_page')
        ->get('content_id');

      return $this->redirect('entity.node.canonical', ['node' => $nid], ['query' => ['slug' => $slug]]);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
