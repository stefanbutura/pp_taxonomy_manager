<?php
/**
 * @file
 * Contains \Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerImportForm.
 */

namespace Drupal\pp_taxonomy_manager\Form;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\pp_taxonomy_manager\Entity\PPTaxonomyManagerConfig;
use Drupal\pp_taxonomy_manager\PPTaxonomyManager;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The confirmation-form for the import of a taxonomy from a PoolParty server.
 */
class PPTaxonomyManagerImportForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pp_taxonomy_manager_import_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param PPTaxonomyManagerConfig $config
   *   The configuration of the PoolParty Taxonomy manager.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config = NULL) {
    /*$vocab = Vocabulary::load("german_vocabulary");
    var_dump($vocab); exit;*/
    // Check if concept scheme URI is given and is a url.
    $scheme_uri = $_GET['uri'];
    if (!UrlHelper::isValid($scheme_uri, TRUE)) {
      drupal_set_message(t('The URI from the selected concept scheme is not valid.'), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Get the project.
    $connection = $config->getConnection();
    $potential_projects = $connection->getApi('PPT')->getProjects();
    $project = NULL;
    foreach ($potential_projects as $potential_project) {
      if ($potential_project['id'] == $config->getProjectId()) {
        $project = $potential_project;
        break;
      }
    }
    if (is_null($project)) {
      drupal_set_message(t('The configured PoolParty project does not exists.'), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Check if concept scheme exists.
    $concept_schemes = $config->getConnection()
      ->getApi('PPT')
      ->getConceptSchemes($config->getProjectId());
    $concept_scheme = NULL;
    foreach ($concept_schemes as $scheme) {
      if ($scheme['uri'] == $scheme_uri) {
        $concept_scheme = $scheme;
        break;
      }
    }
    if (is_null($concept_scheme)) {
      drupal_set_message(t('The selected concept scheme does not exists.'), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Check if the taxonomy is already connected with a concept scheme.
    $configuration = $config->getConfig();
    if (in_array($scheme_uri, $configuration['taxonomies'])) {
      drupal_set_message(t('The concept scheme %scheme is already connected, please select another one.', array('%scheme' => $concept_scheme['title'])), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Check if the new taxonomy already exists in Drupal.
    $machine_name = PPTaxonomyManager::createMachineName($concept_scheme['title']);
    $taxonomy = Vocabulary::load($machine_name);

    $description = t('A new taxonomy will be created and all concepts from the concept scheme %scheme will be inserted as terms.', array('%scheme' => $concept_scheme['title']));
    $description .= '<br />' . t('This can take a while. Please wait until the import is finished.');
    $form['description'] = array(
      '#markup' => $description,
    );
    $field_description = t('Please enter a name of a taxonomy, which does not yet exist.');
    if ($taxonomy) {
      $field_description .= '<br />' . t('The taxonomy %taxonomy (machine name: %machine_name) already exists. Its terms will be updated, deleted and/or created.', array(
          '%taxonomy' => $taxonomy->label(),
          '%machine_name' => $taxonomy->id(),
        ));
    }
    $form['taxonomy_name'] = array(
      '#title' => t('Name of the new taxonomy'),
      '#type' => 'textfield',
      '#default_value' => $concept_scheme['title'],
      '#description' => $field_description,
      '#required' => TRUE,
    );

    // Language mapping.
    $available_languages = \Drupal::languageManager()->getLanguages();
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $project_language_options = array();
    foreach ($project['availableLanguages'] as $project_language) {
      $project_language_options[$project_language] = $project_language;
    }
    $form['languages'] = array(
      '#type' => 'item',
      '#title' => t('Map the Drupal languages with the PoolParty project languages'),
      '#tree' => TRUE,
    );
    /** @var LanguageInterface $lang */
    foreach ($available_languages as $lang) {
      if (!$lang->isLocked()) {
        $form['languages'][$lang->getId()] = array(
          '#type' => 'select',
          '#title' => t('Drupal language %language', array('%language' => $lang->getName())),
          '#description' => t('Select the PoolParty project language'),
          '#options' => $project_language_options,
          '#empty_option' => '',
          '#default_value' => (isset($project_language_options[$lang->getId()]) ? $project_language_options[$lang->getId()] : ''),
          '#required' => ($lang->getId() == $default_language ? TRUE : FALSE),
        );
      }
    }

    $form['concepts_per_request'] = array(
      '#type' => 'textfield',
      '#title' => t('PoolParty concepts per request'),
      '#description' => t('The number of concepts, that get processed during one HTTP request. (Allowed value range: 1 - 100)') . '<br />' . t('The higher this number is, the less HTTP requests have to be sent to the server until the batch finished updating ALL your concepts, what results in a shorter duration of the bulk updating process.') . '<br />' . t('Numbers too high can result in a timeout, which will break the whole bulk updating process.'),
      '#required' => TRUE,
      '#default_value' => 10,
    );

    $form['import'] = array(
      '#type' => 'submit',
      '#value' => t('Import taxonomy'),
    );
    $form['cancel'] = array(
      '#type' => 'link',
      '#title' => t('Cancel'),
      '#url' => Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id())),
      '#suffix' => '</div>',
    );

    $form_state->set('config', $config);
    $form_state->set('concept_scheme', $concept_scheme);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    if (!\Drupal::moduleHandler()->moduleExists('content_translation')) {
      foreach ($values['languages'] as $drupal_lang => $pp_lang) {
        if (!empty($pp_lang) && $drupal_lang != Language::LANGCODE_NOT_SPECIFIED && $drupal_lang != $default_language) {
          $drupal_languages = \Drupal::languageManager()->getLanguages();
          $form_state->setErrorByName('languages][' . $drupal_lang, t('Language "%language" requires translation of taxonomies as it is not your default site language.<br /> Install and enable module "Content Translation" and its sub-module "Taxonomy translation" to make multilingual tagging possible.', array(
            '%language' => $drupal_languages[$drupal_lang]->getName(),
          )));
        }
      }
    }

    // Check whether all languages are different.
    $languages = array_unique($values['languages']);
    if (count($values['languages']) != count($languages)) {
      $form_state->setErrorByName('languages', t('The selected languages must be different.'));
    }
    if (count(array_filter($languages)) > 1 && !\Drupal::moduleHandler()->moduleExists('content_translation')) {
      $form_state->setErrorByName('languages', t('Module "Content Translation" needs to be enabled for multilingual operations.'));
    }

    $concepts_per_request = $values['concepts_per_request'];
    if (empty($concepts_per_request) || !ctype_digit($concepts_per_request) || (int) $concepts_per_request == 0 || (int) $concepts_per_request > 100) {
      $form_state->setErrorByName('concepts_per_request', t('Only values in the range of 1 - 100 are allowed for field "PoolParty concepts per request"'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    /** @var PPTaxonomyManagerConfig $config */
    $config = $form_state->get('config');
    $concept_scheme = $form_state->get('concept_scheme');

    $concepts_per_request = $values['concepts_per_request'];
    $languages = PPTaxonomyManager::orderLanguages($values['languages']);

    $manager = PPTaxonomyManager::getInstance($config);

    // Create the new taxonomy .
    $taxonomy = $manager->createTaxonomy($concept_scheme, $values['taxonomy_name']);
    $manager->enableTranslation($taxonomy, $languages);

    // Add URI and alt. labels fields (if not exists) to the taxonomy.
    $manager->adaptTaxonomyFields($taxonomy);

    // Connect the new taxonomy with the concept scheme.
    $manager->addConnection($taxonomy->id(), $concept_scheme['uri'], $languages);

    // Import all concepts.
    $manager->updateTaxonomyTerms($taxonomy, $concept_scheme['uri'], $languages, $concepts_per_request);
    $form_state->setRedirect('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()));
  }
}
?>