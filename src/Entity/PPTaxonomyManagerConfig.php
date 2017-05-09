<?php

/**
 * @file Contains \Drupal\pp_taxonomy_manager\Entity\PPTaxonomyManagerConfig.
 */

namespace Drupal\pp_taxonomy_manager\Entity;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\semantic_connector\SemanticConnector;

/**
 * @ConfigEntityType(
 *   id ="pp_taxonomy_manager",
 *   label = @Translation("PoolParty Taxonomy Manager configuration"),
 *   handlers = {
 *     "list_builder" = "Drupal\pp_taxonomy_manager\PPTaxonomyManagerConfigListBuilder",
 *     "form" = {
 *       "default" = "Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigConnectionForm",
 *       "add" = "Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigConnectionForm",
 *       "edit" = "Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigConnectionForm",
 *       "edit_config" = "Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigForm",
 *       "delete" = "Drupal\pp_taxonomy_manager\Form\PPTaxonomyManagerConfigDeleteForm",
 *     }
 *   },
 *   config_prefix = "pp_taxonomy_manager",
 *   admin_permission = "administer pp_taxonomy_manager",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title"
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/semantic-drupal/pp-taxonomy-manager/configurations/{pp_taxonomy_manager}/delete",
 *     "edit-form" = "/admin/config/semantic-drupal/pp-taxonomy-manager/configurations/{pp_taxonomy_manager}",
 *     "collection" = "/admin/config/semantic-drupal/pp-taxonomy-manager/",
 *   },
 *   config_export = {
 *     "title",
 *     "id",
 *     "connection_id",
 *     "project_id",
 *     "config",
 *   }
 * )
 */
class PPTaxonomyManagerConfig extends ConfigEntityBase implements PPTaxonomyManagerConfigInterface {
  protected $id;
  protected $project_id;
  protected $title;
  protected $connection_id;
  protected $connection;
  protected $config;

  /**
   * Constructor of the SonrWebminingConfigurationSet-class.
   *
   * {@inheritdoc|}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    if (is_null($this->id())) {
      $this->connection_id = 0;
      $this->config = array();
    }
    else {
      /*$connection_overrides = \Drupal::config('semantic_connector.settings')->get('override_connections');
      if (isset($connection_overrides[$this->id()])) {
        $overrides = $connection_overrides[$this->id()];
        if (isset($overrides['connection_id'])) {
          $this->connection_id = $overrides['connection_id'];
        }
        if (isset($overrides['project_id'])) {
          $this->project_id = $overrides['project_id'];
        }
        if (isset($overrides['title'])) {
          $this->title = $overrides['title'];
        }
      }*/
    }

    $this->connection = SemanticConnector::getConnection('pp_server', $this->connection_id);

    // Merge the Config with the default ones.
    $this->config = $this->config + self::getDefaultConfig();
  }

  /**
   * {@inheritdoc|}
   */
  public function getProjectId() {
    return $this->project_id;
  }

  /**
   * {@inheritdoc|}
   */
  public function setProjectId($project_id) {
    $this->project_id = $project_id;
  }

  /**
   * {@inheritdoc|}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc|}
   */
  public function setTitle($title) {
    $this->title = $title;
  }

  /**
   * {@inheritdoc|}
   */
  public function getConnectionId() {
    return $this->connection_id;
  }

  /**
   * {@inheritdoc|}
   */
  public function setConnectionId($connection_id) {
    $this->connection_id = $connection_id;
    $this->connection = SemanticConnector::getConnection('pp_server', $this->connection_id);
  }

  /**
   * {@inheritdoc|}
   */
  public function getConnection() {
    return $this->connection;
  }

  /**
   * {@inheritdoc|}
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * {@inheritdoc|}
   */
  public function setConfig($config) {
    $this->config = $config + self::getDefaultConfig();
  }

  /**
   * {@inheritdoc|}
   */
  public static function getDefaultConfig() {
    return array(
      'taxonomies' => array(),
    );
  }

  /**
   * Helper function to check whether an pp_taxonomy_manager entity with a specific
   * ID exists.
   *
   * @param string $id
   *   The ID to check if there is an entity for.
   *
   * @return bool
   *   TRUE if an entity with this ID already exists, FALSE if not.
   */
  public static function exist($id) {
    $entity_count = \Drupal::entityQuery('pp_taxonomy_manager')
      ->condition('id', $id)
      ->count()
      ->execute();
    return (bool) $entity_count;
  }
}