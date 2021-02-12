<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Changes session config service defn if the master request came from SSP.
 *
 * This change is made to ensure simpleSAMLphp uses the correct session name. By
 * default, the session name changes depending on the directory of the PHP file
 * serving the request (e.g., index.php is in the document root, but a request
 * coming from simpleSAMLphp will not be). To ensure simpleSAMLphp can detect
 * the Drupal session, we alter the arguments passed to the session manager so
 * that our own session configuration class is used.
 */
class Drupalauth4sspServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Check to see if we are coming from simpleSAMLphp.
    global $isDrupalRunningFromSimpleSamlPhp;
    if ($isDrupalRunningFromSimpleSamlPhp) {
      // If so, try to alter the session config definition.
      if ($container->hasDefinition('session')) {
        $sessionConfigurationDefinition = $container->getDefinition('session_configuration');
        $definition->setClass('Drupal\drupalauth4ssp\SessionConfiguration\AutoSessionNameSessionConfiguration');
      }
    }
  }

}
