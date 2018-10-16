<?php

namespace Drupal\migrate_7x_claw\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Xml;

/**
 * Obtain XML data for migration using the XMLReader pull parser.
 *
 * @DataParser(
 *   id = "authenticated_xml",
 *   title = @Translation("Authenticated XML")
 * )
 */
class AuthenticatedXml extends Xml {

  /**
   * Update the configuration for the dataparserplugin.
   *
   * The XML dataParserPlugin assumes you give it all the URLs to start,
   * but I am dynamically generating them based on the batch.
   *
   * @param array|string $urls
   *   New array of URLs to add to the FedoraDatastream processor.
   */
  public function updateUrls($urls) {
    if (!is_array($urls)) {
      $urls = [$urls];
    }
    $this->urls = $urls;
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl($url) {
    // (Re)open the provided URL.
    $this->reader->close();

    // Clear XML error buffer. Other Drupal code that executed during the
    // migration may have polluted the error buffer and could create false
    // positives in our error check below. We are only concerned with errors
    // that occur from attempting to load the XML string into an object here.
    libxml_clear_errors();

    if (is_null($url)) {
      // No URL means no source.
      return FALSE;
    }

    // Get the XML using the data fetcher to allow us to access URLs requiring
    // authentication.
    $xml = $this->getDataFetcherPlugin()
      ->getResponseContent($url)
      ->getContents();

    // Splice in managed XML datastreams.
    // NOTE: This gives the datastreams a namespace
    // of "default".
    $xml = $this->insertManagedXml($xml, $url);
//echo($xml);
    return $this->reader->XML($xml, NULL, \LIBXML_NOWARNING);
  }

  protected function insertManagedXml($foxml, $url) {
    $doc = new \DomDocument();
    $doc->loadXML($foxml);

    $xpath = new \DOMXpath($doc);
    $xpath->registerNamespace("foxml", "info:fedora/fedora-system:def/foxml#");
    $xpath->registerNamespace("mods", "http://www.loc.gov/mods/v3");
    
    // Get the PID
    $pid = $xpath->query("/foxml:digitalObject/@PID", $doc)->item(0)->nodeValue;

    // Get the latest datastream versions that are managed and xml. 
    $versions = $xpath->query("/foxml:digitalObject/foxml:datastream[@CONTROL_GROUP = \"M\" ]/foxml:datastreamVersion[position() = last() and @MIMETYPE = \"application/xml\"]", $doc);

    // Get rid of the /objectXML at the end of the url, so we can append
    // /datastreams/DSID/content to the end instead.
    $url = trim($url, "/objectXML");

    foreach ($versions as $version) {
      // The ID of each datastreamVersion element is of the form DSID.VID.
      // Hack apart the DSID and version number.
      $ds_vid = $version->getAttribute("ID");
      $ds_vid = explode('.', $ds_vid);
      $dsid = $ds_vid[0];
      $vid = $ds_vid[1];

      // Request the XML datastream from Fedora.
      $ds_xml = $this->getDataFetcherPlugin()
      ->getResponseContent($url . '/datastreams/' . $dsid . '/content')
      ->getContents();
    
      // Hack out the root of the datastream XML.
      $ds_doc = new \DomDocument();
      $ds_doc->loadXML($ds_xml);
      $ds_root = $ds_doc->documentElement;

      // Splice it into the datastreamVersion.
      $xml_content = $doc->createElement("foxml:xmlContent");
      $xml_content->appendChild($doc->importNode($ds_root, TRUE));
      $version->appendChild($xml_content);
    }

    return $doc->saveXml();
  }

  /**
   * {@inheritdoc}
   *
   * Islandora Source can provide 0 urls, we need to exit or it throws an
   * error.
   */
  protected function nextSource() {
    if (count($this->urls) == 0) {
      return FALSE;
    }
    return parent::nextSource();
  }

}
