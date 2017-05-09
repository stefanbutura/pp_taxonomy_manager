<?php

/**
 * @file
 * Contains \Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigForm.
 */

namespace Drupal\pp_taxonomy_manager\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\powertagging\Entity\PowerTaggingConfig;
use Drupal\pp_taxonomy_manager\Entity\PPTaxonomyManagerConfig;
use Drupal\semantic_connector\Entity\SemanticConnectorPPServerConnection;
use Drupal\taxonomy\Entity\Vocabulary;

class PPTaxonomyManagerConfigForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var PPTaxonomyManagerConfig $entity */
    $entity = $this->entity;

    $configuration = $entity->getConfig();

    $connection_overrides = \Drupal::config('semantic_connector.settings')->get('override_connections');
    $overridden_values = array();
    if (isset($connection_overrides[$entity->id()])) {
      $overridden_values = $connection_overrides[$entity->id()];
    }

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#description' => t('Name of the PoolParty Taxonomy Manager configuration.'). (isset($overridden_values['title']) ? ' <span class="semantic-connector-overridden-value">' . t('Warning: overridden by variable') . '</span>' : ''),
      '#size' => 35,
      '#maxlength' => 255,
      '#default_value' => $entity->getTitle(),
      '#required' => TRUE,
    );

    /** @var SemanticConnectorPPServerConnection $connection */
    $connection = $entity->getConnection();
    // Get the project title of the currently configured project.
    $project_title = '<invalid project selected>';
    $pp_server_projects = $connection->getApi('PPT')->getProjects();
    foreach ($pp_server_projects as $pp_server_project) {
      if ($pp_server_project->id == $entity->getProjectId()) {
        $project_title = $pp_server_project->title;
      }
    }

    // Add information about the connection.
    $connection_markup = '';
    // Check the PoolParty server version if required.
    if (\Drupal::config('semantic_connector.settings')->get('version_checking')) {
      $version_messages = array();

      $ppx_api_version_info = $connection->getVersionInfo('PPX');
      if (version_compare($ppx_api_version_info['installed_version'], $ppx_api_version_info['latest_version'], '<')) {
        $version_messages[] = t('The connected PoolParty server is not up to date. You are currently running version %installedversion, upgrade to version %latestversion to enjoy the new features.', array('%installedversion' => $ppx_api_version_info['installed_version'], '%latestversion' => $ppx_api_version_info['latest_version']));
      }

      if (!empty($version_messages)) {
        $connection_markup .= '<div class="messages warning"><div class="message">' . implode('</div><div class="message">', $version_messages) . '</div></div>';
      }
    }
    $connection_markup .= '<p id="pp-taxonomy-manager-connection-info">' . t('Connected PoolParty server') . ': <b>' . $connection->getTitle() . ' (' . $connection->getUrl() . ')</b><br />'
      . t('Selected project') . ': <b>' . $project_title . '</b><br />'
      . Link::fromTextAndUrl(t('Change the connected PoolParty server or project'), Url::fromRoute('entity.pp_taxonomy_manager.edit_form', array('pp_taxonomy_manager' => $entity->id())))->toString() . '</p>';
    $form['pp_connection_markup'] = array(
      '#type' => 'markup',
      '#markup' => $connection_markup,
    );

    $connected = array();
    // Get all available Drupal taxonomies.
    $taxonomies = Vocabulary::loadMultiple();

    // Get all taxonomies that are connected with a PowerTagging configuration.
    $powertaggings = array();
    $taxonomy_powertagging = array();
    if (\Drupal::moduleHandler()->moduleExists('powertagging')) {
      $powertagging_configs = PowerTaggingConfig::loadMultiple();
      /** @var PowerTaggingConfig $powertagging_config */
      foreach ($powertagging_configs as $powertagging_config) {
        $powertagging_config_settings = $powertagging_config->getConfig();
        if (isset($powertagging_config_settings['projects'])) {
          $powertaggings[$powertagging_config->id()] = $powertagging_config->getTitle();
          $vid = $powertagging_config_settings['projects'][$powertagging_config->getProjectId()]['taxonomy_id'];
          $taxonomy_powertagging[$vid][] = $powertagging_config->id();
        }
      }
    }

    // Create a table with all existing and possible connections between Drupal
    // taxonomies and PoolParty concepts schemes for the selected project.

    // Create the table rows for all available concept schemes not yet connected.
    $concept_schemes = $entity->getConnection()
      ->getApi('PPT')
      ->getConceptSchemes($entity->getProjectId());
    $rows = array();
    foreach ($concept_schemes as $scheme) {
      $operations = array();
      if (in_array($scheme->uri, $configuration['taxonomies'])) {
        $connected[$scheme->uri] = $scheme;
        continue;
      }

      $operations[] = '&lArr; ' . Link::fromTextAndUrl(t('Import into Drupal'), Url::fromRoute('entity.pp_taxonomy_manager.import', array('config' => $entity->id()), array('query' => array('uri' => $scheme->uri))))->toString();
      $rows[] = array(
        new FormattableMarkup('<div class="semantic-connector-italic">' . t('not yet connected') . '</div>', array()),
        new FormattableMarkup(implode(' | ', $operations), array()),
        Link::fromTextAndUrl($scheme->title, Url::fromUri($scheme->uri, array('attributes' => array('title' => ((isset($scheme->descriptions) && !empty($scheme->descriptions)) ? $scheme->descriptions[0] : NULL))))),
      );
    }

    // Create the table rows for all connected and disconnected Drupal taxonomies.
    /** @var Vocabulary $taxonomy */
    foreach ($taxonomies as $taxonomy) {
      $operations = array();
      // Show only taxonomies that are not already connected with a
      // PoolParty project via PowerTagging.
      if (!isset($taxonomy_powertagging[$taxonomy->id()])) {
        if (isset($configuration['taxonomies'][$taxonomy->id()])) {
          // Create rows from connected taxonomies.
          if (isset($connected[$configuration['taxonomies'][$taxonomy->id()]])) {
            $scheme = $connected[$configuration['taxonomies'][$taxonomy->id()]];
            $operations[] = '&lArr; ' . Link::fromTextAndUrl(t('Sync from PoolParty'), Url::fromRoute('entity.pp_taxonomy_manager.sync' , array('config' => $entity->id(), 'taxonomy' => $taxonomy->id())))->toString();
            $operations[] = Link::fromTextAndUrl(t('Disconnect from PoolParty'), Url::fromRoute('entity.pp_taxonomy_manager.disconnect' , array('config' => $entity->id(), 'taxonomy' => $taxonomy->id())))->toString() . ' &rArr;';
            $concept_scheme = Link::fromTextAndUrl($scheme->title, Url::fromUri($scheme->uri, array(
              'absolut' => TRUE,
              'attributes' => array('title' => ((isset($scheme->descriptions) && !empty($scheme->descriptions)) ? $scheme->descriptions[0] : NULL)),
            )));
          }
          else {
            $operations[] = Link::fromTextAndUrl(t('Disconnect from PoolParty'), Url::fromRoute('entity.pp_taxonomy_manager.disconnect' , array('config' => $entity->id(), 'taxonomy' => $taxonomy->id())))->toString() . ' &rArr;';
            $concept_scheme = t('The concept scheme could not be found in PoolParty.<br />Make sure to delete the connection if the concept scheme was deleted on purpose.');
          }
          $rows[] = array(
            Link::fromTextAndUrl($taxonomy->label(), Url::fromRoute('entity.taxonomy_vocabulary.edit_form', array('taxonomy_vocabulary' => $taxonomy->id()), array('attributes' => array('title' => $taxonomy->getDescription())))),
            new FormattableMarkup(implode(' | ', $operations), array()),
            $concept_scheme,
          );
        }
        else {
          // Create rows from disconnected taxonomies.
          $operations[] = Link::fromTextAndUrl(t('Export to PoolParty'), Url::fromRoute('entity.pp_taxonomy_manager.export' , array('config' => $entity->id(), 'taxonomy' => $taxonomy->id())))->toString() . ' &rArr;';
          $rows[] = array(
            Link::fromTextAndUrl($taxonomy->label(), Url::fromRoute('entity.taxonomy_vocabulary.edit_form', array('taxonomy_vocabulary' => $taxonomy->id()), array('attributes' => array('title' => $taxonomy->getDescription())))),
            new FormattableMarkup(implode(' | ', $operations), array()),
            new FormattableMarkup('<div class="semantic-connector-italic">' . t('not yet connected') . '</div>', array()),
          );
        }
      }
    }

    // Create the table for the connections.
    $table = array();
    $table['connections'] = array(
      '#theme' => 'table',
      '#header' => array(
        t('Drupal taxonomy'),
        t('Operations'),
        t('PoolParty concept scheme'),
      ),
      '#rows' => $rows,
      '#attributes' => array(
        'id' => 'pp-taxonomy-manager-interconnection-table',
        'class' => array('semantic-connector-tablesorter'),
      ),
    );

    $form['connections'] = array(
      '#type' => 'item',
      '#title' => '<h3 class="semantic-connector-table-title">' . t('Interconnection between the Drupal taxonomies and the PoolParty concept schemes') . '</h3>',
      '#markup' => \Drupal::service('renderer')->render($table),
    );

    // Create a table with taxonomies already connected with a PoolParty project
    // via the PowerTagging module.

    $rows = array();
    foreach ($taxonomies as $taxonomy) {
      // Show only taxonomies that are already connected with a
      // PoolParty project via PowerTagging.
      if (isset($taxonomy_powertagging[$taxonomy->id()])) {
        $powertagging_links = array();
        $powertagging_update_links = array();
        foreach ($taxonomy_powertagging[$taxonomy->id()] as $powertagging_id) {
          $powertagging_links[] = Link::fromTextAndUrl($powertaggings[$powertagging_id], Url::fromRoute('entity.powertagging.edit_config_form', array('powertagging' => $powertagging_id)));
          $link_name = t('Update the taxonomy from "@powertagging"', array('@powertagging' => $powertaggings[$powertagging_id]));
          $powertagging_update_links[] = Link::fromTextAndUrl($link_name, Url::fromRoute('powertagging.update_taxonomy', array('powertagging' => $powertagging_id), array('query' => array('destination' => 'admin/config/semantic-drupal/pp-taxonomy-manager/' . $entity->id()))));
        }
        if (isset($configuration['taxonomies'][$taxonomy->id()])) {
          // Create rows from connected taxonomies created with this module.
          $rows[] = array(
            Link::fromTextAndUrl($taxonomy->label(), Url::fromRoute('entity.taxonomy_vocabulary.edit_form', array('taxonomy_vocabulary' => $taxonomy->id()), array('attributes' => array('title' => $taxonomy->getDescription())))),
            new FormattableMarkup('<div class="item-list"><ul><li>' . implode('</li><li>', $powertagging_links) . '</li></ul></div>', array()),
            Link::fromTextAndUrl(t('Delete Synchronizer connection'), Url::fromRoute('entity.pp_taxonomy_manager.disconnect' , array('config' => $entity->id(), 'taxonomy' => $taxonomy->id()))),
          );
        }
        else {
          // Create rows from taxonomies that are not connected with this module.
          $rows[] = array(
            Link::fromTextAndUrl($taxonomy->label(), Url::fromRoute('entity.taxonomy_vocabulary.edit_form', array('taxonomy_vocabulary' => $taxonomy->id()), array('attributes' => array('title' => $taxonomy->getDescription())))),
            new FormattableMarkup('<div class="item-list"><ul><li>' . implode('</li><li>', $powertagging_links) . '</li></ul></div>', array()),
            new FormattableMarkup('<div class="item-list"><ul><li>' . implode('</li><li>', $powertagging_update_links) . '</li></ul></div>', array()),
          );
        }
      }
    }

    if (count($rows)) {
      $table = array();
      $table['powertagging'] = array(
        '#theme' => 'table',
        '#header' => array(
          t('Drupal taxonomy'),
          t('PowerTagging configurations'),
          t('Operation'),
        ),
        '#rows' => $rows,
        '#attributes' => array(
          'id' => 'pp-taxonomy-manager-powertagging-table',
          'class' => array('semantic-connector-tablesorter'),
        ),
      );

      $form['powertagging'] = array(
        '#type' => 'item',
        '#title' => '<h3 class="semantic-connector-table-title">' . t('Drupal taxonomies already connected with a PoolParty project via PowerTagging module') . '</h3>',
        '#markup' => \Drupal::service('renderer')->render($table),
      );
    }


    // Add CSS and JS.
    $form['#attached'] = array(
      'library' =>  array(
        'pp_taxonomy_manager/admin_area',
        'semantic_connector/tablesorter',
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var PPTaxonomyManagerConfig $entity */
    $entity = $this->entity;

    // Update and save the entity.
    $entity->set('title', $form_state->getValue('title'));
    $entity->save();

    drupal_set_message(t('The connection for PoolParty Taxonomy Manager configuration %title has been updated.', array('%title' => $entity->getTitle())));
    $form_state->setRedirectUrl(Url::fromRoute('entity.pp_taxonomy_manager.collection'));
  }
}