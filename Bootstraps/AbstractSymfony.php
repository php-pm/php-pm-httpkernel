<?php

namespace PHPPM\Bootstraps;

use PHPPM\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Shared bootstrap logic for Symfony Kernels. Handles changes introduced in Symfony 3.3/4 and 4.0
 */
abstract class AbstractSymfony implements BootstrapInterface, HooksInterface, ApplicationEnvironmentAwareInterface
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
     * @param $appenv
     * @param $debug
     */
    public function initialize($appenv, $debug)
    {
        $this->appenv = $appenv;
        $this->debug = $debug;
    }

    /**
     * Does some necessary preparation before each request.
     *
     * @param KernelInterface $app
     */
    public function preHandle($app)
    {
        //resets Kernels startTime, so Symfony can correctly calculate the execution time
        Utils::hijackProperty($app, 'startTime', microtime(true));
    }

    /**
     * Does some necessary clean up after each request.
     *
     * @param KernelInterface $app
     */
    public function postHandle($app)
    {
        $container = $app->getContainer();

        //resets stopwatch, so it can correctly calculate the execution time
        if ($container->has('debug.stopwatch')) {
            $container->get('debug.stopwatch')->__construct();
        }

        //Symfony\Bundle\TwigBundle\Loader\FilesystemLoader
        //->Twig_Loader_Filesystem
        if ($container->has('twig.loader')) {
            $twigLoader = $container->get('twig.loader');
            Utils::bindAndCall(function() use ($twigLoader) {
                foreach ($twigLoader->cache as $path) {
                    ppm_register_file($path);
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
                    foreach ($this->loggers as $logger){
                        Utils::hijackProperty($logger, 'queries', []);
                    }
                }, $profiler->get('db'), null, 'Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector');
            }

            //EventDataCollector
            if ($profiler->has('events')) {
                Utils::hijackProperty($profiler->get('events'), 'data', array(
                    'called_listeners' => array(),
                    'not_called_listeners' => array(),
                ));
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
                    if ($debugLogger = $this->getDebugLogger()) {
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

    /**
     * @return KernelInterface
     */
    protected function createKernelInstance()
    {
        // include applications autoload
        require './vendor/autoload.php';

        // attempt to preload the environment vars
        $this->loadEnvironmentVariables();

        // locate and attempt to boot the kernel in the current project folder
        $kernel = $this->locateApplicationKernel();

        return new $kernel($this->appenv, $this->debug);
    }

    /**
     * @param KernelInterface $app
     */
    protected function initializeKernel($app)
    {
        // we need to change some services, before the boot, because they would otherwise
        // be instantiated and passed to other classes which makes it impossible to replace them.
        Utils::bindAndCall(function () use ($app) {
            $app->initializeBundles();
            $app->initializeContainer();
        }, $app);
    }

    /**
     * @param KernelInterface $app
     */
    protected function bootKernel($app)
    {
        Utils::bindAndCall(function () use ($app) {
            foreach ($app->getBundles() as $bundle) {
                $bundle->setContainer($app->container);
                $bundle->boot();
            }

            $app->booted = true;
        }, $app);
    }

    /**
     * Attempt to load the env vars from .env, only if Dotenv exists
     */
    protected function loadEnvironmentVariables()
    {
        if (!getenv('APP_ENV') && class_exists('Symfony\Component\Dotenv\Dotenv')) {
            (new \Symfony\Component\Dotenv\Dotenv())->load(realpath('./.env'));
        }
    }

    /**
     * Based on getNamespace from Illuminate\Foundation\Application
     *
     * @return string
     */
    protected function locateApplicationKernel()
    {
        $composer = json_decode(file_get_contents(realpath('./composer.json')), true);

        if (!isset($composer['autoload']['psr-4'])) {
            return 'AppKernel';
        }

        foreach ((array) $composer['autoload']['psr-4'] as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if (realpath('./src/') == realpath('./'.$pathChoice)) {
                    return $namespace . 'Kernel';
                }
            }
        }

        return 'AppKernel';
    }
}
