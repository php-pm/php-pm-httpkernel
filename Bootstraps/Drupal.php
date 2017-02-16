<?php

namespace PHPPM\Bootstraps;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * A PHP-PM bootstrap for the Drupal framework.
 *
 * @see \PHPPM\Bootstraps\Symfony
 * @see \PHPPM\Bridges\HttpKernel
 */
class Drupal implements BootstrapInterface, ApplicationEnvironmentAwareInterface
{
    /**
     * The PHP environment in which to bootstrap (such as 'dev' or 'production').
     *
     * @var string|null
     */
    protected $appenv;

    /**
     * @var boolean
     */
    protected $debug;

    /**
     * Instantiate the bootstrap, storing the $appenv
     *
     * @param $appenv
     * @param $debug
     */
    public function initialize($appenv, $debug)
    {
        $this->appenv = $appenv;
        $this->debug = $debug;
    }

    /**
     * @return string
     */
    public function getStaticDirectory() {
        return './';
    }

    /**
     * Create a Drupal application.
     */
    public function getApplication()
    {
        //load drupals autoload.php, so their classes are available
        $autoloader = require './vendor/autoload.php';

        $sitePath = 'sites/default';

        Settings::initialize('./', $sitePath, $autoloader);

        $app = new DrupalKernel($this->appenv, $autoloader);
        $app->setSitePath($sitePath);

        return $app;
    }
}