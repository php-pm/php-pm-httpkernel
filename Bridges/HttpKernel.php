<?php

namespace PHPPM\Bridges;

use function Amp\call;
use Amp\Promise;
use Amp\Success;
use Amp\Uri\Uri;
use Aerys\Request;
use Aerys\Response;
use PHPPM\Bootstraps\ApplicationEnvironmentAwareInterface;
use PHPPM\Bootstraps\BootstrapInterface;
use PHPPM\Bootstraps\HooksInterface;
use PHPPM\Bootstraps\RequestClassProviderInterface;
use PHPPM\Utils;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;
use Symfony\Component\HttpKernel\TerminableInterface;

class HttpKernel implements BridgeInterface
{
    /**
     * An application implementing the HttpKernelInterface.
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
     * The app bootstrapping itself is actually proxied off to an object implementing the
     * PHPPM\Bridges\BridgeInterface interface which should live within your app itself and
     * be able to be autoloaded.
     *
     * @param string $appBootstrap The name of the class used to bootstrap the application
     * @param string|null $appenv The environment your application will use to bootstrap (if any)
     * @param boolean $debug If debug is enabled
     *
     * @see http://stackphp.com
     */
    public function bootstrap($appBootstrap, $appenv, $debug)
    {
        $appBootstrap = $this->normalizeAppBootstrap($appBootstrap);

        $this->bootstrap = new $appBootstrap();
        if ($this->bootstrap instanceof ApplicationEnvironmentAwareInterface) {
            $this->bootstrap->initialize($appenv, $debug);
        }
        if ($this->bootstrap instanceof BootstrapInterface) {
            $this->application = $this->bootstrap->getApplication();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticDirectory(): string
    {
        return $this->bootstrap->getStaticDirectory();
    }

    /** @inheritdoc */
    public function onRequest(Request $request, Response $response): Promise
    {
        if (null === $this->application) {
            return new Success;
        }

        return call(function () use ($request, $response) {
            $syRequest = yield from $this->mapRequest($request);

            // start buffering the output, so cgi is not sending any http headers
            // this is necessary because it would break session handling since
            // headers_sent() returns true if any unbuffered output reaches cgi stdout.
            ob_start();

            try {
                if ($this->bootstrap instanceof HooksInterface) {
                    $this->bootstrap->preHandle($this->application);
                }

                $syResponse = $this->application->handle($syRequest);
            } catch (\Exception $exception) {
                $response->setStatus(500); // internal server error
                $response->end();

                // end buffering if we need to throw
                @ob_end_clean();

                throw $exception;
            }

            // should not receive output from application->handle()
            @ob_end_clean();

            $this->mapResponse($response, $syResponse);

            if ($this->application instanceof TerminableInterface) {
                $this->application->terminate($syRequest, $syResponse);
            }

            if ($this->bootstrap instanceof HooksInterface) {
                $this->bootstrap->postHandle($this->application);
            }
        });
    }

    protected function mapRequest(Request $aerysRequest): \Generator
    {
        $method = $aerysRequest->getMethod();
        $headers = $aerysRequest->getAllHeaders();
        $query = (new Uri($aerysRequest->getUri()))->getQuery();

        $_COOKIE = [];

        $sessionCookieSet = false;

        foreach ($headers['cookie'] ?? [] as $cookieHeader) {
            $headersCookie = explode(';', $cookieHeader);
            foreach ($headersCookie as $cookie) {
                list($name, $value) = explode('=', trim($cookie));
                $_COOKIE[$name] = $value;

                if ($name === session_name()) {
                    session_id($value);
                    $sessionCookieSet = true;
                }
            }
        }

        if (!$sessionCookieSet && session_id()) {
            // session id already set from the last round but not got from the cookie header,
            // so generate a new one, since php is not doing it automatically with session_start() if session
            // has already been started.
            session_id(Utils::generateSessionId());
        }

        $files = []; // TODO: Support files and POST
        $post = [];

        if ($this->bootstrap instanceof RequestClassProviderInterface) {
            $class = $this->bootstrap->requestClass();
        } else {
            $class = SymfonyRequest::class;
        }

        \parse_str($query, $queryParams);

        $body = yield $aerysRequest->getBody();

        /** @var SymfonyRequest $syRequest */
        $syRequest = new $class($queryParams, $post, $attributes = [], $_COOKIE, $files, $_SERVER, $body);
        $syRequest->setMethod($method);

        return $syRequest;
    }

    protected function mapResponse(Response $aerysResponse, SymfonyResponse $syResponse)
    {
        // end active session
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
            session_unset(); // reset $_SESSION
        }

        $nativeHeaders = [];

        foreach (headers_list() as $header) {
            if (false !== $pos = strpos($header, ':')) {
                $name = substr($header, 0, $pos);
                $value = trim(substr($header, $pos + 1));

                $nativeHeaders[$name][] = $value;
            }
        }

        // after reading all headers we need to reset it, so next request
        // operates on a clean header.
        header_remove();

        $headers = array_merge(
            \array_change_key_case($nativeHeaders, \CASE_LOWER),
            \array_change_key_case($syResponse->headers->allPreserveCase(), \CASE_LOWER)
        );

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

        if (isset($headers['set-cookie'])) {
            $headers['set-cookie'] = array_merge((array) $headers['set-cookie'], $cookies);
        } else {
            $headers['set-cookie'] = $cookies;
        }

        if ($syResponse instanceof SymfonyStreamedResponse) {
            $aerysResponse->setStatus($syResponse->getStatusCode());

            foreach ($headers as $key => $values) {
                foreach ($values as $i => $value) {
                    if ($i === 0) {
                        $aerysResponse->setHeader($key, $value);
                    } else {
                        $aerysResponse->addHeader($key, $value);
                    }
                }
            }

            // asynchronously get content
            ob_start(function($buffer) use ($aerysResponse) {
                $aerysResponse->write($buffer);
                return '';
            }, 4096);

            $syResponse->sendContent();

            // flush remaining content
            @ob_end_flush();
            $aerysResponse->end();
        } else {
            ob_start();
            $content = $syResponse->getContent();
            @ob_end_flush();

            $aerysResponse->setStatus($syResponse->getStatusCode());

            foreach ($headers as $key => $values) {
                foreach ($values as $i => $value) {
                    if ($i === 0) {
                        $aerysResponse->setHeader($key, $value);
                    } else {
                        $aerysResponse->addHeader($key, $value);
                    }
                }
            }

            $aerysResponse->end($content);
        }
    }

    /**
     * @param $appBootstrap
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function normalizeAppBootstrap($appBootstrap): string
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

        throw new \RuntimeException('Class ' . $appBootstrap . ' does not exist');
    }
}
