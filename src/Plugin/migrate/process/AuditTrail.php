<?php

namespace Drupal\migrate_7x_claw\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parses the AUDIT datastream (a.k.a. 'audit trail') out of Fedora 3.x FOXML.
 *
 * @MigrateProcessPlugin(
 *   id = "audit_trail"
 * )
 * @package Drupal\migrate_7x_claw\Plugin\process
 */
class AuditTrail extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Guzzle HTTP Client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Http client authentication.
   *
   * @var array
   */
  protected $auth = [];

  /**
   * The URI of your Fedora instance.
   *
   * @var string
   */
  protected $fedoraUri;

  /**
   * Constructs a download process plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP client.
   *
   * @throws \Drupal\migrate\MigrateException
   *   On configuration errors.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, Client $http_client) {
    $configuration += [
      'guzzle_options' => [],
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    if (!isset($configuration['settings']['fedora_base_url'])) {
      throw new MigrateException("The fedora_datastream plugin requires a settings: key with a fedora_base_url key of your Fedora Base URI.");
    }
    $this->fedoraUri = $configuration['settings']['fedora_base_url'];
    if (isset($configuration['settings']['authentication'])) {
      $this->auth = [];
      $this->auth[] = $configuration['settings']['authentication']['username'];
      $this->auth[] = $configuration['settings']['authentication']['password'];
      if (isset($configuration['settings']['authentication']['plugin'])) {
        $this->auth[] = (isset($configuration['settings']['authentication']['plugin']) ?
        $configuration['settings']['authentication']['plugin'] :
        $configuration['settings']['authentication']['type']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    error_log(var_export($row), 3, '/tmp/audit_trail.log');
    $pid = $row->getSourceIdValues()['PID'];

    // Parse the AUDIT datastream out of the retrieved FOXML.
    if (isset($fetch) && isset($dsid)) {
      $foxml = self::getDatastream($pid);
      try {
        $dom = new \DOMDocument();
        $dom->loadXML($foxml);
        foreach ($dom->getElementsByTagNameNS('info:fedora/fedora-system:def/audit#', 'auditTrail') as $auditTrail) {
          $audit_trail_xml = $dom->saveXML($auditTrail);
        }
        // during development
        error_log($audit_trail_xml, 3, '/tmp/audit.log');
        return $audit_trail_xml;
      }
      catch (\Exception $e) {
        throw new MigrateException('Could not process FOXML.');
      }
    }

    return "";
  }

  /**
   * Get the datastream from Fedora 3.
   *
   * @param string $pid
   *   The PID of the remote object.
   *
   * @return string
   *   The contents of the exported FOXML.
   */
  private function getDatastream($pid) {
    $uri = $this->fedoraUri . '/objects/' . $pid . '/export';
    try {
      $response = $this->httpClient->get($uri, ['auth' => $this->auth]);
      return $response->getBody()->getContents();
    }
    catch (\Exception $e) {
      throw new MigrateException('Could not retrieve FOXML from Fedora.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return FALSE;
  }

}