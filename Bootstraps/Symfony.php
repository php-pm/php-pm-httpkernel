<?php

namespace PHPPM\Bootstraps;

use PHPPM\Symfony\StrongerNativeSessionStorage;
use PHPPM\Utils;
use Symfony\Component\HttpFoundation\Request;

/**
 * A default bootstrap for the Symfony framework
 */
class Symfony extends AbstractBootstrap implements HooksInterface
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

        //since we need to change some services, we need to manually change some services
        $app = new \AppKernel($this->appenv, $this->debug);

        //we need to change some services, before the boot, because they would otherwise
        //be instantiated and passed to other classes which makes it impossible to replace them.
        Utils::bindAndCall(function() use ($app) {
            // init bundles
            $app->initializeBundles();

            // init container
            $app->initializeContainer();
        }, $app);

        //now we can modify the container
        $nativeStorage = new StrongerNativeSessionStorage(
            $app->getContainer()->getParameter('session.storage.options'),
            $app->getContainer()->has('session.handler') ? $app->getContainer()->get('session.handler'): null,
            $app->getContainer()->get('session.storage.metadata_bag')
        );
        $app->getContainer()->set('session.storage.native', $nativeStorage);

        Utils::bindAndCall(function() use ($app) {
            foreach ($app->getBundles() as $bundle) {
                $bundle->setContainer($app->container);
                $bundle->boot();
            }

            $app->booted = true;
        }, $app);

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
}
