<?php

/**
 * @file
 * A collection of methods required for batches of the PoolParty Taxonomy Manager.
 */

namespace Drupal\pp_taxonomy_manager;
use Drupal\taxonomy\Entity\Term;

/**
 * A collection of static functions offered by the PoolParty Taxonomy Manager module.
 */
class PPTaxonomyManagerBatches {
  /**
   * Batch process function for exporting taxonomy terms into a PoolParty server.
   *
   * @param PPTaxonomyManager $manager
   *   The PoolParty Taxonomy Manager object.
   * @param Term[] $terms
   *   The taxonomy terms that are to be exported.
   * @param string $drupal_lang
   *   The language of the taxonomy terms that are to be exported.
   * @param string $pp_lang
   *   The language of the concept that are to be created.
   * @param string $scheme_uri
   *   The concept scheme URI where the terms should be created.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public static function exportTerms($manager, array $terms, $drupal_lang, $pp_lang, $scheme_uri, array $info, array &$context) {
    if (!isset($context['results']['processed'])) {
      $context['results']['processed'] = 0;
      $context['results']['hash_update_processed'] = 0;
      $context['results']['translation_processed'] = 0;
      $context['results']['exported_terms'] = array(
        array('uri' => $scheme_uri, 'parents' => array()),
      );
    }

    $manager->exportBatch($terms, $drupal_lang, $pp_lang, $context);
    $remaining_time = $manager->calculateRemainingTime($info['start_time'], $context['results']['processed'], $info['total']);

    // Show the remaining time as a batch message.
    $context['message'] = t('Processed terms for creating the concepts: %processed of %total.', array(
        '%processed' => $context['results']['processed'],
        '%total' => $info['total'],
      )) . '<br />' . t('Remaining time: %remaining_time.', array('%remaining_time' => $remaining_time));
  }

  /**
   * Batch process finished function for exporting taxonomy terms.
   */
  public static function exportTermsFinished($success, $results, $operations) {
    if ($success) {
      $message = t('The export of %processed terms is completed successfully.', array(
        '%processed' => (isset($results['processed']) ? $results['processed'] : 0),
      ));
      drupal_set_message($message);
    }
    else {
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', array(
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE)
      ));
      drupal_set_message($message, 'error');
    }
  }

  /**
   * Batch process function: updating the hash table after the export.
   *
   * @param PPTaxonomyManager $manager
   *   The PoolParty Taxonomy Manager object.
   * @param Term[] $terms
   *   The taxonomy terms that are to be exported.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public static function updateTermHashes($manager, array $terms, array $info, array &$context) {
    $manager->updateHashBatch($terms, $info, $context);
    $remaining_time = $manager->calculateRemainingTime($info['start_time'], $context['results']['hash_update_processed'], $info['total']);

    // Show the remaining time as a batch message.
    $context['message'] = t('Processed terms for adding connection to the concepts: %processed of %total.', array(
        '%processed' => $context['results']['hash_update_processed'],
        '%total' => $info['total'],
      )) . '<br />' . t('Remaining time: %remaining_time.', array('%remaining_time' => $remaining_time));
  }

  /**
   * Batch process function for exporting the translations of the taxonomy terms.
   *
   * @param \Drupal\pp_taxonomy_manager\PPTaxonomyManager $manager
   *   The PoolParty Taxonomy Manager object.
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
  public static function exportTermTranslations($manager, array $terms, $drupal_lang, $pp_lang, array $info, array &$context) {
    $manager->exportTranslationsBatch($terms, $drupal_lang, $pp_lang, $info, $context);
    $remaining_time = $manager->calculateRemainingTime($info['start_time'], $context['results']['translation_processed'], $info['total']);

    // Show the remaining time as a batch message.
    $context['message'] = t('Processed terms for adding the translations: %processed of %total.', array(
        '%processed' => $context['results']['translation_processed'],
        '%total' => $info['total'],
      )) . '<br />' . t('Remaining time: %remaining_time.', array('%remaining_time' => $remaining_time));
  }

  /**
   * Batch process function for updating taxonomy terms from a PoolParty server.
   *
   * @param \Drupal\pp_taxonomy_manager\PPTaxonomyManager $manager
   *   The PoolParty Taxonomy Manager object.
   * @param array $concepts
   *   The concepts that are to be updated.
   * @param string $pp_lang
   *   The PoolParty language of the concepts.
   * @param int $vid
   *   The taxonomy ID where the terms should be updated.
   * @param string $machine_name
   *   The taxonomy machine_name where the terms should be updated.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public static function updateTerms($manager, array $concepts, $pp_lang, $vid, $machine_name, array $info, array &$context) {
    if (!isset($context['results']['processed'])) {
      $context['results']['processed'] = 0;
      $context['results']['processed_parents'] = 0;
      $context['results']['created_terms'] = array();
      $context['results']['updated_terms'] = array();
      $context['results']['skipped_terms'] = array();
      $context['results']['deleted_terms'] = array();
    }

    $manager->updateBatch($concepts, $pp_lang, $vid, $machine_name, $info, $context);
    $remaining_time = $manager->calculateRemainingTime($info['start_time'], $context['results']['processed'], $info['total']);

    // Show the remaining time as a batch message.
    $context['message'] = t('Processed concepts for creating/updating the terms: %processed of %total (created: %created, updated: %updated, skipped: %skipped).', array(
        '%processed' => $context['results']['processed'],
        '%total' => $info['total'],
        '%created' => count($context['results']['created_terms']),
        '%updated' => count($context['results']['updated_terms']),
        '%skipped' => count($context['results']['skipped_terms']),
      )) . '<br />' . t('Remaining time: %remaining_time.', array('%remaining_time' => $remaining_time));
  }

  /**
   * Batch process finished function for updating taxonomy terms.
   */
  public static function updateTermsFinished($success, $results, $operations) {
    if ($success) {
      $message = t('The update of %processed terms is completed successfully (updated: %updated, created: %created, deleted: %deleted, skipped: %skipped).', array(
        '%processed' => (isset($results['processed']) ? $results['processed'] : 0),
        '%updated' => (isset($results['updated_terms']) ? count($results['updated_terms']) : 0),
        '%created' => (isset($results['created_terms']) ? count($results['created_terms']) : 0),
        '%deleted' => (isset($results['deleted_terms']) ? count($results['deleted_terms']) : 0),
        '%skipped' => (isset($results['skipped_terms']) ? count($results['skipped_terms']) : 0),
      ));
      drupal_set_message($message);
    }
    else {
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', array(
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE)
      ));
      drupal_set_message($message, 'error');
    }
  }

  /**
   * Batch process function for updating taxonomy terms from a PoolParty server.
   *
   * @param \Drupal\pp_taxonomy_manager\PPTaxonomyManager $manager
   *   The PoolParty Taxonomy Manager object.
   * @param array $concepts
   *   The concepts that are to be updated.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public static function updateTermParents($manager, array $concepts, array $info, array &$context) {
    $manager->updateParentsBatch($concepts, $info, $context);
    $remaining_time = $manager->calculateRemainingTime($info['start_time'], $context['results']['processed_parents'], $info['total']);

    // Show the remaining time as a batch message.
    $context['message'] = t('Processed concepts for building the taxonomy tree: %processed of %total.', array(
        '%processed' => $context['results']['processed'],
        '%total' => $info['total'],
      )) . '<br />' . t('Remaining time: %remainingtime.', array('%remainingtime' => $remaining_time));
  }

  /**
   * Batch process function for deleting taxonomy terms.
   *
   * @param object $manager
   *   The PoolParty Taxonomy Manager object.
   * @param int $vid
   *   The taxonomy ID where the terms should be deleted.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public static function deleteVocabulary($manager, $vid, array &$context) {
    $manager->deleteBatch($vid, $context);

    // Show the batch message.
    $context['message'] = t('Removing taxonomy terms for all deleted concepts.');
  }

  /**
   * Batch process function to add statistic logs.
   *
   * @param object $manager
   *   The PoolParty Taxonomy Manager object.
   * @param int $vid
   *   The taxonomy ID where the terms should be updated.
   * @param array $info
   *   An associative array of information about the batch process.
   * @param array $context
   *   The batch context to transmit data between different calls.
   */
  public static function saveVocabularyLog($manager, $vid, array $info, array &$context) {
    $end_time = time();
    $manager->addLog($vid, $info['start_time'], $end_time);
  }
}