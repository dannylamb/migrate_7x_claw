<?php

namespace Drupal\migrate_7x_claw\Plugin\migrate_plus\data_parser;

use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\DataParserPluginBase;

/**
 * Obtain pathinfo() data for files.
 *
 * @DataParser(
 *   id = "pathinfo",
 *   title = @Translation("Path Info")
 * )
 */
class PathInfo extends DataParserPluginBase {

  /**
   * Path info results on the url.
   *
   * @var array 
   */
  protected $path_info;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $configuration['item_selector'] = '';
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl($url) {
    $this->path_info = pathinfo($url);
    $this->path_info['fullpath'] = $url;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
      $this->currentItem = $this->path_info;
      $this->path_info = NULL;
  }

}
