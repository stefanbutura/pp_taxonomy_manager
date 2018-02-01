<?php

/**
 * @file
 * The main class of the PoolParty Taxonomy Manager.
 */

namespace Drupal\pp_taxonomy_manager;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\pp_taxonomy_manager\Entity\PPTaxonomyManagerConfig;
use Drupal\semantic_connector\Api\SemanticConnectorPPTApi;
use Drupal\semantic_connector\SemanticConnector;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermStorage;

/**
 * A collection of static functions offered by the PoolParty Taxonomy Manager module.
 */
class PPTaxonomyManager {

  protected static $instance;
  protected $config;

  /**
   * Constructor of the PoolParty Taxonomy Manager class.
   *
   * @param $config PPTaxonomyManagerConfig
   *   The configuration of the PoolParty Taxonomy Manager.
   */
  protected function __construct($config) {
    $this->config = $config;
  }

  /**
   * Get a smart-glossary-instance (Singleton).
   *
   * @param $config PPTaxonomyManagerConfig
   *   The configuration of the PoolParty Taxonomy Manager.
   *
   * @return PPTaxonomyManager
   *   The PoolParty Taxonomy Manager instance.
   */
  public static function getInstance($config) {
    if (!isset(self::$instance)) {
      $object_name = __CLASS__;
      self::$instance = new $object_name($config);
    }
    return self::$instance;
  }

  /**
   * Create a new PP Taxonomy Manager configuration.
   *
   * @param string $title
   *   The title of the configuration.
   * @param string $project_id
   *   The ID of the project
   * @param string $connection_id
   *   The ID of Semantic Connector connection
   * @param array $config
   *   The config of the PP Taxonomy Manager configuration as an array.
   *
   * @return PPTaxonomyManagerConfig
   *   The new PP Taxonomy Manager configuration.
   */
  public static function createConfiguration($title, $project_id, $connection_id, array $config = array()) {
    $configuration = PPTaxonomyManagerConfig::create();
    $configuration->set('id', SemanticConnector::createUniqueEntityMachineName('pp_taxonomy_manager', $title));
    $configuration->setTitle($title);
    $configuration->setProjectID($project_id);
    $configuration->setConnectionId($connection_id);
    $configuration->setConfig($config);
    $configuration->save();

    return $configuration;
  }

  /**
   * Updates an existing connection between a taxonomy and a PP concept scheme.
   *
   * @param int $vid
   *   The taxonomy ID.
   * @param string $scheme_uri
   *   The concept scheme URI.
   * @param array $languages
   *   An array of language mappings between Drupal and PoolParty project
   *   languages.
   */
  public function updateConnection($vid, $scheme_uri, $languages) {
    $this->addConnection($vid, $scheme_uri, $languages);
  }

  /**
   * Adds a new connection between a taxonomy and a PoolParty concept scheme.
   *
   * @param int $vid
   *   The taxonomy ID.
   * @param string $scheme_uri
   *   The concept scheme URI.
   * @param array $languages
   *   An array of language mappings between Drupal and PoolParty project
   *   languages.
   */
  public function addConnection($vid, $scheme_uri, $languages) {
    $settings = $this->config->getConfig();
    $settings['taxonomies'][$vid] = $scheme_uri;
    $settings['languages'][$vid] = $languages;
    $this->config->setConfig($settings);
    $this->config->save();
  }

  /**
   * Deletes a connection between a taxonomy and a PoolParty concept scheme.
   *
   * @param int $vid
   *   The taxonomy ID.
   */
  public function deleteConnection($vid) {
    $settings = $this->config->getConfig();
    unset($settings['taxonomies'][$vid]);
    unset($settings['languages'][$vid]);
    $this->config->setConfig($settings);
    $this->config->save();
  }

  /**
   * Changes the translation mode of a taxonomy from "Localize" to "Translate".
   *
   * @param object $taxonomy
   *   A Drupal taxonomy object.
   * @param array $languages
   *   An array of languages:
   *    key = Drupal language
   *    value = PoolParty language.
   */
  protected function changeTranslationMode($taxonomy, $languages) {
    // @todo: implement method.
    /*$default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $selected_languages = array_keys($languages);

    $processed_terms = array();
    $parents = array();

    // Go through all taxonomy terms.
    $tree = taxonomy_get_tree($taxonomy->vid, 0, NULL, TRUE);
    foreach ($tree as $term) {
      if (isset($processed_terms[$term->id()])) {
        continue;
      }
      $processed_terms[$term->id()] = TRUE;
      $translation_set = NULL;
      $parents[$term->id()] = array();

      // Go through all selected languages.
      foreach ($selected_languages as $language) {
        $translate_term = clone $term;
        $save_translation = FALSE;

        // Create a new translation set if the language is the default language.
        if ($language == $default_language) {
          $save_translation = TRUE;
          $translation_set = i18n_translation_set_create('taxonomy_term', $taxonomy->machine_name);
        }

        // For all other languages get its translations if exists and create new
        // term object.
        else {
          $translations = i18n_string_translation_search('taxonomy:term:' . $term->id() . ':*', $language);
          if (!empty($translations)) {
            $save_translation = TRUE;
            $translate_term->tid = NULL;
            $translate_term->parent = NULL;
            foreach ($translations as $translation) {
              $translate_term->{$translation->property} = $translation->translations[$language];
            }
          }
        }

        // Save the term and add it to the translation set.
        if ($translation_set && $save_translation) {
          // Add the parents for the terms with non-default language.
          if ($language != $default_language) {
            foreach ($term->getParents() as $parent) {
              if (!empty($parents[$parent][$language])) {
                $translate_term->parent[] = $parents[$parent][$language];
              }
            }
          }
          // Save the term.
          $translate_term->language = $language;
          taxonomy_term_save($translate_term);
          // Add the term to the translation set.
          $translation_set->add_item($translate_term, $language);
          $translation_set->save();

          if ($language != $default_language) {
            $parents[$term->id()][$language] = $translate_term->tid;
          }
        }
      }
    }*/
  }

