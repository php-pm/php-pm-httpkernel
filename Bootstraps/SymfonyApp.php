<?php

namespace PHPPM\Bootstraps;

use PHPPM\Symfony\StrongerNativeSessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class SymfonyApp
 *
 * Bootstraps a standard, session based Symfony application.
 *
 * @package    PHPPM\Bootstraps
 * @subpackage PHPPM\Bootstraps\SymfonyApp
 */
class SymfonyApp extends AbstractSymfony
{

    /**
     * Create a Symfony application
     *
     * @return KernelInterface
     */
    public function getApplication()
    {
        $app = $this->createKernelInstance();

        $this->initializeKernel($app);

        // replace session handler with one more suited to php-pm (from Symfony bootstrapper)
        if ($app->getContainer()->hasParameter('session.storage.options')) {
            $nativeStorage = new StrongerNativeSessionStorage(
                $app->getContainer()->getParameter('session.storage.options'),
                $app->getContainer()->has('session.handler') ? $app->getContainer()->get('session.handler') : null,
                $app->getContainer()->get('session.storage.metadata_bag')
            );
            $app->getContainer()->set('session.storage.native', $nativeStorage);
        }

        $this->bootKernel($app);

        return $app;
    }
}
