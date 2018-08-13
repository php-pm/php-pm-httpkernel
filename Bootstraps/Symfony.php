<?php

namespace PHPPM\Bootstraps;

use PHPPM\Symfony\StrongerNativeSessionStorage;
use PHPPM\Utils;
use Symfony\Component\HttpFoundation\Request;
use function PHPPM\register_file;

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
     * @return \AppKernel
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
        if (!getenv('APP_ENV') && class_exists('Symfony\Component\Dotenv\Dotenv') && file_exists(realpath('.env'))) {
            (new \Symfony\Component\Dotenv\Dotenv())->load(realpath('.env'));
        }

        $namespace = getenv('APP_KERNEL_NAMESPACE') ?: '\App\\';
        $fqcn      = $namespace . (getenv('APP_KERNEL_CLASS_NAME') ?: 'Kernel');
        $class     = class_exists($fqcn) ? $fqcn : '\AppKernel';

        if (!class_exists($class)) {
        	throw new \Exception("Symfony Kernel class was not found in the configured locations. Given: '$class'");
        }

        //since we need to change some services, we need to manually change some services
        $app = new $class($this->appenv, $this->debug);

        // We need to change some services, before the boot, because they would
        // otherwise be instantiated and passed to other classes which makes it
        // impossible to replace them.

        Utils::bindAndCall(function () use ($app) {
            // init bundles
            $app->initializeBundles();

            // init container
            $app->initializeContainer();
        }, $app);

        Utils::bindAndCall(function () use ($app) {
            foreach ($app->getBundles() as $bundle) {
                $bundle->setContainer($app->container);
                $bundle->boot();
            }

            $app->booted = true;
        }, $app);

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
     * @param \AppKernel $app
     */
    public function preHandle($app)
    {
        //resets Kernels startTime, so Symfony can correctly calculate the execution time
        Utils::hijackProperty($app, 'startTime', microtime(true));
    }

    /**
     * Does some necessary clean up after each request.
     *
     * @param \AppKernel $app
     */
    public function postHandle($app)
    {
        $container = $app->getContainer();

        if ($container->has('doctrine')) {
            $em = $container->get("doctrine");
            if (!$em->getManager()->isOpen()) {
                $em->resetManager();
            }
        }

        //resets stopwatch, so it can correctly calculate the execution time
        if ($container->has('debug.stopwatch')) {
            $container->get('debug.stopwatch')->__construct();
        }

        //Symfony\Bundle\TwigBundle\Loader\FilesystemLoader
        //->Twig_Loader_Filesystem
        if ($container->has('twig.loader')) {
            $twigLoader = $container->get('twig.loader');
            Utils::bindAndCall(function () use ($twigLoader) {
                foreach ($twigLoader->cache as $path) {
                    register_file($path);
                }
            }, $twigLoader);
        }

        //reset all profiler stuff currently supported
        if ($container->has('profiler')) {
            $profiler = $container->get('profiler');

            // since Symfony does not reset Profiler::disable() calls after each request, we need to do it,
            // so the profiler bar is visible after the second request as well.
            $profiler->enable();

            //PropelLogger
            if ($container->has('propel.logger')) {
                $propelLogger = $container->get('propel.logger');
                Utils::hijackProperty($propelLogger, 'queries', []);
            }

            //Doctrine
            //Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector
            if ($profiler->has('db')) {
                Utils::bindAndCall(function () {
                    //$logger: \Doctrine\DBAL\Logging\DebugStack
                    foreach ($this->loggers as $logger) {
                        Utils::hijackProperty($logger, 'queries', []);
                    }
                }, $profiler->get('db'), null, 'Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector');
            }

            //EventDataCollector
            if ($profiler->has('events')) {
                Utils::hijackProperty($profiler->get('events'), 'data', [
                    'called_listeners' => [],
                    'not_called_listeners' => [],
                ]);
            }

            //TwigDataCollector
            if ($profiler->has('twig')) {
                Utils::bindAndCall(function () {
                    Utils::hijackProperty($this->profile, 'profiles', []);
                }, $profiler->get('twig'));
            }

            //Logger
            if ($container->has('logger')) {
                $logger = $container->get('logger');
                Utils::bindAndCall(function () {
                    if (\method_exists($this, 'getDebugLogger') && $debugLogger = $this->getDebugLogger()) {
                        //DebugLogger
                        Utils::hijackProperty($debugLogger, 'records', []);
                    }
                }, $logger);
            }

            //SwiftMailer logger
            //Symfony\Bundle\SwiftmailerBundle\DataCollector\MessageDataCollector
            if ($container->hasParameter('swiftmailer.mailers')) {
                $mailers = $container->getParameter('swiftmailer.mailers');
                foreach ($mailers as $name => $mailer) {
                    $loggerName = sprintf('swiftmailer.mailer.%s.plugin.messagelogger', $name);
                    if ($container->has($loggerName)) {
                        /** @var \Swift_Plugins_MessageLogger $logger */
                        $logger = $container->get($loggerName);
                        $logger->clear();
                    }
                }
            }

            //Symfony\Bridge\Swiftmailer\DataCollector\MessageDataCollector
            if ($container->has('swiftmailer.plugin.messagelogger')) {
                $logger = $container->get('swiftmailer.plugin.messagelogger');
                $logger->clear();
            }
        }
    }
}