  /**
   * Creates a new Drupal taxonomy.
   *
   * @param object $concept_scheme
   *   A PoolParty concept scheme.
   * @param string $taxonomy_name
   *   The name of the taxonomy to create If NULL is given the title of the
   *   concept scheme gets used (default).
   *
   * @return Vocabulary
   *   The created taxonomy.
   */
  public function createTaxonomy($concept_scheme, $taxonomy_name = '') {
    $taxonomy_name = trim(\Drupal\Component\Utility\Html::escape($taxonomy_name));
    if (empty($taxonomy_name)) {
      $taxonomy_name = $concept_scheme->title;
    }

    // Check if the new taxonomy already exists.
    $machine_name = self::createMachineName($taxonomy_name);
    $taxonomy = Vocabulary::load($machine_name);

    if (!$taxonomy) {
      // Create the new taxonomy.
      $taxonomy = Vocabulary::create(array(
        'vid' => $machine_name,
        'machine_name' => $machine_name,
        'description' => substr(t('Automatically created by PoolParty Taxonomy Manager.') . ((isset($concept_scheme->descriptions) && !empty($concept_scheme->descriptions)) ? ' ' . $concept_scheme->descriptions[0] : ''), 0, 128),
        'name' => $taxonomy_name,
      ));
      $taxonomy->save();

      drupal_set_message(t('Vocabulary %taxonomy successfully created.', array('%taxonomy' => $taxonomy_name)));
      \Drupal::logger('pp_taxonomy_manager')->notice('Vocabulary created: %taxonomy (VID = %vid)', array(
        '%taxonomy' => $taxonomy_name,
        '%vid' => $taxonomy->id(),
      ));
    }

    return $taxonomy;
  }

  /**
   * Set the correct translation mode for the Drupal taxonomy.
   *
   * @param Vocabulary $vocabulary
   *   A Drupal taxonomy.
   * @param array $languages
   *   An array of languages:
   *    key = Drupal language
   *    value = PoolParty language.
   *
   * @return boolean
   *   TRUE if the translation mode had to be changed, FALSE if not.
   */
  public function enableTranslation($vocabulary, $languages) {
    if (\Drupal::moduleHandler()->moduleExists('content_translation')) {
      $language_count = count($languages);

      // Make the taxonomy translatable if the translation module for taxonomies
      // is installed and more than one language is selected.
      if ($language_count > 1 && !\Drupal::service('content_translation.manager')->isEnabled('taxonomy_term', $vocabulary->id())) {
        \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $vocabulary->id(), TRUE);
        \Drupal::entityTypeManager()->clearCachedDefinitions();
        \Drupal::service('router.builder')->setRebuildNeeded();
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Adds additional fields to a specific taxonomy term if not exists.
   *
   * @param Vocabulary $vocabulary
   *   A Drupal taxonomy object.
   */
  public function adaptTaxonomyFields(Vocabulary $vocabulary) {
    $fields = self::taxonomyFields();
    foreach ($fields as $field) {
      $this->createVocabularyField($field);
      $this->addFieldtoVocabulary($field, $vocabulary);

      // Set the widget data.
      entity_get_form_display('taxonomy_term', $vocabulary->id(), 'default')
        ->setComponent($field['field_name'], $field['widget'])
        ->save();
    }
  }

  /**
   * Creates a new field if not exists.
   *
   * @param array $field
   *   The field that should be created.
   */
  protected function createVocabularyField(array $field) {
    if (is_null(FieldStorageConfig::loadByName('taxonomy_term', $field['field_name']))) {
      $new_field = [
        'field_name' => $field['field_name'],
        'type' => $field['type'],
        'entity_type' => 'taxonomy_term',
        'cardinality' => $field['cardinality'],
        'settings' => $field['field_settings'],
      ];
      FieldStorageConfig::create($new_field)->save();
    }
  }

  /**
   * Adds a field to a specific taxonomy term if not exists.
   *
   * @param array $field
   *   The field that should be added.
   * @param Vocabulary $vocabulary
   *   The taxonomy at which the field should be added.
   */
  protected function addFieldtoVocabulary(array $field, Vocabulary $vocabulary) {
    if (is_null(FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), $field['field_name']))) {
      $instance = [
        'field_name' => $field['field_name'],
        'entity_type' => 'taxonomy_term',
        'bundle' => $vocabulary->id(),
        'label' => $field['label'],
        'description' => $field['description'],
        'required' => $field['required'],
        'settings' => $field['instance_settings'],
      ];
      FieldConfig::create($instance)->save();
    }
  }

  /**
   * Creates a new concept scheme on the specified PoolParty server.
   *
   * @param Vocabulary $vocabulary
   *   A Drupal taxonomy object.
   * @param string $scheme_title
   *   The title of the concept scheme to create. If NULL is given the name of
   *   the taxonomy gets used (default).
   *
   * @return string
   *   The URI of the new concept scheme.
   */
  public function createConceptScheme(Vocabulary $vocabulary, $scheme_title = '') {
    $scheme_title = trim(\Drupal\Component\Utility\Html::escape($scheme_title));
    if (empty($scheme_title)) {
      $scheme_title = $vocabulary->label();
    }

    $description = 'Automatically created by Drupal. ' . $vocabulary->getDescription();
    /** @var SemanticConnectorPPTApi $ppt */
    $ppt = $this->config->getConnection()->getAPI('PPT');
    $scheme_uri = $ppt->createConceptScheme($this->config->getProjectId(), $scheme_title, $description);
    drupal_set_message(t('Concept scheme %scheme successfully created.', array('%scheme' => $scheme_title)));
    \Drupal::logger('pp_taxonomy_manager')->notice('Concept scheme created: %scheme (URI = %uri)', array(
      '%scheme' => $scheme_title,
      '%uri' => $scheme_uri,
    ));

    return $scheme_uri;
  }

