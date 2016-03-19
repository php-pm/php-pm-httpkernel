<?php

namespace PHPPM\Bootstraps;

use Stack\Builder;

/**
 * A default bootstrap for the Laravel framework
 */
class Laravel implements BootstrapInterface
{
    /**
     * @var string|null The application environment
     */
    protected $appenv;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * Store the application
     *
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $app;

    /**
     * Instantiate the bootstrap, storing the $appenv
     * @param string|null $appenv The environment your application will use to bootstrap (if any)
     */
    public function __construct($appenv, $debug)
    {
        $this->appenv = $appenv;
        $this->debug = $debug;
        putenv("APP_DEBUG=" . ($debug ? 'true' : 'false'));
        putenv("APP_ENV=" . $this->appenv);
    }

    /**
     * @return string
     */
    public function getStaticDirectory() {
        return 'public/';
    }

    /**
     * Create a Laravel application
     */
    public function getApplication()
    {

        if (file_exists('bootstrap/autoload.php')) {
            require_once 'bootstrap/autoload.php';
        }

        // Laravel 5 / Lumen
        if (file_exists('bootstrap/app.php')) {
            $this->app = require_once 'bootstrap/app.php';
        }

        // Laravel 4
        if (file_exists('bootstrap/start.php')) {
            $this->app = require_once 'bootstrap/start.php';
        }

        if (!$this->app) {
            throw new \RuntimeException('Laravel bootstrap file not found');
        }

        $this->app->boot();

        return $this->app;
    }
}
