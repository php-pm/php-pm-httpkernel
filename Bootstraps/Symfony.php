<?php

namespace PHPPM\Bootstraps;

use Stack\Builder;
use Symfony\Component\HttpKernel\HttpCache\Store;

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

        $info = new \ReflectionClass('AppKernel');
        $appDir = dirname($info->getFileName());
        $symfonyAutoload = $appDir . '/autoload.php';
        if (is_file($symfonyAutoload)) {
            require_once $symfonyAutoload;
        }

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
}
