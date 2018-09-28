<?php

namespace PHPPM\Bootstraps;

/**
 * A default bootstrap for the Laravel framework
 */
class Laravel implements
    BootstrapInterface,
    HooksInterface,
    RequestClassProviderInterface,
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
     * @var \Illuminate\Foundation\Application|null
     */
    protected $app;

    /**
     * Laravel Application->register() parameter count
     *
     * @var int
     */
    private $appRegisterParameters;

    /**
     * Instantiate the bootstrap, storing the $appenv
     *
     * @param string|null $appenv The environment your application will use to bootstrap (if any)
     * @param boolean $debug
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
    public function requestClass()
    {
        return '\Illuminate\Http\Request';
    }

    /**
     * Create a Laravel application
     */
    public function getApplication()
    {
        if (file_exists('bootstrap/autoload.php')) {
            require_once 'bootstrap/autoload.php';
        } elseif (file_exists('vendor/autoload.php')) {
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

        $this->app->afterResolving('auth', function ($auth) {
            $auth->extend('session', function ($app, $name, $config) {
                $provider = $app['auth']->createUserProvider($config['provider']);
                $guard = new \PHPPM\Laravel\SessionGuard($name, $provider, $app['session.store'], null, $app);
                if (method_exists($guard, 'setCookieJar')) {
                    $guard->setCookieJar($this->app['cookie']);
                }
                if (method_exists($guard, 'setDispatcher')) {
                    $guard->setDispatcher($this->app['events']);
                }
                if (method_exists($guard, 'setRequest')) {
                    $guard->setRequest($this->app->refresh('request', $guard, 'setRequest'));
                }

                return $guard;
            });
        });

        $app = $this->app;
        $this->app->extend('session.store', function () use ($app) {
            $manager = $app['session'];
            return $manager->driver();
        });

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
        //check if this is a lumen framework, if so, do not reset
        //note that lumen does not have the getProvider method
        if (method_exists($this->app, 'getProvider')) {
            //reset debugbar if available
            $this->resetProvider('\Illuminate\Redis\RedisServiceProvider');
            $this->resetProvider('\Illuminate\Cookie\CookieServiceProvider');
            $this->resetProvider('\Illuminate\Session\SessionServiceProvider');
        }
    }

    /**
     * @param string $providerName
     */
    protected function resetProvider($providerName)
    {
        if (!$this->app->getProvider($providerName)) {
            return;
        }

        $this->appRegister($providerName, true);
    }

    /**
     * Register application provider
     * Workaround for BC break in https://github.com/laravel/framework/pull/25028
     * @param string $providerName
     * @param bool $force
     */
    protected function appRegister($providerName, $force = false)
    {
        if (!$this->appRegisterParameters) {
            $method = new \ReflectionMethod(get_class($this->app), 'register');
            $this->appRegisterParameters = count($method->getParameters());
        }

        if ($this->appRegisterParameters == 3) {
            $this->app->register($providerName, [], $force);
        } else {
            $this->app->register($providerName, $force);
        }
    }
}
