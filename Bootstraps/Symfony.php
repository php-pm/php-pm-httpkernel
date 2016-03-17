<?php

namespace PHPPM\Bootstraps;
use Symfony\Component\HttpFoundation\Request;

/**
 * A default bootstrap for the Symfony framework
 */
class Symfony implements BootstrapInterface
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
     * Create a Symfony application
     * @return SymfonyAppKernel
     */
    public function getApplication()
    {
        if (file_exists('./app/AppKernel.php')) {
            require_once './app/AppKernel.php';
        }

        $this->includeAutoload();

        $app = new SymfonyAppKernel($this->appenv, $this->debug); //which extends \AppKernel
        $app->loadClassCache();
        $app->boot();

        //warm up
        $request = new Request();
        $request->setMethod(Request::METHOD_HEAD);
        $app->handle($request);

        return $app;
    }

    /**
     * Includes the autoload file from the app directory, if available.
     *
     * The Symfony standard edition configures the annotation class loading
     * in that file.
     * The alternative bootstrap.php.cache cannot be included as that leads
     * to "Cannot redeclare class" error, when starting php-pm.
     */
    protected function includeAutoload()
    {
        $info = new \ReflectionClass('AppKernel');
        $appDir = dirname($info->getFileName());
        $symfonyAutoload = $appDir . '/autoload.php';
        if (is_file($symfonyAutoload)) {
            require_once $symfonyAutoload;
        }
    }
}
