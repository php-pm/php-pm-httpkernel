<?php


namespace PHPPM\Bootstraps;


class Lumen implements BootstrapInterface, HooksInterface, RequestClassProviderInterface, ApplicationEnvironmentAwareInterface
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
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $app;

    /**
     * Instantiate the bootstrap, storing the $appenv
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
    public function getStaticDirectory()
    {
        return 'public/';
    }

    /**
     * {@inheritdoc}
     */
    public function requestClass()
    {
        return '\Illuminate\Http\Request';
    }

    /**
     * Create a Laravel application
     */
    public function getApplication()
    {
        //  Lumen
        if ( ! file_exists('bootstrap/app.php')) {
            throw new \RuntimeException('Laravel bootstrap file not found');
        }

        $this->app = require_once 'bootstrap/app.php';

        return $this->app->make('Laravel\Lumen\Application');
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