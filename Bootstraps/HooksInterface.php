<?php

namespace PHPPM\Bootstraps;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HooksInterface
{
    public function preHandle($app, ServerRequestInterface $request);
    public function postHandle($app, ServerRequestInterface $request, ResponseInterface $response);
}
