<?php

namespace PHPPM\Bootstraps;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A default bootstrap for the Symfony framework
 */
class Symfony implements BootstrapInterface, HooksInterface
{
    /**
     * @var string|null The application environment
     */
    protected $appenv;

    /**
     * @var boolean
     */
    protected $debug;

    /**
     * Instantiate the bootstrap, storing the $appenv
     */
    public function __construct($appenv, $debug)
    {
        $this->appenv = $appenv;
        $this->debug = $debug;
    }

    /**
     * @return string
     */
    public function getStaticDirectory()
    {
        return 'web/';
    }

    /**
     * Create a Symfony application
     *
     * @return \AppKernel
     */
    public function getApplication()
    {
        // include applications autoload
        $appAutoLoader = './app/autoload.php';
        if (file_exists($appAutoLoader)) {
            require $appAutoLoader;
        } else {
            require './vendor/autoload.php';
        }

        $app = new \AppKernel($this->appenv, $this->debug);
        $app->loadClassCache();
        $app->boot();

        //warm up
        $request = new Request();
        $request->setMethod(Request::METHOD_HEAD);
        $app->handle($request);
        $this->preloadServices($app);

        return $app;
    }

    /**
     * Does some necessary preparation before each request.
     *
     * @param \AppKernel $app
     */
    public function preHandle($app)
    {
        //resets Kernels startTime, so Symfony can correctly calculate the execution time
        \Closure::bind(
            function () {
                $this->startTime = microtime(true);
            },
            $app,
            'AppKernel'
        )->call($app);
    }

    /**
     * Does some necessary clean up after each request.
     *
     * @param \AppKernel $app
     */
    public function postHandle($app)
    {
        //resets stopwatch, so it can correctly calculate the execution time
        if ($app->getContainer()->has('debug.stopwatch')) {
            $app->getContainer()->get('debug.stopwatch')->__construct();
        }

        if ($app->getContainer()->has('profiler')) {
            // since Symfony does not reset Profiler::disable() calls after each request, we need to do it,
            // so the profiler bar is visible after the second request as well.
            $app->getContainer()->get('profiler')->enable();
        }
    }

    /**
     * Instantiate all services in the Dependency Injection Container.
     *
     * Stateless (shared) services in the Dependency Injection Container will be
     * instantiated once by the first Request that require them, and then their
     * instance will be reused in all other Requests.
     * This also allows to "warm up" part of autoloading.
     *
     * @param KernelInterface $kernel
     */
    protected function preloadServices(KernelInterface $kernel)
    {
        $kernel->boot();
        $container = $kernel->getContainer();
        foreach ($container->getServiceIds() as $serviceId) {
            $container->get($serviceId);
        }
    }
}
