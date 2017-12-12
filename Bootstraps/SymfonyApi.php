<?php

namespace PHPPM\Bootstraps;

use PHPPM\Symfony\StrongerNativeSessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class SymfonyApi
 *
 * Bootstraps a Symfony based API without Session support.
 *
 * @package    PHPPM\Bootstraps
 * @subpackage PHPPM\Bootstraps\SymfonyApi
 */
class SymfonyApi extends AbstractSymfony
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
        $this->bootKernel($app);

        return $app;
    }
}
