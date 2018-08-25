<?php

namespace PHPPM\Tests\SymfonyMocks;

class Container
{
    public function has($service)
    {
        return false;
    }
}
