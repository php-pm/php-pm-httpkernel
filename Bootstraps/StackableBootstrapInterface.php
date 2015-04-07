<?php

namespace PHPPM\Bootstraps;

use Stack\Builder;

/**
 * Stack\Builder extension for use with HttpKernel middlewares
 */
interface StackableBootstrapInterface extends BootstrapInterface
{
    public function getStack(Builder $stack);
}
