<?php
/**
 * @file
 * Contains \Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigConnectionForm.
 */

namespace Drupal\pp_taxonomy_manager\Form;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\pp_taxonomy_manager\Entity\PPTaxonomyManagerConfig;
use Drupal\semantic_connector\Entity\SemanticConnectorPPServerConnection;
use Drupal\semantic_connector\SemanticConnector;

class PPTaxonomyManagerConfigConnectionForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var PPTaxonomyManagerConfig $entity */
    $entity = $this->entity;
    $is_new = !$entity->getOriginalId();

    if (!$is_new) {
      $form['title'] = array(
        '#type' => 'hidden',
        '#value' => $entity->getTitle(),
      );
    }
    else {
      $form['title'] = array(
        '#type' => 'textfield',
        '#title' => t('Name'),
        '#description' => t('Name of the PoolParty Taxonomy Manager configuration.'),
        '#size' => 35,
        '#maxlength' => 255,
        '#default_value' => $entity->getTitle(),
        '#required' => TRUE,
        '#validated' => TRUE,
      );
    }

    $connection_overrides = \Drupal::config('semantic_connector.settings')->get('override_connections');
    $overridden_values = array();
    if (!$is_new && isset($connection_overrides[$entity->id()])) {
      $overridden_values = $connection_overrides[$entity->id()];
    }

    // Container: Server settings.
    $form['server_settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('1. Select the PoolParty server to use'),
    );

    if (isset($overridden_values['connection_id'])) {
      $form['server_settings']['overridden_connection'] = array(
        '#type' => 'markup',
        '#markup' => '<span class="semantic-connector-overridden-value">' . t('Warning: overridden by variable') . '</span>',
      );
    }

    $connections = SemanticConnector::getConnectionsByType('pp_server');
    if (!empty($connections)) {
      $connection_options = array();
      /** @var SemanticConnectorPPServerConnection $connection */
      foreach ($connections as $connection) {
        $credentials = $connection->getCredentials();
        $connection_options[implode('|', array(
          $connection->getTitle(),
          $connection->getUrl(),
          $credentials['username'],
          $credentials['password']
        ))] = $connection->getTitle();
      }
      $form['server_settings']['load_connection'] = array(
        '#type' => 'select',
        '#title' => t('Load an available PoolParty server'),
        '#options' => $connection_options,
        '#empty_option' => '',
        '#default_value' => '',
      );
    }

    // Container: Connection details.
    $form['server_settings']['connection_details'] = array(
      '#type' => 'fieldset',
      '#title' => t('Connection details'),
    );

    $connection = $entity->getConnection();
    $form['server_settings']['connection_details']['connection_id'] = array(
      '#type' => 'hidden',
      '#value' => $connection->id(),
    );

    $form['server_settings']['connection_details']['server_title'] = array(
      '#type' => 'textfield',
      '#title' => t('Server title'),
      '#description' => t('A short title for the server below.'),
      '#size' => 35,
      '#maxlength' => 60,
      '#default_value' => $connection->getTitle(),
      '#required' => TRUE,
    );

    $form['server_settings']['connection_details']['url'] = array(
      '#type' => 'textfield',
      '#title' => t('URL'),
      '#description' => t('URL, where the PoolParty server runs.'),
      '#size' => 35,
      '#maxlength' => 255,
      '#default_value' => $connection->getUrl(),
      '#required' => TRUE,
    );

    $credentials = $connection->getCredentials();
    $form['server_settings']['connection_details']['credentials'] = array(
      '#type' => 'fieldset',
      '#title' => t('Credentials'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['server_settings']['connection_details']['credentials']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#description' => t('Name of a user for the credentials.'),
      '#size' => 35,
      '#maxlength' => 60,
      '#default_value' => $credentials['username'],
    );
    $form['server_settings']['connection_details']['credentials']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#description' => t('Password of a user for the credentials.'),
      '#size' => 35,
      '#maxlength' => 128,
      '#default_value' => $credentials['password'],
    );

    $form['server_settings']['health_check'] = array(
      '#type' => 'button',
      '#value' => t('Health check'),
      '#ajax' => array(
        'callback' => '::connectionTest',
        'wrapper' => 'health_info',
        'method' => 'replace',
        'effect' => 'slide',
        'progress' => array(
          'type' => 'throbber',
          'message' => t('Testing the connection...'),
        ),
      ),
    );

    if ($is_new) {
      $markup = '<div id="health_info">' . t('Click to check if the server is available.') . '</div>';
    }
    else {
      $available = '<div id="health_info" class="available"><div class="semantic-connector-led led-green" title="Service available"></div>' . t('The server is available.') . '</div>';
      $not_available = '<div id="health_info" class="not-available"><div class="semantic-connector-led led-red" title="Service NOT available"></div>' . t('The server is not available or the credentials are incorrect.') . '</div>';
      $markup = $connection->available() ? $available : $not_available;
    }
    $form['server_settings']['health_info'] = array(
      '#markup' => $markup,
    );

    // Container: Project loading.
    $form['project_load'] = array(
      '#type' => 'fieldset',
      '#title' => t('2. Load the projects'),
    );

    $form['project_load']['load_projects'] = array(
      '#type' => 'button',
      '#value' => 'Load projects',
      '#ajax' => array(
        'event' => 'click',
        'callback' => '::getProjects',
        'wrapper' => 'projects-replace',
        'progress' => array(
          'type' => 'throbber',
          'message' => t('Loading projects...'),
        ),
      ),
    );

    // Container: Project selection.
    $form['project_select'] = array(
      '#type' => 'fieldset',
      '#title' => t('3. Select the project to use'),
      '#description' => t('Note: In case this list is still empty after clicking the "Load projects" button make sure that a connection to the PoolParty server can be established and check the rights of your selected user inside PoolParty.'),
    );

    // Get the project options for the currently configured PoolParty server.
    $project_options = array();
    if (!$is_new) {
      $projects = $connection->getApi('PPT')->getProjects();
      foreach ($projects as $project) {
        $project_options[$project->id] = $project->title;
      }
    }
    // configuration set admin page.
    $form['project_select']['project'] = array(
      '#type' => 'select',
      '#title' => 'Select a project',
      '#prefix' => '<div id="projects-replace">',
      '#suffix' => '</div>',
      '#options' => $project_options,
      '#default_value' => (!$is_new ? $entity->getProjectId() : NULL),
      '#required' => TRUE,
      '#validated' => TRUE,
    );

    if (isset($overridden_values['project_id'])) {
      $form['project_select']['project']['#description'] = '<span class="semantic-connector-overridden-value">' . t('Warning: overridden by variable') . '</span>';
    }

    // Add CSS and JS.
    $form['#attached'] = array(
      'library' =>  array(
        'pp_taxonomy_manager/admin_area',
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var PPTaxonomyManagerConfig $entity */
    $entity = $this->entity;
    $is_new = !$entity->getOriginalId();

    // Only do project validation during the save-operation, not during
    // ajax-requests like the health check of the server.
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#parents'][0] == 'save') {
      // A title is required.
      if ($is_new && empty($form_state->getValue('title'))) {
        $form_state->setErrorByName('title', t('Name field is required.'));
      }

      // A project needs to be selected.
      if (empty($form_state->getValue('project'))) {
        $form_state->setErrorByName('project', t('Please select a project.'));
      }
      // And it has to be a valid project (one that is connected to a
      // PoolParty Taxonomy Manager server).
      else {
        if (!empty($form_state->getValue('url')) && UrlHelper::isValid($form_state->getValue('url'), TRUE)) {
          // Create a new connection (without saving) with the current form data.
          $connection = SemanticConnector::getConnection('pp_server');
          $connection->setUrl($form_state['values']['url']);
          $connection->setCredentials(array(
            'username' => $form_state->getValue('username'),
            'password' => $form_state->getValue('password'),
          ));
        }
        else {
          $form_state->setErrorByName('url', t('The field URL must be a valid URL.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var PPTaxonomyManagerConfig $entity */
    $entity = $this->entity;
    $is_new = !$entity->getOriginalId();
    if ($is_new) {
      // Configuration entities need an ID manually set.
      $entity->set('id', SemanticConnector::createUniqueEntityMachineName('pp_taxonomy_manager', $entity->get('title')));
      drupal_set_message(t('PoolParty Taxonomy Manager configuration %title has been created.', array('%title' => $entity->get('title'))));
    }
    else {
      drupal_set_message(t('Updated PoolParty Taxonomy Manager configuration %title.',
        array('%title' => $entity->get('title'))));
    }

    // Always create a new connection, if URL and type are the same the old one
    // will be used anyway.
    $connection = SemanticConnector::createConnection('pp_server', $form_state->getValue('url'), $form_state->getValue('server_title'), array(
      'username' => $form_state->getValue('username'),
      'password' => $form_state->getValue('password'),
    ));

    $entity->set('connection_id', $connection->id());
    $entity->set('project_id', $form_state->getValue('project'));
    $entity->save();

    $form_state->setRedirectUrl(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $entity->id())));
  }

  /**
   * Ajax callback function for checking if a new PoolParty Taxonomy Manager server
   * is available.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface &$form_state
   *   The form_state array.
   *
   * @return array
   *   The output array to be rendered.
   */
  function connectionTest(array &$form, FormStateInterface $form_state) {
    $available = '<div id="health_info" class="available"><div class="semantic-connector-led led-green" title="Service available"></div>' . t('The server is available.') . '</div>';
    $not_available = '<div id="health_info" class="not-available"><div class="semantic-connector-led led-red" title="Service NOT available"></div>' . t('The server is not available or the credentials are incorrect.') . '</div>';
    $markup = '';

    if (!empty($form_state->getValue('url')) && UrlHelper::isValid($form_state->getValue('url'), TRUE)) {
      // Create a new connection (without saving) with the current form data.
      $connection = SemanticConnector::getConnection('pp_server');
      $connection->setUrl($form_state->getValue('url'));
      $connection->setCredentials(array(
        'username' => $form_state->getValue('username'),
        'password' => $form_state->getValue('password'),
      ));

      $availability = $connection->getApi('PPX')->available();
      if (isset($availability['message']) && !empty($availability['message'])) {
        $markup = '<div id="health_info" class="not-available"><div class="semantic-connector-led led-red" title="Service NOT available"></div>' . $availability['message'] . '</div>';
      }
      else {
        $markup = $availability['success'] ? $available : $not_available;
      }
    }

    if (empty($markup)) {
      $markup = $not_available;
    }

    // Clear potential error messages thrown during the requests.
    drupal_get_messages();

    return array(
      '#type' => 'markup',
      '#markup' => $markup,
    );
  }

  /**
   * Ajax callback function to get a project select list for a given PoolParty
   * server connection configuration.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface &$form_state
   *   The form_state array.
   *
   * @return array
   *   The select form element containing the project options for the current
   *   PoolParty server connection.
   */
  public function getProjects(array &$form, FormStateInterface $form_state) {
    $projects_element = $form['project_select']['project'];

    $project_options = array();
    if (!empty($form_state->getValue('url')) && UrlHelper::isValid($form_state->getValue('url'), TRUE)) {
      // Create a new connection (without saving) with the current form data.
      $connection = SemanticConnector::getConnection('pp_server');
      $connection->setUrl($form_state->getValue('url'));
      $connection->setCredentials(array(
        'username' => $form_state->getValue('username'),
        'password' => $form_state->getValue('password'),
      ));
      $projects = $connection->getApi('PPT')->getProjects();
      foreach ($projects as $project) {
        $project_options[$project->id] = $project->title;
      }
    }

    $projects_element['#options'] = $project_options;
    return $projects_element;
  }
}
