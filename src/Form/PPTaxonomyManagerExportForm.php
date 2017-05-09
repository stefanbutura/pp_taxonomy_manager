<?php
/**
 * @file
 * Contains \Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerExportForm.
 */

namespace Drupal\pp_taxonomy_manager\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\pp_taxonomy_manager\Entity\PPTaxonomyManagerConfig;
use Drupal\pp_taxonomy_manager\PPTaxonomyManager;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The confirmation-form for the export of a taxonomy to a PoolParty server.
 */
class PPTaxonomyManagerExportForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pp_taxonomy_manager_export_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param PPTaxonomyManagerConfig $config
   *   The configuration of the PoolParty Taxonomy manager.
   * @param Vocabulary $taxonomy
   *   The taxonomy to use.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config = NULL, $taxonomy = NULL) {
    // Check if concept scheme URI is given and is a url.
    // Check if taxonomy exists.
    if ($taxonomy === FALSE) {
      drupal_set_message(t('The selected taxonomy does not exists.'), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Get the project.
    $connection = $config->getConnection();
    $projects = $connection->getApi('PPT')->getProjects();
    $project = NULL;
    foreach ($projects as $project) {
      if ($project->id == $config->getProjectId()) {
        break;
      }
    }
    if (is_null($project)) {
      drupal_set_message(t('The configured PoolParty project does not exists.'), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Check if the taxonomy is connected with a concept scheme.
    $configuration = $config->getConfig();
    if (isset($configuration['taxonomies'][$taxonomy->id()])) {
      drupal_set_message(t('The taxonomy %taxonomy is already connected, please select another one.', array('%taxonomy' => $taxonomy->label())), 'error');
      return new RedirectResponse(Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()))->toString());
    }

    // Get the sum of all terms from the taxonomy.
    $tree = \Drupal::service('entity_type.manager')
      ->getStorage("taxonomy_term")
      ->loadTree($taxonomy->id());
    $count = count($tree);

    $description = t('A new concept scheme will be created in the project %project and %count terms will be inserted.', array(
      '%project' => $project->title,
      '%count' => $count,
    ));
    $description .= '<br />' . t('This can take a while. Please wait until the export is finished.');
    $form['description'] = array(
      '#markup' => $description,
    );
    $form['concept_scheme_title'] = array(
      '#title' => t('Title of the new concept scheme'),
      '#type' => 'textfield',
      '#default_value' => $taxonomy->label(),
      '#required' => TRUE,
    );

    // Language mapping.
    // @todo: multilingualism?
    /*
    if (module_exists('i18n_taxonomy') && in_array($taxonomy->i18n_mode, array(
        I18N_MODE_LOCALIZE,
        I18N_MODE_TRANSLATE,
      ))) {
      $available_languages = language_list();
    }
    else {*/
      $available_languages = array(\Drupal::languageManager()->getDefaultLanguage());
    //}
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $project_language_options = array();
    foreach ($project->availableLanguages as $project_language) {
      $project_language_options[$project_language] = $project_language;
    }
    $form['languages'] = array(
      '#type' => 'item',
      '#title' => t('Map the Drupal languages with the PoolParty project languages'),
      '#description' => count($available_languages) > 1 ? t('The term-translations of the non-selected languages are not exported.') : '',
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
          '#default_value' => ($lang->getId() == $default_language ? $project->defaultLanguage : ''),
          '#required' => ($lang->getId() == $default_language ? TRUE : FALSE),
          '#disabled' => ($lang->getId() == $default_language ? TRUE : FALSE),
        );
      }
    }

    $form['terms_per_request'] = array(
      '#type' => 'textfield',
      '#title' => t('Taxonomy terms per request'),
      '#description' => t('The number of terms, that get processed during one HTTP request. (Allowed value range: 1 - 100)') . '<br />' . t('The higher this number is, the less HTTP requests have to be sent to the server until the batch finished exporting ALL your terms, what results in a shorter duration of the bulk exporting process.') . '<br />' . t('Numbers too high can result in a timeout, which will break the whole bulk exporting process.'),
      '#required' => TRUE,
      '#default_value' => 10,
    );

    // @todo: multilingualism
    /* if (module_exists('i18n_taxonomy') && $taxonomy->i18n_mode == I18N_MODE_LOCALIZE) {
      $form['info'] = array(
        '#prefix' => '<p><label>' . t('Attention:') . '</label>',
        '#markup' => t('The translation mode of this taxonomy will be changed from "Localize" to "Translate" first before the export begins.'),
        '#suffix' => '</p>',
      );
    } */

    $form['save'] = array(
      '#type' => 'submit',
      '#value' => t('Export taxonomy'),
    );
    $form['cancel'] = array(
      '#type' => 'link',
      '#title' => t('Cancel'),
      '#url' => Url::fromRoute('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id())),
      '#suffix' => '</div>',
    );

    $form_state->set('config', $config);
    $form_state->set('taxonomy', $taxonomy);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Check whether all languages are different.
    $languages = array_unique($values['languages']);
    if (count($values['languages']) != count($languages)) {
      $form_state->setErrorByName('languages', t('The selected languages must be different.'));
    }

    $concepts_per_request = $values['terms_per_request'];
    if (empty($concepts_per_request) || !ctype_digit($concepts_per_request) || (int) $concepts_per_request == 0 || (int) $concepts_per_request > 100) {
      $form_state->setErrorByName('terms_per_request', t('Only values in the range of 1 - 100 are allowed for field "Taxonomy terms per request"'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    /** @var PPTaxonomyManagerConfig $config */
    $config = $form_state->get('config');
    /** @var Vocabulary $taxonomy */
    $taxonomy = $form_state->get('taxonomy');

    $terms_per_request = $values['terms_per_request'];
    $languages = PPTaxonomyManager::orderLanguages($values['languages']);

    $manager = PPTaxonomyManager::getInstance($config);

    // Set the correct translation mode for the taxonomy.
    $manager->setTranslationMode($taxonomy, $languages);

    // Add URI and alt. labels fields (if not exists) to the taxonomy.
    $manager->adaptTaxonomyFields($taxonomy);

    // Create the new concept scheme in the PoolParty thesaurus.
    $scheme_uri = $manager->createConceptScheme($taxonomy, $values['concept_scheme_title']);

    // Connect the taxonomy with the new concept scheme.
    $manager->addConnection($taxonomy->id(), $scheme_uri, $languages);

    // Export all taxonomy terms.
    $manager->exportTaxonomyTerms($taxonomy, $scheme_uri, $languages, $terms_per_request);

    $form_state->setRedirect('entity.pp_taxonomy_manager.edit_config_form', array('pp_taxonomy_manager' => $config->id()));
  }
}
?>