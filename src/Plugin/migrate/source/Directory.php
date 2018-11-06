<?php

namespace Drupal\migrate_7x_claw\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;

/**
 * Source plugin for retrieving files from directories.
 *
 * @MigrateSource(
 *   id = "directory"
 * )
 */
class Directory extends Url {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $files = [];

    foreach ($configuration['urls'] as $url) {
      $dir_iterator = new \RecursiveDirectoryIterator($url, \FilesystemIterator::SKIP_DOTS);
      $file_iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
     
      foreach ($file_iterator as $file_info) {
        if ($file_info->isFile()) {
          if (isset($configuration['extensions']) && !empty($configuration['extensions'])) {
            $ext = $file_info->getExtension();
            if (in_array($ext, $configuration['extensions'])) {
              $files[] = $file_info->getPathname();
            }
          }
          else {
            $files[] = $file_info->getPathname();
          }
        }
      }
    }

    $configuration['urls'] = $files;

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

}
