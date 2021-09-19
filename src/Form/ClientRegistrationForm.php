<?php

namespace Drupal\client_registration\Form;

use Drupal\client_registration\Api\RegisterClient;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Client registration form class.
 */
class ClientRegistrationForm extends FormBase {

  /**
   * The api register client.
   *
   * @var \Drupal\client_registration\Api\RegisterClient
   */
  protected $registerClient;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Client registration form class constructor.
   *
   * @param \Drupal\client_registration\Api\RegisterClient $register_client
   *   The register client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   */
  public function __construct(
    RegisterClient $register_client,
    EntityTypeManagerInterface $entity_type_manager,
    Request $request,
    MessengerInterface $messenger,
    ConfigFactory $config_factory
  ) {
    $this->registerClient = $register_client;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('register_client'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('messenger'),
      $container->get('config.factory'),
    );
  }

  /**
   * Returns the form id.
   *
   * @return string
   *   The form id
   */
  public function getFormId() {
    return 'client_registration_form';
  }

  /**
   * Build the form by defining the form fields.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The form
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form_state->disableCache();

    $form['#attached']['library'][] = 'client_registration/autocomplete_number_trimmer';

    $user_input = $form_state->getUserInput();
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $form['information'] = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Create your account') . '</h2><p>' . $this->t("Create your Fitness Network account here. You'll need this account to view all subscriptions and prices and to subscribe. With this account, you can also view your subscriptions, invoices and edit your profile data.") . '</p>',
    ];

    $form['name'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['m-form__group', 'm-form__group--is-horizontal'],
      ],
    ];

    $form['name']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#description' => $this->t('Enter your first name'),
      '#placeholder' => $this->t('First name'),
      '#required' => TRUE,
      '#maxlength' => 191,
    ];

    $form['name']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#placeholder' => $this->t('Last name'),
      '#description' => $this->t('Enter your last name'),
      '#required' => TRUE,
      '#maxlength' => 191,
    ];

    $form['organisations'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['m-form__group', 'm-form__group--is-horizontal'],
      ],
    ];

    $organisation_terms = NULL;
    $organisation = NULL;
    $organisation_id = NULL;
    if (isset($user_input['organisation_id'])) {
      $organisation_drupal_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($user_input['organisation_id']);
      $organisation = $term_storage->load($organisation_drupal_id);
      $organisation_id = $organisation->field_organisation_id->value;
    }
    else {
      $organisation_id = NULL;
    }

    // It is possible to register by organisation slug, check for this case.
    if (!is_null($this->request->get('slug'))) {
      $slug = $this->request->get('slug');
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

      // Find the organisation with this slug.
      $result = $term_storage->getQuery()
        ->condition('field_register_slug', $slug)
        ->condition('vid', 'organisations')
        ->execute();
      if (count($result) === 1) {
        $organisation_terms = $result;
        $organisation = $term_storage->load(reset($result));
        $organisation_id = $organisation->field_organisation_id->value;
      }
    }

    if (is_null($organisation_terms)) {
      $organisation_terms = $term_storage->getQuery()
        ->notExists('field_hide_in_dropdown')
        ->condition('vid', 'organisations')
        ->sort('name')
        ->execute();
    }

    $organisations = [];
    /** @var \Drupal\taxonomy\Entity\Term $organisation */
    foreach ($organisation_terms as $organisation_term_id) {
      $organisation_term = $term_storage->load($organisation_term_id);
      $organisations[$organisation_term->field_organisation_id->value] =
        $organisation_term->getName();
    }

