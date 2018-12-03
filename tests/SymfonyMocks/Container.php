<?php

namespace PHPPM\Tests\SymfonyMocks;

class Container
{
    private $containerDir;

    public function has($service)
    {
        return false;
    }
}
