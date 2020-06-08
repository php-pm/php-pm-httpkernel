<?php

namespace PHPPM\Bootstraps;

interface HooksInterface
{
    public function preHandle($app);
    public function postHandle($app);
}
