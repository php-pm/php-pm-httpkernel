<?php

namespace PHPPM\Bootstraps;

use PHPPM\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Contracts\Service\ResetInterface;
use function PHPPM\register_file;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Dotenv\Dotenv;

/**
 * A default bootstrap for the Symfony framework
 */
class Symfony implements BootstrapInterface, HooksInterface, ApplicationEnvironmentAwareInterface
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
     *
     * @param string $appenv
     * @param boolean $debug
     */
    public function initialize($appenv, $debug)
    {
        $this->appenv = $appenv;
        $this->debug = $debug;
    }

    /**
     * Create a Symfony application
     *
     * @return KernelInterface
     * @throws \Exception
     */
    public function getApplication()
    {
        // include applications autoload
        $appAutoLoader = './app/autoload.php';
        if (file_exists($appAutoLoader)) {
            require $appAutoLoader;
        } else {
            require $this->getVendorDir().'/autoload.php';
        }

        // environment loading as of Symfony 3.3
        if (!getenv('APP_ENV') && class_exists(Dotenv::class) && file_exists(realpath('.env'))) {
            //Symfony >=5.1 compatibility
            if (method_exists(Dotenv::class, 'usePutenv')) {
                (new Dotenv())->usePutenv()->load(realpath('.env'));
            } else {
                (new Dotenv(true))->load(realpath('.env'));
            }
        }

        $namespace = getenv('APP_KERNEL_NAMESPACE') ?: '\App\\';
        $fqcn      = $namespace . (getenv('APP_KERNEL_CLASS_NAME') ?: 'Kernel');
        $class     = class_exists($fqcn) ? $fqcn : '\AppKernel';

        if (!class_exists($class)) {
            throw new \Exception("Symfony Kernel class was not found in the configured locations. Given: '$class'");
        }

        //since we need to change some services, we need to manually change some services
        $app = new $class($this->appenv, $this->debug);

        if ($this->debug) {
            Utils::bindAndCall(function () use ($app) {
                $app->boot();
                $container = $app->container;

                $containerClassName = substr(strrchr(get_class($app->container), "\\"), 1);
                $metaName = $containerClassName . '.php.meta';

                Utils::bindAndCall(function () use ($container) {
                    $container->publicContainerDir = $container->containerDir;
                }, $container);

                if ($container->publicContainerDir === null) {
                    return;
                }

                $metaContent = @file_get_contents($app->container->publicContainerDir . '/../' . $metaName);

                // Cannot read the Metadata, returning
                if ($metaContent === false) {
                    return;
                }

                $containerMetadata = unserialize($metaContent);

                foreach ($containerMetadata as $entry) {
                    if ($entry instanceof FileResource) {
                        register_file($entry->__toString());
                    }
                }
            }, $app);
        }

        if ($trustedProxies = getenv('TRUSTED_PROXIES')) {
            Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
        }

        if ($trustedHosts = getenv('TRUSTED_HOSTS')) {
            Request::setTrustedHosts(explode(',', $trustedHosts));
        }

        return $app;
    }

    /**
    * Returns the vendor directory containing autoload.php
    *
    * @return string
    */
    protected function getVendorDir()
    {
        if (getenv('COMPOSER_VENDOR_DIR') && file_exists(getenv('COMPOSER_VENDOR_DIR'))) {
            return getenv('COMPOSER_VENDOR_DIR');
        } else {
            return './vendor';
        }
    }

    /**
     * Does some necessary preparation before each request.
     *
     * @param KernelInterface $app
     */
    public function preHandle($app)
    {
    }

    /**
     * Does some necessary clean up after each request.
     *
     * @param KernelInterface $app
     */
    public function postHandle($app)
    {
        $container = $app->getContainer();

        if ($container->has('doctrine')) {
            $doctrineRegistry = $container->get('doctrine');
            if (!$doctrineRegistry instanceof ResetInterface) {
                foreach ($doctrineRegistry->getManagers() as $curManagerName => $curManager) {
                    if (!$curManager->isOpen()) {
                        $doctrineRegistry->resetManager($curManagerName);
                    } else {
                        $curManager->clear();
                    }
                }
            }
        }

        //Symfony\Bundle\TwigBundle\Loader\FilesystemLoader
        //->Twig_Loader_Filesystem
        if ($this->debug && $container->has('twig.loader')) {
            $twigLoader = $container->get('twig.loader');
            Utils::bindAndCall(function () use ($twigLoader) {
                foreach ($twigLoader->cache as $path) {
                    register_file($path);
                }
            }, $twigLoader);
        }

        //reset all profiler stuff currently supported
        if ($container->has('propel.logger')) {
            $propelLogger = $container->get('propel.logger');
            Utils::hijackProperty($propelLogger, 'queries', []);
        }
    }
}
