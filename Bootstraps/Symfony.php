<?php

namespace PHPPM\Bootstraps;

use Stack\Builder;

/**
 * A default bootstrap for the Symfony framework
 */
class Symfony implements StackableBootstrapInterface
{
    /**
     * @var string|null The application environment
     */
    protected $appenv;

    /**
     * Instantiate the bootstrap, storing the $appenv
     */
    public function __construct($appenv)
    {
        $this->appenv = $appenv;
    }

    /**
     * Create a Symfony application
     */
    public function getApplication()
    {
        if (file_exists('./app/AppKernel.php')) {
            require_once './app/AppKernel.php';
        }

        $this->includeAutoload();

        $app = new \AppKernel($this->appenv, false);
        $app->loadClassCache();

        return $app;
    }

    /**
     * Return the StackPHP stack.
     */
    public function getStack(Builder $stack)
    {
        return $stack;
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