    $form['organisations']['organisation_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Organisation'),
      '#target_type' => 'taxonomy_term',
      '#description' => $this->t('Select your organisation'),
      '#required' => count($organisations) > 1,
      '#disabled' => count($organisations) === 1,
      '#default_value' => count($organisations) === 1 ? ($organisation ?? NULL) : NULL,
      '#ajax' => [
        'callback' => [$this, 'updateForm'],
        'event' => 'change',
        'effect' => 'fade',
      ],
    ];

    $form['organisations']['organisation_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organisation ID'),
      '#placeholder' => $this->t('Organisation ID'),
      '#description' => $this->t('Type the ID of your organisation'),
    ];

    $class_hidden_department_id = is_null($organisation_id) ? ' u-visually-hidden' : '';
    $class_hidden_registration_code = ' u-visually-hidden';
    $registration_code_required = FALSE;
    if (!is_null($organisation)) {
      if ($organisation->field_require_registration_code->value == '1') {
        $class_hidden_registration_code = '';
        $registration_code_required = TRUE;
      }
    }

    $form['registration_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your employee number'),
      '#placeholder' => $this->t('Employee number'),
      '#description' => $this->t('Type your employee number'),
      '#prefix' => '<div id="edit-registration-code-wrapper" class="m-form__wrapper' . $class_hidden_registration_code . '">',
      '#suffix' => '</div>',
      '#required' => $registration_code_required,
    ];

    $form['department_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Department'),
      '#description' => $this->t('Select your department'),
      '#prefix' => '<div id="edit-department-wrapper" class="m-form__wrapper' . $class_hidden_department_id . '">',
      '#suffix' => '</div>',
      '#required' => !is_null($organisation_id),
    ];

    $department_options = [];
    if ($organisation_id) {
      $result = $term_storage->getQuery()
        ->condition('field_organisation_id', $organisation_id)
        ->execute();
      $organisation = $term_storage->load(reset($result));
      $departments = $organisation->get('field_departments')->getValue();
      foreach ($departments as $department_entity) {
        $department = $term_storage->load($department_entity['target_id']);
        $department_options[$department->get('field_department_id')->getString()] = $department->getName();
      }
      if (count($departments) === 1) {
        $department = $term_storage->load($departments[0]['target_id']);
        $form['department_id']['#default_value'] = $department->get('field_department_id')->getString();
      }
    }
    $form['department_id']['#options'] = $department_options;
    if (isset($user_input['department_id']) &&
      !array_key_exists($user_input['department_id'], $department_options)) {
      $form['department_id']['#value'] = '';
    }

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#placeholder' => $this->t('Email address'),
      '#description' => $this->t('Type your email address'),
      '#required' => TRUE,
    ];

    $form['password_confirmation'] = [
      '#type' => 'password_confirm',
      '#placeholder' => $this->t('Password'),
      '#description' => $this->t('Type your password again'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create account'),
      '#attributes' => [
        'class' => ['m-form__actions m-form__actions--single'],
      ],
    ];

    return $form;
  }

  /**
   * Validates the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getUserInput()['organisation_id'] ? str_getcsv($form_state->getUserInput()['organisation_id'], ',', '"') : [];
    if (count($values) > 1) {
      $form_state->setErrorByName('organisation_id', $this->t('Only one value is allowed in this field.'));
    }

    try {
      $this->registerClient->validate($this->getCleanValues($form_state));
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      if ($response->getStatusCode() === 422) {
        // Validation errors.
        $result = Json::decode($response->getBody());
        foreach ($result['errors'] as $field_name => $errors) {
          foreach ($errors as $error) {
            $form_state->setErrorByName($field_name, $error);
          }
        }
      }
    }
  }

  /**
   * Submits the data in the form to the API.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submit the values to the API.
    try {
      $this->registerClient->post($this->getCleanValues($form_state));
    }
    catch (ClientException $e) {
      $response = new RedirectResponse($this->request->getRequestUri());
      $response->send();

      $message = 'Something went wrong. Please try again. Errorcode: ' .
        $e->getResponse()->getStatusCode();
      $this->messenger->addError($message);

      $result = Json::decode($e->getResponse()->getBody());
      foreach ($result['errors'] as $errors) {
        foreach ($errors as $error) {
          $this->messenger->addError($error);
        }
      }

      return;
    }
    $nid = $this->configFactory
      ->get('linked_content.content.registration_thank_you_page')
      ->get('content_id');
    if (is_null($nid)) {
      $url = Url::fromRoute('<front>');
    }
    else {
      $url = Url::fromRoute('entity.node.canonical', ['node' => $nid]);
    }
    $form_state->setRedirectUrl($url);
  }

  /**
   * Sets the password parameter to something usable in the backend.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Returns the new values array
   */
  private function getCleanValues(FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $values['password'] = $values['password_confirmation'];

    // Convert the drupal id of the organisation to the backend id.
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $organisation = $term_storage->load($values['organisation_id']);
    $values['organisation_id'] = $organisation->field_organisation_id->value;
    return $values;
  }

  /**
   * Add form element select for types.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();

    // ValCommand does not exist, so we can use InvokeCommand.
    $ajax_response->addCommand(new ReplaceCommand('#edit-department-wrapper', $form['department_id']));
    $ajax_response->addCommand(new ReplaceCommand('#edit-registration-code-wrapper', $form['registration_code']));

    // Return the AjaxResponse Object.
    return $ajax_response;
  }

}