  /**
   * Creates a batch for exporting all terms of a taxonomy.
   *
   * @param Vocabulary $vocabulary
   *   A Drupal taxonomy object.
   * @param string $scheme_uri
   *   A concept scheme URI
   * @param array $languages
   *   An array of languages:
   *    key = Drupal language
   *    value = PoolParty language.
   * @param int $terms_per_request
   *   Count of taxonomy terms per http request.
   */
  public function exportTaxonomyTerms(Vocabulary $vocabulary, $scheme_uri, $languages, $terms_per_request) {
    $start_time = time();

    // Configure the batch data.
    $batch = array(
      'title' => t('Exporting taxonomy %name ...', array('%name' => $vocabulary->label())),
      'operations' => array(),
      'init_message' => t('Starting with the export of the taxonomy terms.'),
      'progress_message' => t('Processed @current out of @total.'),
      'finished' => array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'exportTermsFinished'),
    );

    // Get the taxonomy tree for the default language.
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $tree = \Drupal::service('entity_type.manager')
      ->getStorage("taxonomy_term")
      ->loadTree($vocabulary->id(), 0, NULL, TRUE);

    // Set additional data.
    $count = count($tree);
    $info = array(
      'total' => $count,
      'start_time' => $start_time,
    );

    // Enable the translation for the taxonomy if required.
    $this->enableTranslation($vocabulary, $languages);

