<?php

namespace PHPPM\Bridges;

use PHPPM\Bootstraps\BootstrapInterface;
use PHPPM\Bootstraps\HooksInterface;
use PHPPM\React\HttpResponse;
use React\Http\Request as ReactRequest;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
     * @var BootstrapInterface
     */
    protected $bootstrap;

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
        $appBootstrap = $this->normalizeAppBootstrap($appBootstrap);

        $this->bootstrap = new $appBootstrap($appenv, $debug);

        if ($this->bootstrap instanceof BootstrapInterface) {
            $this->application = $this->bootstrap->getApplication();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticDirectory()
    {
        return $this->bootstrap->getStaticDirectory();
    }

    /**
     * Handle a request using a HttpKernelInterface implementing application.
     *
     * @param ReactRequest $request
     * @param HttpResponse $response
     */
    public function onRequest(ReactRequest $request, HttpResponse $response)
    {
        if (null === $this->application) {
            return;
        }

        $syRequest = self::mapRequest($request);

        //start buffering the output, so cgi is not sending any http headers
        //this is necessary because it would break session handling since
        //headers_sent() returns true if any unbuffered output reaches cgi stdout.
        ob_start();

        try {
            if ($this->bootstrap instanceof HooksInterface) {
                $this->bootstrap->preHandle($this->application);
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

        if ($this->bootstrap instanceof HooksInterface) {
            $this->bootstrap->postHandle($this->application);
        }
    }

    /**
     * Convert React\Http\Request to Symfony\Component\HttpFoundation\Request
     *
     * @param ReactRequest $reactRequest
     * @return SymfonyRequest $syRequest
     */
    protected static function mapRequest(ReactRequest $reactRequest)
    {
        $method = $reactRequest->getMethod();
        $headers = array_change_key_case($reactRequest->getHeaders());
        $query = $reactRequest->getQuery();

        $cookies = array();
        if (isset($headers['Cookie'])) {
            $headersCookie = explode(';', $headers['Cookie']);
            foreach ($headersCookie as $cookie) {
                list($name, $value) = explode('=', trim($cookie));
                $cookies[$name] = $value;
            }
        }

        $files = $reactRequest->getFiles();
        $post = $reactRequest->getPost();

        $syRequest = new SymfonyRequest($query, $post, $attributes = [], $cookies, $files, $_SERVER, $reactRequest->getBody());

        $syRequest->setMethod($method);
        $syRequest->headers->replace($headers);

        return $syRequest;
    }

    /**
     * Convert Symfony\Component\HttpFoundation\Response to React\Http\Response
     *
     * @param HttpResponse $reactResponse
     * @param SymfonyResponse $syResponse
     */
    protected static function mapResponse(HttpResponse $reactResponse, SymfonyResponse $syResponse)
    {
        $content = $syResponse->getContent();

        $headers = $syResponse->headers->allPreserveCase();
        $cookies = [];

        /** @var Cookie $cookie */
        foreach ($syResponse->headers->getCookies() as $cookie) {
            $cookieHeader = sprintf('%s=%s', $cookie->getName(), $cookie->getValue());

            if ($cookie->getPath()) {
                $cookieHeader .= '; Path=' . $cookie->getPath();
            }
            if ($cookie->getDomain()) {
                $cookieHeader .= '; Domain=' . $cookie->getDomain();
            }

            if ($cookie->getExpiresTime()) {
                $cookieHeader .= '; Expires=' . $cookie->getExpiresTime();
            }

            if ($cookie->isSecure()) {
                $cookieHeader .= '; Secure';
            }
            if ($cookie->isHttpOnly()) {
                $cookieHeader .= '; HttpOnly';
            }

            $cookies[] = $cookieHeader;
        }

        $headers['Set-Cookie'] = $cookies;

        $reactResponse->writeHead($syResponse->getStatusCode(), $headers);

        $stdOut = '';
        while ($buffer = @ob_get_clean()) {
            $stdOut .= $buffer;
        }

        $reactResponse->end($stdOut . $content);

    }

    /**
     * @param $appBootstrap
     * @return string
     * @throws \RuntimeException
     */
    protected function normalizeAppBootstrap($appBootstrap)
    {
        $appBootstrap = str_replace('\\\\', '\\', $appBootstrap);

        $bootstraps = [
            $appBootstrap,
            '\\' . $appBootstrap,
            '\\PHPPM\Bootstraps\\' . ucfirst($appBootstrap)
        ];

        foreach ($bootstraps as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
    }
}
