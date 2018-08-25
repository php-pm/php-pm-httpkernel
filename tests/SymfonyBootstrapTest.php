<?php

namespace PHPPM\Tests;

use PHPUnit\Framework\TestCase;
use PHPPM\Bridges\HttpKernel;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

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

        $request = $this->getMockBuilder(ServerRequestInterface::class)->getMock();
        $request->method('getHeader')->with('Cookie')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getUploadedFiles')->willReturn([]);
        $request->method('getMethod')->willReturn('GET');

        $response = $bridge->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', (string)$response->getBody());
    }

    /**
     * @runInSeparateProcess
     */
    public function testFileUpload()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\SymfonyMocks\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $fileOK = $this->getMockBuilder(UploadedFileInterface::class)->getMock();
        $fileOK->method('getClientFilename')->willReturn('testOK.pdf');
        $fileOK->method('getClientMediaType')->willReturn('pdf');
        $fileOK->method('getSize')->willReturn(1000);
        $fileOK->method('getError')->willReturn(UPLOAD_ERR_OK);

        $fileErr = $this->getMockBuilder(UploadedFileInterface::class)->getMock();
        $fileErr->method('getClientFilename')->willReturn('testErr.pdf');
        $fileErr->method('getClientMediaType')->willReturn('pdf');
        $fileErr->method('getSize')->willReturn(0);
        $fileErr->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);

        $request = $this->getMockBuilder(ServerRequestInterface::class)->getMock();
        $request->method('getHeader')->with('Cookie')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getUploadedFiles')->willReturn([$fileOK, $fileErr]);
        $request->method('getMethod')->willReturn('POST');

        $response = $bridge->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Uploaded files: testOK.pdf,NULL', (string)$response->getBody());
    }

    /**
     * @runInSeparateProcess
     */
    public function testPostJSON()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\SymfonyMocks\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $_SERVER["CONTENT_TYPE"] = 'application/json';
        $request = $this->getMockBuilder(ServerRequestInterface::class)->getMock();
        $request->method('getHeader')->with('Cookie')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getUploadedFiles')->willReturn([]);
        $request->method('getBody')->willReturn('{"some_json_array":[{"map1":"value1"},{"map2":"value2"}]}');
        $request->method('getMethod')->willReturn('POST');

        $response = $bridge->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Received JSON: {"some_json_array":[{"map1":"value1"},{"map2":"value2"}]}', (string)$response->getBody());
    }
}
