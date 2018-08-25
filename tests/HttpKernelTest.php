<?php

namespace PHPPM\Tests;

use PHPUnit\Framework\TestCase;
use PHPPM\Bridges\HttpKernel;
use Psr\Http\Message\ServerRequestInterface;

class HttpKernelTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testNoBootstrap()
    {
        $bridge = new HttpKernel();
        $request = $this
            ->getMockBuilder(ServerRequestInterface::class)
            ->getMock();
        $response = $bridge->handle($request);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Application not configured during bootstrap', (string)$response->getBody());
    }
}
