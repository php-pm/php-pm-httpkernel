<?php

namespace PHPPM\Tests;

use PHPPM\ProcessSlave;
use PHPPM\Tests\Fixtures\ProcessSlaveDouble;
use PHPUnit\Framework\TestCase;
use PHPPM\Bridges\HttpKernel;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

class SymfonyBootstrapTest extends TestCase
{
    public function setUp(): void
    {
        ProcessSlave::$slave = new ProcessSlaveDouble();
    }

    public static function tearDownAfterClass(): void
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__.'/Fixtures/Symfony/var');
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetRequest()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\Fixtures\\Symfony\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $request = new ServerRequest('GET', '/get');
        $_SERVER['REQUEST_URI'] = (string) $request->getUri();

        $response = $bridge->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', (string) $response->getBody());
    }

    /**
     * @runInSeparateProcess
     */
    public function testFileUpload()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\Fixtures\\Symfony\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $request = new ServerRequest('POST', '/upload');
        $dummyStream = fopen('data:text/plain,dummy', 'r');
        $uploadedFiles = [
            new UploadedFile($dummyStream, 1000, UPLOAD_ERR_OK, 'testOK.pdf', 'pdf'),
            new UploadedFile($dummyStream, 0, UPLOAD_ERR_NO_FILE, 'testErr.pdf', 'pdf'),
        ];
        $request = $request->withUploadedFiles($uploadedFiles);
        $_SERVER['REQUEST_URI'] = (string) $request->getUri();

        $response = $bridge->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Uploaded files: testOK.pdf,NULL', (string)$response->getBody());
    }

    /**
     * @runInSeparateProcess
     */
    public function testPostJSON()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\Fixtures\\Symfony\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $request = new ServerRequest('POST', '/json', [
            'CONTENT_TYPE' => 'application/json',
        ], '{"some_json_array":[{"map1":"value1"},{"map2":"value2"}]}');
        $_SERVER['REQUEST_URI'] = (string) $request->getUri();

        $response = $bridge->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Received JSON: {"some_json_array":[{"map1":"value1"},{"map2":"value2"}]}', (string)$response->getBody());
    }

    /**
     * @runInSeparateProcess
     */
    public function testStreamedResponse()
    {
        putenv('APP_KERNEL_NAMESPACE=PHPPM\\Tests\\Fixtures\\Symfony\\');
        $bridge = new HttpKernel();
        $bridge->bootstrap('Symfony', 'test', true);

        $request = new ServerRequest('GET', '/streamed');
        $_SERVER['REQUEST_URI'] = (string) $request->getUri();

        $response = $bridge->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('streamed data', (string)$response->getBody());
    }
}
