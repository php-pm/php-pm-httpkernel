<?php

namespace PHPPM\Bridges;

use PHPPM\Bootstraps\BootstrapInterface;
use PHPPM\Bootstraps\SymfonyAppKernel;
use React\Http\Request as ReactRequest;
use React\Http\Response as ReactResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;
use Symfony\Component\HttpKernel\TerminableInterface;

class HttpKernel implements BridgeInterface
{
    /**
     * An application implementing the HttpKernelInterface
     *
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $application;

    /**
     * Bootstrap an application implementing the HttpKernelInterface.
     *
     * In the process of bootstrapping we decorate our application with any number of
     * *middlewares* using StackPHP's Stack\Builder.
     *
     * The app bootstraping itself is actually proxied off to an object implementing the
     * PHPPM\Bridges\BridgeInterface interface which should live within your app itself and
     * be able to be autoloaded.
     *
     * @param string $appBootstrap The name of the class used to bootstrap the application
     * @param string|null $appenv The environment your application will use to bootstrap (if any)
     * @param boolean $debug If debug is enabled
     * @see http://stackphp.com
     */
    public function bootstrap($appBootstrap, $appenv, $debug)
    {
        // include applications autoload
        $autoloader = dirname(realpath($_SERVER['SCRIPT_NAME'])) . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }

        $appBootstrap = $this->normalizeAppBootstrap($appBootstrap);

        $bootstrap = new $appBootstrap($appenv, $debug);

        if ($bootstrap instanceof BootstrapInterface) {
            $this->application = $bootstrap->getApplication();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticDirectory()
    {
        return 'web/';
    }

    /**
     * Handle a request using a HttpKernelInterface implementing application.
     *
     * @param \React\Http\Request $request
     * @param \React\Http\Response $response
     */
    public function onRequest(ReactRequest $request, ReactResponse $response)
    {
        if (null === $this->application) {
            return;
        }

        $content = '';
        $headers = $request->getHeaders();
        $contentLength = isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;

        $request->on('data', function ($data)
        use ($request, $response, &$content, $contentLength) {
            // read data (may be empty for GET request)
            $content .= $data;

            // handle request after receive
            if (strlen($content) >= $contentLength) {
                $syRequest = self::mapRequest($request, $content);

                try {
                    if ($this->application instanceof SymfonyAppKernel) {
                        $this->application->preHandle();
                    }

                    $syResponse = $this->application->handle($syRequest);
                } catch (\Exception $exception) {
                    $response->writeHead(500); // internal server error
                    $response->end();
                    throw $exception;
                }

                self::mapResponse($response, $syResponse);

                if ($this->application instanceof TerminableInterface) {
                    $this->application->terminate($syRequest, $syResponse);
                }

                if ($this->application instanceof SymfonyAppKernel) {
                    $this->application->postHandle();
                }
            }
        });
    }

    /**
     * Convert React\Http\Request to Symfony\Component\HttpFoundation\Request
     *
     * @param ReactRequest $reactRequest
     * @return SymfonyRequest $syRequest
     */
    protected static function mapRequest(ReactRequest $reactRequest, $content)
    {
        $method = $reactRequest->getMethod();
        $headers = $reactRequest->getHeaders();
        $query = $reactRequest->getQuery();
        $post = array();

        // parse body?
        if (isset($headers['Content-Type']) && (0 === strpos($headers['Content-Type'], 'application/x-www-form-urlencoded'))
            && in_array(strtoupper($method), array('POST', 'PUT', 'DELETE', 'PATCH'))
        ) {
            parse_str($content, $post);
        }

        $cookies = array();
        if (isset($headers['Cookie'])) {
            $headersCookie = explode(';', $headers['Cookie']);
            foreach ($headersCookie as $cookie) {
                list($name, $value) = explode('=', trim($cookie));
                $cookies[$name] = $value;
            }
        }

        $syRequest = new SymfonyRequest(
        // $query, $request, $attributes, $cookies, $files, $server, $content
            $query, $post, array(), $cookies, array(), array(), $content
        );

        $syRequest->setMethod($method);
        $syRequest->headers->replace($headers);
        $syRequest->server->set('REQUEST_URI', $reactRequest->getPath());
        $syRequest->server->set('SERVER_NAME', explode(':', $headers['Host'])[0]);

        return $syRequest;
    }

    /**
     * Convert Symfony\Component\HttpFoundation\Response to React\Http\Response
     *
     * @param ReactResponse $reactResponse
     * @param SymfonyResponse $syResponse
     */
    protected static function mapResponse(ReactResponse $reactResponse,
                                          SymfonyResponse $syResponse)
    {
        $headers = $syResponse->headers->all();
        $reactResponse->writeHead($syResponse->getStatusCode(), $headers);

        // @TODO convert StreamedResponse in an async manner
        if ($syResponse instanceof SymfonyStreamedResponse) {
            ob_start();
            $syResponse->sendContent();
            $content = ob_get_contents();
            ob_end_clean();
        } else {
            $content = $syResponse->getContent();
        }

        $reactResponse->end($content);
    }

    /**
     * @param $appBootstrap
     * @return string
     * @throws \RuntimeException
     */
    protected function normalizeAppBootstrap($appBootstrap)
    {
        $appBootstrap = str_replace('\\\\', '\\', $appBootstrap);
        if (false === class_exists($appBootstrap)) {
            $appBootstrap = '\\' . $appBootstrap;
            if (false === class_exists($appBootstrap)) {
                throw new \RuntimeException('Could not find bootstrap class ' . $appBootstrap);
            }
            return $appBootstrap;
        }
        return $appBootstrap;
    }
}