    // Set the export operations.
    for ($i = 0; $i < $count; $i += $terms_per_request) {
      $terms = array_slice($tree, $i, $terms_per_request);
      $batch['operations'][] = array(
        array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'exportTerms'),
        array(
          $this,
          $terms,
          $default_language,
          $languages[$default_language],
          $scheme_uri,
          $info,
        ),
      );
    }

    // Set the update hash table operations after the export of all terms.
    for ($i = 0; $i < $count; $i += $terms_per_request) {
      $terms = array_slice($tree, $i, $terms_per_request);
      $batch['operations'][] = array(
        array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'updateTermHashes'),
        array($this, $terms, $info),
      );
    }

    // Set the export translation operations.
    unset($languages[$default_language]);
    if (!empty($languages)) {
      foreach ($languages as $drupal_lang => $pp_lang) {
        /*$tree = i18n_taxonomy_get_tree($vocabulary->id(), $drupal_lang, 0, NULL, TRUE);
        $count = count($tree);
        $info = array(
          'total' => $count,
          'start_time' => $start_time,
        );*/

        for ($i = 0; $i < $count; $i += $terms_per_request) {
          $terms = array_slice($tree, $i, $terms_per_request);
          $batch['operations'][] = array(
            array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'exportTermTranslations'),
            array($this, $terms, $drupal_lang, $pp_lang, $info),
          );
        }
      }
    }

    // Set the log operation.
    $batch['operations'][] = array(
      array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'saveVocabularyLog'),
      array($this, $vocabulary->id(), $info),
    );

    // Start the batch.
    batch_set($batch);
  }

  /**
   * Batch process method for exporting taxonomy terms into a PoolParty server.
   *
   * @param Term[] $terms
   *   The taxonomy terms that are to be exported.
   * @param string $drupal_lang
   *   The language of the taxonomy terms that are to be exported.
   * @param string $pp_lang
   *   The language of the concept that are to be created.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public function exportBatch(array $terms, $drupal_lang, $pp_lang, array &$context) {
    /** @var SemanticConnectorPPTApi $ppt */
    $ppt = $this->config->getConnection()->getAPI('PPT');

    // Custom attribute fields.
    $custom_fields = self::taxonomyFields();
    $normal_fields = array(
      'field_uri',
      'field_alt_labels',
      'field_hidden_labels',
    );
    foreach ($normal_fields as $normal_field) {
      unset($custom_fields[$normal_field]);
    }

    $exported_terms = &$context['results']['exported_terms'];
    $project_id = $this->config->getProjectId();
    /** @var Term $term */
    foreach ($terms as $term) {
      // Create an array of parent IDs
      /** @var TermStorage $term_storage */
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $parents = $term_storage->loadParents($term->id());
      $parent_tids = array();
      if (empty($parents)) {
        $parent_tids[] = 0;
      }
      else {
        /** @var Term $parent */
        foreach ($parents as $parent) {
          $parent_tids[] = $parent->id();
        }
      }

      // Create new concept.
      $created = FALSE;
      if (!isset($exported_terms[$term->id()])) {
        foreach ($parent_tids as $parent_tid) {
          if (isset($exported_terms[$parent_tid])) {
            // Create new concept.
            if (!$created) {
              $uri = $ppt->createConcept($project_id, $term->getName(), $exported_terms[$parent_tid]['uri']);
              // Add definition, alt labels, hidden labels and custom properties
              // if required.
              if (!empty($term->getDescription())) {
                $ppt->addLiteral($project_id, $uri, 'definition', $term->getDescription(), $pp_lang);
              }
              $alt_label_values = $term->get('field_alt_labels')->getValue();
              if (!empty($alt_label_values)) {
                $alt_labels = explode(',', $alt_label_values[0]['value']);
                foreach ($alt_labels as $alt_label) {
                  $ppt->addLiteral($project_id, $uri, 'alternativeLabel', $alt_label, $pp_lang);
                }
              }
              $hidden_label_values = $term->get('field_hidden_labels')->getValue();
              if (!empty($hidden_label_values)) {
                $hidden_labels = explode(',', $hidden_label_values[0]['value']);
                foreach ($hidden_labels as $hidden_label) {
                  $ppt->addLiteral($project_id, $uri, 'hiddenLabel', $hidden_label, $pp_lang);
                }
              }
              if (!empty($custom_fields)) {
                foreach ($custom_fields as $field_id => $field_schema) {
                  if (isset($term->{$field_id})) {
                    $custom_field_values = $term->{$field_id}->getValue();
                    if (!empty($custom_field_values)) {
                      $ppt->addCustomAttribute($project_id, $uri, $field_schema['property'], $custom_field_values[0]['value'], $pp_lang);
                    }
                  }
                }
              }

              // Update term with the new URI.
              $term->get('field_uri')->setValue($uri);
              $term->save();

              $exported_terms[$term->id()] = array(
                'uri' => $uri,
                'parents' => array($parent_tid),
                'drupalLang' => $drupal_lang,
                'ppLang' => $pp_lang,
                'hash' => FALSE,
              );
              \Drupal::logger('pp_taxonomy_manager')->notice('Concept created: %name (URI = %uri)', array(
                '%name' => $term->getName(),
                '%uri' => $uri,
              ));
              $created = TRUE;
            }
            // Add additional parents.
            else {
              $uri = $exported_terms[$term->id()]['uri'];
              $ppt->addRelation($project_id, $uri, $exported_terms[$parent_tid]['uri']);
              $exported_terms[$term->id()]['parents'][] = $parent_tid;
            }
          }
        }
      }
      // Add missing parents to the concept.
      else {
        foreach ($parent_tids as $parent_tid) {
          if (isset($exported_terms[$parent_tid]) && !in_array($parent_tid, $exported_terms[$term->id()]['parents'])) {
            $uri = $exported_terms[$term->id()]['uri'];
            $ppt->addRelation($project_id, $uri, $exported_terms[$parent_tid]['uri']);
            $exported_terms[$term->id()]['parents'][] = $parent_tid;
          }
        }
      }
      $context['results']['processed']++;
    }
  }

  /**
   * Batch process function for updating the hash table after the export.
   *
   * @param Term[] $terms
   *   The taxonomy terms that are to be exported.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public function updateHashBatch(array $terms, array $info, array &$context) {
    /** @var SemanticConnectorPPTApi $ppt */
    $ppt = $this->config->getConnection()->getAPI('PPT');

    $exported_terms = &$context['results']['exported_terms'];
    $project_id = $this->config->getProjectId();
    /** @var Term $term */
    foreach ($terms as $term) {
      if (isset($exported_terms[$term->id()]) && !$exported_terms[$term->id()]['hash']) {
        // Add hash data to the database.
        $data = $exported_terms[$term->id()];
        $concept = $ppt->getConcept($project_id, $data['uri'], $this->skosProperties(), $data['ppLang']);
        $concept->drupalLang = $data['drupalLang'];
        $concept->ppLang = $data['ppLang'];

        $uri_lang = $this->getUri($concept);
        $hash = $this->hash($concept);
        $this->addHashData($term, $data['ppLang'], $uri_lang, $hash, $info['start_time']);

        $exported_terms[$term->id()]['hash'] = TRUE;
        $context['results']['hash_update_processed']++;
      }
    }
  }

  /**
   * Batch process method for exporting taxonomy terms into a PoolParty server.
   *
   * @param Term[] $terms
   *   The taxonomy terms that are to be exported.
   * @param string $drupal_lang
   *   The language of the taxonomy terms that are to be exported.
   * @param string $pp_lang
   *   The language of the concept that are to be created.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public function exportTranslationsBatch(array $terms, $drupal_lang, $pp_lang, array $info, array &$context) {
    /** @var SemanticConnectorPPTApi $ppt */
    $ppt = $this->config->getConnection()->getAPI('PPT');

    // Custom attribute fields.
    $custom_fields = self::taxonomyFields();
    $normal_fields = array(
      'field_uri',
      'field_alt_labels',
      'field_hidden_labels',
    );
    foreach ($normal_fields as $normal_field) {
      unset($custom_fields[$normal_field]);
    }

    $exported_terms = $context['results']['exported_terms'];
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $project_id = $this->config->getProjectId();
    /** @var Term $term */
    foreach ($terms as $term) {
      // Check if the term with the default language is already exported.
      if (isset($exported_terms[$term->id()]) && $term->hasTranslation($drupal_lang)) {
        // Get the translated version of the taxonomy term.
        $term = $term->getTranslation($drupal_lang);

        $uri = $exported_terms[$term->id()]['uri'];
        // Add pref, alt, hidden labels and definition.
        $ppt->addLiteral($project_id, $uri, 'preferredLabel', $term->getName(), $pp_lang);
        if (!empty($term->getDescription())) {
          $ppt->addLiteral($project_id, $uri, 'definition', $term->getDescription(), $pp_lang);
        }
        $alt_label_values = $term->get('field_alt_labels')->getValue();
        if (!empty($alt_label_values)) {
          $alt_labels = explode(',', $alt_label_values[0]['value']);
          foreach ($alt_labels as $alt_label) {
            $ppt->addLiteral($project_id, $uri, 'alternativeLabel', $alt_label, $pp_lang);
          }
        }
        $hidden_label_values = $term->get('field_hidden_labels')->getValue();
        if (!empty($hidden_label_values)) {
          $hidden_labels = explode(',', $hidden_label_values[0]['value']);
          foreach ($hidden_labels as $hidden_label) {
            $ppt->addLiteral($project_id, $uri, 'hiddenLabel', $hidden_label, $pp_lang);
          }
        }
        if (!empty($custom_fields)) {
          foreach ($custom_fields as $field_id => $field_schema) {
            if (isset($term->{$field_id})) {
              $custom_field_values = $term->{$field_id}->getValue();
              if (!empty($custom_field_values)) {
                $ppt->addCustomAttribute($project_id, $uri, $field_schema['property'], $custom_field_values[0]['value'], $pp_lang);
              }
            }
          }
        }

        // Add hash data to the database.
        $concept = $ppt->getConcept($project_id, $uri, $this->skosProperties(), $pp_lang);
        $concept->drupalLang = $drupal_lang;
        $concept->ppLang = $pp_lang;

        $uri_lang = $this->getUri($concept);
        $hash = $this->hash($concept);
        $this->addHashData($term, $pp_lang, $uri_lang, $hash, $info['start_time']);
      }
    }

    $context['results']['translation_processed']++;
  }

  /**
   * Creates a batch for updating all terms of a taxonomy.
   *
   * @param Vocabulary $vocabulary
   *   A Drupal taxonomy object.
   * @param string $scheme_uri
   *   A concept scheme URI
   * @param array $languages
   *   An array of languages:
   *    key = Drupal language
   *    value = PoolParty language.
   * @param int $concepts_per_request
   *   Count of concepts per http request.
   */
  public function updateTaxonomyTerms(Vocabulary $vocabulary, $scheme_uri, $languages, $concepts_per_request) {
    $start_time = time();

    // Configure the batch data.
    $batch = array(
      'title' => t('Updating taxonomy %name ...', array('%name' => $vocabulary->label())),
      'operations' => array(),
      'init_message' => t('Starting with the update of the taxonomy terms.'),
      'progress_message' => t('Processed @current out of @total.'),
      'finished' => array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'updateTermsFinished'),
    );

    // Get all the concepts from the PoolParty server per language.
    $skos_properties = $this->skosProperties();
    /** @var SemanticConnectorPPTApi $ppt */
    $ppt = $this->config->getConnection()->getApi('PPT');

    $top_concept_uris = array();
    $concepts = array();
    $count = 0;
    foreach ($languages as $drupal_lang => $pp_lang) {
      $concepts[$pp_lang] = array();
      $top_concepts = $ppt->getTopConcepts($this->config->getProjectId(), $scheme_uri, $skos_properties, $pp_lang);
      foreach ($top_concepts as $top_concept) {
        $top_concept_uris[] = $top_concept['uri'] . '@' . $pp_lang;
      }
      $tree = $ppt->getSubTree($this->config->getProjectId(), $scheme_uri, $skos_properties, $pp_lang);
      $tree_list = $this->tree2list($tree, $drupal_lang, $pp_lang);
      $concepts[$pp_lang] = array_merge($concepts[$pp_lang], $tree_list);
      $count += count($tree_list);
    }

    // Set additional data.
    $info = array(
      'total' => $count,
      'start_time' => $start_time,
      'top_concept_uris' => $top_concept_uris,
    );

    // Enable the translation for the taxonomy if required.
    $this->enableTranslation($vocabulary, $languages);

    // Set the update operations.
    foreach ($concepts as $pp_lang => $lang_concepts) {
      for ($i = 0; $i < count($lang_concepts); $i += $concepts_per_request) {
        $concept_list = array_slice($lang_concepts, $i, $concepts_per_request);
        $batch['operations'][] = array(
          array(
            '\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches',
            'updateTerms'
          ),
          array(
            $this,
            $concept_list,
            $pp_lang,
            $vocabulary->id(),
            $vocabulary->id(),
            $info,
          ),
        );
      }
    }

    // Set the update parents operations.
    foreach ($concepts as $pp_lang => $lang_concepts) {
      for ($i = 0; $i < count($lang_concepts); $i += $concepts_per_request) {
        $concept_list = array_slice($lang_concepts, $i, $concepts_per_request);
        $batch['operations'][] = array(
          array(
            '\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches',
            'updateTermParents'
          ),
          array($this, $concept_list, $info),
        );
      }
    }

    // Set the delete operations for the removed concepts.
    $batch['operations'][] = array(
      array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'deleteVocabulary'),
      array($this, $vocabulary->id()),
    );

    // Set the log operation.
    $batch['operations'][] = array(
      array('\Drupal\pp_taxonomy_manager\PPTaxonomyManagerBatches', 'saveVocabularyLog'),
      array($this, $vocabulary->id(), $info),
    );

    // Start the batch.
    batch_set($batch);
  }

  /**
   * Batch process method for updating taxonomy terms from a PoolParty server.
   *
   * @param array $concepts
   *   The concepts that are to be updated.
   * @param string $pp_lang
   *   The PoolParty language of the concepts.
   * @param string $vid
   *   The taxonomy ID where the terms should be updated.
   * @param string $machine_name
   *   The taxonomy machine_name where the terms should be updated.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public function updateBatch($concepts, $pp_lang, $vid, $machine_name, $info, &$context) {
    $uris = array_keys($concepts);
    $processed_uris = array();
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();

    // Get mapping data for every concept.
    $term_query = \Drupal::database()->select('pp_taxonomy_manager_terms', 't');
    $term_query->fields('t', array('tid', 'language', 'uri', 'hash'));
    $term_query->condition('t.tmid', $this->config->id());
    $term_query->condition('t.language', $pp_lang);
    $term_query->condition('t.vid', $vid);
    $term_query->condition('t.uri', $uris, 'IN');
    $result = $term_query->execute();

    // Update all existing terms.
    while ($record = $result->fetch()) {
      $concept = $concepts[$record->uri];
      $hash = $this->hash($concept);
      if ($record->hash != $hash) {
        $term = Term::load($record->tid);
        // Normal update.
        if ($concept['drupalLang'] == $default_language) {
          $term = $this->mapTaxonomyTermDetails($term, $concept);
        }
        // Translation update.
        else {
          // Get the translated version of the taxonomy term.
          $translation = $term->getTranslation($concept['drupalLang']);
          $mapped_translation = $this->mapTaxonomyTermDetails($translation, $concept);
          $term->addTranslation($concept['drupalLang'], $mapped_translation->toArray());
        }
        $term->save();
        $this->updateHashData($term, $pp_lang, $hash, $info['start_time']);
        $context['results']['updated_terms'][$record->uri] = $record->tid;
        \Drupal::logger('pp_taxonomy_manager')->notice('Taxonomy term updated: %name (TID = %tid) (%lang)', array(
          '%name' => $term->getName(),
          '%tid' => $term->id(),
          '%lang' => $concept['drupalLang'],
        ));
      }
      else {
        $context['results']['skipped_terms'][$record->uri] = $record->tid;
      }

      $processed_uris[] = $record->uri;
      $context['results']['processed']++;
    }

    // Create new terms for new existing concepts.
    $new_uris = array_diff($uris, $processed_uris);
    if (!empty($new_uris)) {
      foreach ($new_uris as $uri) {
        // Check if a term with the same URI and language already exists
        // in the taxonomy.
        $concept = $concepts[$uri];

        $query = \Drupal::entityQuery('taxonomy_term');
        $query->condition('vid', $machine_name);
        $query->condition('field_uri', $concept['uri']);

        $result = $query->execute();
        $tid = reset($result);

        if ($tid) {
          $term = Term::load($tid);
        }
        else {
          $term = Term::create(array('vid' => $vid));
        }

        // Normal update.
        if ($concept['drupalLang'] == $default_language) {
          $term = $this->mapTaxonomyTermDetails($term, $concept);
        }
        // Translation update.
        else {
          // Get the translated version of the taxonomy term.
          if ($term->hasTranslation($concept['drupalLang'])) {
            $translation = $term->getTranslation($concept['drupalLang']);
          }
          else {
            $translation = clone $term;
          }
          $mapped_translation = $this->mapTaxonomyTermDetails($translation, $concept);
          $term->addTranslation($concept['drupalLang'], $mapped_translation->toArray());
        }

        $term->save();

        // Add the hash to the hash table.
        $uri = $this->getUri($concept);
        $hash = $this->hash($concept);
        $this->addHashData($term, $pp_lang, $uri, $hash, $info['start_time']);
        $context['results']['created_terms'][$uri] = $term->id();
        $context['results']['processed']++;
        \Drupal::logger('pp_taxonomy_manager')->notice('Taxonomy term created: %name (TID = %tid)', array(
          '%name' => $term->getName(),
          '%tid' => $term->id(),
        ));
      }
    }
  }

  /**
   * Batch process method for updating the parents for the taxonomy terms.
   *
   * @param array $concepts
   *   The concepts that are to be updated.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public function updateParentsBatch($concepts, $info, &$context) {
    $changed_terms = $context['results']['updated_terms'] + $context['results']['created_terms'];
    $all_terms = $changed_terms + $context['results']['skipped_terms'];
    $parent_values = array();
    $handled_tids = array();
    foreach ($concepts as $concept) {
      $concept_uri = $this->getUri($concept);

      // Check if concept is updated or new.
      if (!isset($changed_terms[$concept_uri])) {
        $context['results']['processed_parents']++;
        continue;
      }

      // If the concept is a top concept then set to the top of the tree.
      $parents = array();
      if (in_array($concept_uri, $info['top_concept_uris'])) {
        $parents[] = 0;
      }

      if (isset($concept['broaders']) && !empty($concept['broaders'])) {
        foreach ($concept['broaders'] as $broader) {
          $broader_uri = $broader . '@' . $concept['ppLang'];
          if (isset($all_terms[$broader_uri])) {
            $parents[] = $all_terms[$broader_uri];
          }
        }
      }

      if (empty($parents)) {
        $parents = array(0);
      }
      foreach ($parents as $parent_tid) {
        $parent_values[] = array(
          'tid' => $all_terms[$concept_uri],
          'parent' => (int) $parent_tid,
        );
      }
      $handled_tids[] = $all_terms[$concept_uri];
      $context['results']['processed_parents']++;
    }

    if (!empty($parent_values)) {
      // Delete old hierarchy values.
      $delete_query = \Drupal::database()->delete('taxonomy_term_hierarchy');
      $delete_query->condition('tid', $handled_tids, 'IN');
      $delete_query->execute();

      // Insert new hierarchy values.
      $query = \Drupal::database()->insert('taxonomy_term_hierarchy')
        ->fields(array('tid', 'parent'));

      foreach ($parent_values as $parent_value) {
        $query->values($parent_value);
      }
      $query->execute();
    }
  }

  /**
   * Batch process method for deleting taxonomy terms.
   *
   * @param string $vid
   *   The taxonomy ID where the terms should be updated.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public function deleteBatch($vid, &$context) {
    $all_terms = $context['results']['updated_terms'] + $context['results']['created_terms'] + $context['results']['skipped_terms'];
    $all_terms = array_values($all_terms);

    $result_query = \Drupal::database()->select('pp_taxonomy_manager_terms', 't');
    $result_query->fields('t', array('tid', 'uri'));
    $result_query->condition('t.tmid', $this->config->id());
    $result_query->condition('t.vid', $vid);

    if (!empty($all_terms)) {
      $result_query->condition('t.tid', $all_terms, 'NOT IN');
    }
    $result = $result_query->execute();

    while ($record = $result->fetch()) {
      $term = Term::load($record->tid);
      $context['results']['deleted_terms'][$record->uri] = $record->tid;
      \Drupal::logger('pp_taxonomy_manager')->notice('Taxonomy term deleted: %name (TID = %tid)', array(
        '%name' => $term->getName(),
        '%tid' => $term->id(),
      ));
      $term->delete();
    }
  }

  /**
   * Deletes the term from the hash table.
   *
   * @param Term $term
   *   The taxonomy term.
   */
  public static function deleteTaxonomyTerm($term) {
    $delete_query = \Drupal::database()->delete('pp_taxonomy_manager_terms');
    $delete_query->condition('vid', $term->getVocabularyId());
    $delete_query->condition('tid', $term->id());
    $delete_query->execute();
  }

  /**
   * Inserts the new statistic log.
   *
   * @param int $vid
   *   The Drupal taxonomy ID.
   * @param int $start_time
   *   The start time of the batch.
   * @param int $end_time
   *   The end time of the batch.
   */
  public function addLog($vid, $start_time, $end_time) {
    $insert_query = \Drupal::database()->insert('pp_taxonomy_manager_logs');
    $insert_query->fields(array(
      'tmid' => $this->config->id(),
      'vid' => $vid,
      'start_time' => $start_time,
      'end_time' => $end_time,
      'uid' => \Drupal::currentUser()->id(),
    ));
    $insert_query->execute();
  }

  /**
   * Deletes all synchronization data.
   *
   * @param int $vid
   *   The Drupal taxonomy ID.
   */
  public function deleteSyncData($vid) {
    // Delete the log data.
    $delete_query = \Drupal::database()->delete('pp_taxonomy_manager_logs');
    $delete_query->condition('tmid', $this->config->id());
    $delete_query->condition('vid', $vid);
    $delete_query->execute();

    // Delete the hash data.
    $delete_query = \Drupal::database()->delete('pp_taxonomy_manager_terms');
    $delete_query->condition('tmid', $this->config->id());
    $delete_query->condition('vid', $vid);
    $delete_query->execute();
  }

  /**
   * Calculates the remaining time of a batch process.
   *
   * @param int $start_time
   *   The start time.
   * @param int $processed
   *   The count of processed items.
   * @param int $total
   *   The total count of items.
   *
   * @return string
   *   The remaining time in a human readable string.
   */
  public function calculateRemainingTime($start_time, $processed, $total) {
    $time_string = '';
    if ($processed > 0) {
      $remaining_time = floor((time() - $start_time) / $processed * ($total - $processed));
      if ($remaining_time > 0) {
        $time_string = (floor($remaining_time / 3600) % 24) . ' hours ' . (floor($remaining_time / 60) % 60) . ' minutes ' . ($remaining_time % 60) . ' seconds';
      }
      else {
        $time_string = t('Done.');
      }
    }

    return $time_string;
  }

  /**
   * Maps a taxonomy term data with a PoolParty concept.
   *
   * @param Term $term
   *   The object of the taxonomy term, which will receive the new detail data.
   * @param object $concept
   *   A concept detail data to update the term with.
   *
   * @return Term
   *   The mapped taxonomy term.
   */
  protected function mapTaxonomyTermDetails($term, $concept) {
    $term->setName($concept['prefLabel']);
    $term->get('field_uri')->setValue($concept['uri']);
    if (isset($concept['definitions'])) {
      $term->setDescription(implode(' ', $concept['definitions']));
    }
    if (isset($concept['altLabels'])) {
      $term->get('field_alt_labels')->setValue(implode(',', $concept['altLabels']));
    }
    if (isset($concept['hiddenLabels'])) {
      $term->get('field_hidden_labels')->setValue(implode(',', $concept['hiddenLabels']));
    }

    // Add data for custom fields.
    if (isset($concept['properties'])) {
      $fields = self::taxonomyFields();
      foreach ($fields as $field_id => $field_schema) {
        if (!in_array($field_id, array(
            'field_uri',
            'field_alt_labels',
            'field_hidden_labels',
            'field_exact_match',
          )) && isset($concept['properties'][$field_schema['property']])
        ) {
          $term->get($field_id)->setValue($concept['properties'][$field_schema['property']]);
        }
      }
    }

    return $term;
  }

  /**
   * Creates a list of concepts from a tree.
   *
   * @param array $tree
   *   A list of concepts in tree format.
   * @param string $drupal_lang
   *   The Drupal language of the concepts.
   * @param string $pp_lang
   *   The PoolParty project language of the concepts.
   * @param int $depth
   *   The depth of the recursive function call.
   *
   * @return array
   *   A list of concept objects.
   */
  protected function tree2list(array $tree, $drupal_lang, $pp_lang, $depth = 0) {
    $concepts = array();
    foreach ($tree as $subtree) {
      if (is_array($subtree) && !empty($subtree['concept'])) {
        // If a concept is in the top level but is not a top concept, then
        // remove its broaders (it's important for other languages if is not
        // translated consistently).
        if ($depth == 0) {
          unset($subtree['concept']['broaders']);
        }
        $subtree['concept']['drupalLang'] = $drupal_lang;
        $subtree['concept']['ppLang'] = $pp_lang;
        $concept_uri = $this->getUri($subtree['concept']);
        $concepts[$concept_uri] = $subtree['concept'];
        if (!empty($subtree['narrowers'])) {
          $tree_list = $this->tree2list($subtree['narrowers'], $drupal_lang, $pp_lang, ($depth + 1));
          $concepts = array_merge($concepts, $tree_list);
        }
      }
    }
    return $concepts;
  }

  /**
   * Inserts the new created concept to the database.
   *
   * @param Term $term
   *   A Drupal taxonomy term.
   * @param string $pp_lang
   *   The PoolParty language used.
   * @param string $uri
   *   The URI with language prefix of a concept.
   * @param string $hash
   *   The new hash data.
   * @param int $start_time
   *   The start time of the batch.
   */
  protected function addHashData($term, $pp_lang, $uri, $hash, $start_time) {
    $insert_query = \Drupal::database()->insert('pp_taxonomy_manager_terms');
    $insert_query->fields(array(
      'tid' => $term->id(),
      'language' => $pp_lang,
      'vid' => $term->getVocabularyId(),
      'tmid' => $this->config->id(),
      'synced' => $start_time,
      'uri' => $uri,
      'hash' => $hash,
    ));
    $insert_query->execute();
  }

  /**
   * Updates the hash data for a taxonomy term.
   *
   * @param Term $term
   *   The taxonomy term.
   * @param string $pp_lang
   *   The PoolParty language used.
   * @param string $hash
   *   The new hash data.
   * @param int $start_time
   *   The synchonization start time.
   */
  protected function updateHashData($term, $pp_lang, $hash, $start_time) {
    $update_query = \Drupal::database()->update('pp_taxonomy_manager_terms');
    $update_query->fields(array(
      'synced' => $start_time,
      'hash' => $hash,
    ));
    $update_query->condition('vid', $term->getVocabularyId());
    $update_query->condition('tid', $term->id());
    $update_query->condition('language', $pp_lang);
    $update_query->execute();
  }

  /**
   * Creates a hash code from a concept $concept.
   *
   * @param object $concept
   *   A concept object from PoolParty.
   *
   * @return string
   *   The hash code.
   */
  protected function hash($concept) {
    return hash('md5', serialize($concept));
  }

  /**
   * Returns all SKOS properties of the taxonomy fields.
   *
   * @return array
   *   List of SKOS properties.
   */
  protected function skosProperties() {
    $fields = self::taxonomyFields();
    $properties = array();
    foreach ($fields as $field) {
      if (isset($field['property'])) {
        $properties[] = $field['property'];
      }
    }

    $properties[] = 'skos:broader';

    return $properties;
  }

  /**
   * Returns the URI with language of a concept.
   *
   * @param object $concept
   *   The object of a concept.
   *
   * @return string
   *   The uri with the language (e.g., http://a.concept.uri/1234@en).
   */
  protected function getUri($concept) {
    return $concept['uri'] . '@' . $concept['ppLang'];
  }

  /**
   * Returns a list of all additional fields for a PoolParty taxonomy.
   *
   * @return array
   *   A list additional fields.
   */
  protected static function taxonomyFields() {
    $taxonomy_field_schema = [
      'field_uri' => [
        'field_name' => 'field_uri',
        'type' => 'link',
        'label' => t('URI'),
        'description' => t('URI of the concept.'),
        'cardinality' => 1,
        'field_settings' => [],
        'required' => TRUE,
        'instance_settings' => [
          'link_type' => LinkItemInterface::LINK_GENERIC,
          'title' => DRUPAL_DISABLED,
        ],
        'widget' => [
          'type' => 'link_default',
          'weight' => 3,
        ],
      ],
      'field_alt_labels' => [
        'field_name' => 'field_alt_labels',
        'type' => 'text',
        'label' => t('Alternative labels'),
        'description' => t('A comma separated list of synonyms.'),
        'cardinality' => 1,
        'field_settings' => [
          'max_length' => 8192,
        ],
        'required' => FALSE,
        'instance_settings' => [],
        'widget' => [
          'type' => 'text_textfield',
          'weight' => 4,
        ],
        'property' => 'skos:altLabel',
      ],
      'field_hidden_labels' => [
        'field_name' => 'field_hidden_labels',
        'type' => 'text',
        'label' => t('Hidden labels'),
        'description' => t('A comma separated list of secondary variants of this term.'),
        'cardinality' => 1,
        'field_settings' => [
          'max_length' => 8192,
        ],
        'required' => FALSE,
        'instance_settings' => [],
        'widget' => [
          'type' => 'text_textfield',
          'weight' => 5,
        ],
        'property' => 'skos:hiddenLabel',
      ],
      'field_exact_match' => [
        'field_name' => 'field_exact_match',
        'type' => 'link',
        'label' => t('Exact matches'),
        'description' => t('URIs which show to the same concept at a different data source.'),
        'cardinality' => -1,
        'field_settings' => [],
        'required' => FALSE,
        'instance_settings' => [
          'link_type' => LinkItemInterface::LINK_GENERIC,
          'title' => DRUPAL_DISABLED,
        ],
        'widget' => [
          'type' => 'link_default',
          'weight' => 6,
        ],
        'property' => 'skos:exactMatch',
      ],
    ];

    // Add the possibility to add custom fields  via hook here.
    $custom_fields = array();
    \Drupal::moduleHandler()->alter('pp_taxonomy_manager_custom_attributes', $custom_fields);
    if (!empty($custom_fields)) {
      foreach ($custom_fields as $field_id => $custom_field) {
        // Check if a property is given and it is not one of the custom_fields
        if (isset($custom_field['property']) && !isset($taxonomy_field_schema[$field_id]) && isset($custom_field['type']) && $custom_field['type'] == 'text') {
          $taxonomy_field_schema[$field_id] = $custom_field;
        }
      }
    }

    return $taxonomy_field_schema;
  }

  /**
   * Creates a machine readable name from a human readable name.
   *
   * @param string $name
   *   The human readable name.
   *
   * @return string
   *   The machine readable name.
   */
  public static function createMachineName($name) {
    $name = strtolower($name);
    return substr(preg_replace(array('@[^a-z0-9_]+@', '@_+@'), '_', $name), 0, 32);
  }

  /**
   * Returns all selected languages with the default language first.
   *
   * @param array $all_languages
   *   An array of languages:
   *    key = Drupal language
   *    value = PoolParty language.
   *
   * @return array
   *   All maped languages with the default language first.
   */
  public static function orderLanguages($all_languages) {
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $languages[$default_language] = $all_languages[$default_language];
    unset($all_languages[$default_language]);
    if (!empty($all_languages)) {
      foreach ($all_languages as $drupal_lang => $pp_lang) {
        if (!empty($pp_lang)) {
          $languages[$drupal_lang] = $pp_lang;
        }
      }
    }

    return $languages;
  }
}