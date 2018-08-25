<?php

namespace PHPPM\Tests\SymfonyMocks;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Kernel {
    private $bundlesInitialized = false;
    private $containerInitialized = false;

    public function __construct($env, $debug)
    {
    }

    public function initializeBundles()
    {
        $this->bundlesInitialized = true;
    }

    public function initializeContainer()
    {
        $this->containerInitialized = true;
    }

    public function getBundles()
    {
        return array();
    }

    public function getContainer()
    {
        return new Container();
    }

    public function handle(Request $request)
    {
        if(!$this->bundlesInitialized) { throw new \Exception('Bundles not initialized'); }
        if(!$this->containerInitialized) { throw new \Exception('Container not initialized'); }

        // Simple get request
        return new Response('Success', 200);
    }
}
