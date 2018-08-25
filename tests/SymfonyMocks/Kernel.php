<?php

namespace PHPPM\Tests\SymfonyMocks;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Kernel
{
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
        return [];
    }

    public function getContainer()
    {
        return new Container();
    }

    public function handle(Request $request)
    {
        if (!$this->bundlesInitialized) {
            throw new \Exception('Bundles not initialized');
        }
        if (!$this->containerInitialized) {
            throw new \Exception('Container not initialized');
        }

        if ($request->getMethod() == 'POST') {
            $mappedFileNames = array_map(function ($f) {
                if (!isset($f)) {
                    return 'NULL';
                }
                return $f->getClientOriginalName();
            }, $request->files->all());
            return new Response('Uploaded files: '.implode(',', $mappedFileNames), 201);
        } elseif ($request->getMethod() == 'GET') {
            // Simple get request
            return new Response('Success', 200);
        }
    }
}
