<?php

namespace PHPPM\Bootstraps;

/**
 * A default bootstrap for the Laravel framework
 */
class Laravel implements BootstrapInterface, HooksInterface, RequestClassProviderInterface,
    ApplicationEnvironmentAwareInterface
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
     *
     * @param string|null $appenv The environment your application will use to bootstrap (if any)
     * @param $debug
     */
    public function initialize($appenv, $debug)
    {
        $this->appenv = $appenv;
        $this->debug = $debug;
        putenv("APP_DEBUG=" . ($debug ? 'true' : 'false'));
        putenv("APP_ENV=" . $this->appenv);
    }

    /**
     * {@inheritdoc}
     */
    public function requestClass() {
        return '\Illuminate\Http\Request';
    }

    /**
     * Create a Laravel application
     */
    public function getApplication()
    {
        if (file_exists('bootstrap/autoload.php')) {
            require_once 'bootstrap/autoload.php';
        } else if (file_exists('vendor/autoload.php')) {
            require_once 'vendor/autoload.php';
        }

        // Laravel 5 / Lumen
        $isLaravel = true;
        if (file_exists('bootstrap/app.php')) {
            $this->app = require_once 'bootstrap/app.php';
            if (substr($this->app->version(), 0, 5) === 'Lumen') {
                $isLaravel = false;
            }
        }

        // Laravel 4
        if (file_exists('bootstrap/start.php')) {
            $this->app = require_once 'bootstrap/start.php';
            $this->app->boot();
            
            return $this->app;
        }

        if (!$this->app) {
            throw new \RuntimeException('Laravel bootstrap file not found');
        }

        $kernel = $this->app->make($isLaravel ? 'Illuminate\Contracts\Http\Kernel' : 'Laravel\Lumen\Application');

        return $kernel;
    }

    /**
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function preHandle($app)
    {
        //reset const LARAVEL_START, to get the correct timing
    }

    /**
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function postHandle($app)
    {
        //reset debugbar if available
    }
}
