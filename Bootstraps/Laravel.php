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
        require_once 'bootstrap/autoload.php';

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

        $kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
	
	    $this->app->afterResolving('auth', function($auth) {
		    $auth->extend('session', function($app, $name, $config) {
			    $provider = $app['auth']->createUserProvider($config['provider']);
			    $guard = new \PHPPM\Laravel\SessionGuard($name, $provider, $app['session.store'], null, $app);
			    $guard->setCookieJar($app['cookie']);
			    $guard->setDispatcher($app['events']);
			    $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
			
			    return $guard;
		    });
	    });
	    
	    $app = $this->app;
	    $this->app->extend('session.store', function() use ($app) {
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
		//reset debugbar if available
		
		$this->resetProvider('\Illuminate\Cookie\CookieServiceProvider');
		$this->resetProvider('\Illuminate\Session\SessionServiceProvider');
	}
	
	/**
	 * @param string $providerName
	 */
	protected function resetProvider($providerName)
	{
		if (!$this->app->getProvider($providerName))
		{
			return;
		}
		
		$this->app->register($providerName, [], true);
	}
}
