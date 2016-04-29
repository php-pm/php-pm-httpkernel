<?php

namespace PHPPM\Bootstraps;

/**
 * Implement this interface if HttpKernel bridge needs to return a specialized request class
 */
interface RequestClassProviderInterface
{
    public function requestClass();
}