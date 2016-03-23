<?php

namespace PHPPM\Bootstraps;

abstract class AbstractBootstrap implements BootstrapInterface
{
    /**
     * @return string
     */
    abstract public function getStaticDirectory();

    public function requestClass() {
        return '\Symfony\Component\HttpFoundation\Request';
    }
}