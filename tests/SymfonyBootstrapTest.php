<?php

namespace PHPPM\Tests;

use PHPUnit\Framework\TestCase;
use PHPPM\Bridges\HttpKernel;
use Psr\Http\Message\ServerRequestInterface;

class SymfonyBootstrapTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testGetRequest()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\SymfonyMocks\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $request = $this
            ->getMockBuilder(ServerRequestInterface::class)
            ->getMock();
        $request->method('getHeader')->with('Cookie')->willReturn(array());
        $request->method('getUploadedFiles')->willReturn(array());
        $request->method('getQueryParams')->willReturn(array());
        $request->method('getMethod')->willReturn('GET');
        
        $response = $bridge->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', (string)$response->getBody());
    }
}
