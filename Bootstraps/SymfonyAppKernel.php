<?php

namespace PHPPM\Bootstraps;

use Symfony\Component\HttpKernel\KernelInterface;

class SymfonyAppKernel extends \AppKernel implements KernelInterface
{

    /**
     * Does some necessary preparation before each request.
     */
    public function preHandle()
    {
        //resets Kernels startTime, so Symfony can correctly calculate the execution time
        $this->startTime = microtime(true);
    }

    /**
     * Does some necessary clean up after each request.
     */
    public function postHandle()
    {
        //resets stopwatch, so it can correctly calculate the execution time
        $this->getContainer()->get('debug.stopwatch')->__construct();

        if ($this->getContainer()->has('profiler')) {
            // since Symfony does not reset Profiler::disable() calls after each request, we need to do it,
            // so the profiler bar is visible after the second request as well.
            $this->getContainer()->get('profiler')->enable();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRootDir()
    {
        if (null === $this->rootDir) {
            $r = new \ReflectionClass('\AppKernel');
            $this->rootDir = dirname($r->getFileName());
        }

        return $this->rootDir;
    }

}