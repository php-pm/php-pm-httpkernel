<?php

namespace PHPPM\Bridges;

use PHPPM\Bootstraps\BootstrapInterface;
use PHPPM\Bootstraps\HooksInterface;
use PHPPM\Bootstraps\RequestClassProviderInterface;
use PHPPM\React\HttpResponse;
use PHPPM\Utils;
use React\Http\Request as ReactRequest;
use Symfony\Component\HttpFoundation\Cookie;
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
     *
     * @throws \Exception
     */
    public function onRequest(ReactRequest $request, HttpResponse $response)
    {
        if (null === $this->application) {
            return;
        }

        $syRequest = $this->mapRequest($request);

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

        $this->mapResponse($response, $syResponse);

        if ($this->application instanceof TerminableInterface) {
            $this->application->terminate($syRequest, $syResponse);
        }
        
        if (is_a($this->application, '\Illuminate\Contracts\Http\Kernel')) {
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
    protected function mapRequest(ReactRequest $reactRequest)
    {
        $method = $reactRequest->getMethod();
        $headers = $reactRequest->getHeaders();
        $query = $reactRequest->getQuery();

        $cookies = [];
        $_COOKIE = [];

        $sessionCookieSet = false;

        if (isset($headers['Cookie']) || isset($headers['cookie'])) {
            $headersCookie = explode(';', isset($headers['Cookie']) ? $headers['Cookie'] : $headers['cookie']);
            foreach ($headersCookie as $cookie) {
                list($name, $value) = explode('=', trim($cookie));
                $cookies[$name] = $value;
                $_COOKIE[$name] = $value;

                if ($name === session_name()) {
                    session_id($value);
                    $sessionCookieSet = true;
                }
            }
        }

        if (!$sessionCookieSet && session_id()) {
            //session id already set from the last round but not got from the cookie header,
            //so generate a new one, since php is not doing it automatically with session_start() if session
            //has already been started.
            session_id(Utils::generateSessionId());
        }

        $files = $reactRequest->getFiles();
        $post = $reactRequest->getPost();

        if ($this->bootstrap instanceof RequestClassProviderInterface) {
            $class = $this->bootstrap->requestClass();
        }
        else {
            $class = '\Symfony\Component\HttpFoundation\Request';
        }

        $syRequest = new $class($query, $post, $attributes = [], $cookies, $files, $_SERVER, $reactRequest->getBody());

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
    protected function mapResponse(HttpResponse $reactResponse, SymfonyResponse $syResponse)
    {
        //end active session
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
            session_unset(); //reset $_SESSION
        }

        $content = $syResponse->getContent();
        if ($syResponse instanceof SymfonyStreamedResponse) {
            $syResponse->sendContent();
        }

        $nativeHeaders = [];

        foreach (headers_list() as $header) {
            if (false !== $pos = strpos($header, ':')) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));

                if (isset($nativeHeaders[$name])) {
                    if (!is_array($nativeHeaders[$name])) {
                        $nativeHeaders[$name] = [$nativeHeaders[$name]];
                    }

                    $nativeHeaders[$name][] = $value;
                } else {
                    $nativeHeaders[$name] = $value;
                }
            }
        }

        //after reading all headers we need to reset it, so next request
        //operates on a clean header.
        header_remove();

        $headers = array_merge($nativeHeaders, $syResponse->headers->allPreserveCase());
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
                $cookieHeader .= '; Expires=' . gmdate('D, d-M-Y H:i:s', $cookie->getExpiresTime()). ' GMT';
            }

            if ($cookie->isSecure()) {
                $cookieHeader .= '; Secure';
            }
            if ($cookie->isHttpOnly()) {
                $cookieHeader .= '; HttpOnly';
            }

            $cookies[] = $cookieHeader;
        }

        if (isset($headers['Set-Cookie'])) {
            $headers['Set-Cookie'] = array_merge((array)$headers['Set-Cookie'], $cookies);
        } else {
            $headers['Set-Cookie'] = $cookies;
        }

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
