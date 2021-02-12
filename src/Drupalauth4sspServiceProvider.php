<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Changes session config service definition.
 *
 * This change is made to ensure simpleSAMLphp uses the correct session name. By
 * default, the session name changes depending on the directory of the PHP file
 * serving the request (e.g., index.php is in the document root, but a request
 * coming from simpleSAMLphp will not be). To ensure simpleSAMLphp can detect
 * the Drupal session, we alter the arguments passed to the session manager so
 * that our own session configuration class is used, which changes how the
 * session name is determined if we are running from simpleSAMLphp.
 */
class Drupalauth4sspServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Try to alter the session config definition.
    if ($container->hasDefinition('session')) {
      $sessionConfigurationDefinition = $container->getDefinition('session_configuration');
      $sessionConfigurationDefinition->setClass('Drupal\drupalauth4ssp\SessionConfiguration\AutoSessionNameSessionConfiguration');
    }
  }

}
