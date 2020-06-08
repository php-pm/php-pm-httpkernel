<?php

namespace PHPPM\Bridges;

use PHPPM\Bootstraps\ApplicationEnvironmentAwareInterface;
use PHPPM\Bootstraps\BootstrapInterface;
use PHPPM\Bootstraps\HooksInterface;
use PHPPM\Bootstraps\RequestClassProviderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use GuzzleHttp\Psr7;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\TerminableInterface;
use Illuminate\Contracts\Http\Kernel;

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
     * @var string[]
     */
    protected $tempFiles = [];

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
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->application) {
            // internal server error
            return new Psr7\Response(500, ['Content-type' => 'text/plain'], 'Application not configured during bootstrap');
        }

        $syRequest = $this->mapRequest($request);

        // start buffering the output, so cgi is not sending any http headers
        // this is necessary because it would break session handling since
        // headers_sent() returns true if any unbuffered output reaches cgi stdout.
        ob_start();

        if ($this->bootstrap instanceof HooksInterface) {
            $this->bootstrap->preHandle($this->application);
        }

        $syResponse = $this->application->handle($syRequest);

        $out = ob_get_clean();
        $response = $this->mapResponse($syResponse, $out);

        if ($this->application instanceof TerminableInterface) {
            $this->application->terminate($syRequest, $syResponse);
        }

        if ($this->application instanceof Kernel) {
            $this->application->terminate($syRequest, $syResponse);
        }

        if ($this->bootstrap instanceof HooksInterface) {
            $this->bootstrap->postHandle($this->application);
        }

        return $response;
    }

    /**
     * Convert React\Http\Request to Symfony\Component\HttpFoundation\Request
     *
     * @param ServerRequestInterface $psrRequest
     *
     * @return SymfonyRequest $syRequest
     */
    protected function mapRequest(ServerRequestInterface $psrRequest)
    {
        $method = $psrRequest->getMethod();
        $query = $psrRequest->getQueryParams();

        // cookies
        $_COOKIE = [];

        foreach ($psrRequest->getHeader('Cookie') as $cookieHeader) {
            $cookies = explode(';', $cookieHeader);

            foreach ($cookies as $cookie) {
                if (strpos($cookie, '=') == false) {
                    continue;
                }
                list($name, $value) = explode('=', trim($cookie));
                $_COOKIE[$name] = $value;

                if ($name === session_name()) {
                    session_id($value);
                }
            }
        }

        /** @var \React\Http\Io\UploadedFile $file */
        $uploadedFiles = $psrRequest->getUploadedFiles();

        $this->mapFiles($uploadedFiles);

        // @todo check howto handle additional headers
        // @todo check howto support other HTTP methods with bodies
        $post = $psrRequest->getParsedBody() ?: [];

        if ($this->bootstrap instanceof RequestClassProviderInterface) {
            $class = $this->bootstrap->requestClass();
        } else {
            $class = SymfonyRequest::class;
        }

        /** @var SymfonyRequest $syRequest */
        $syRequest = new $class($query, $post, $attributes = [], $_COOKIE, $uploadedFiles, $_SERVER, (string)$psrRequest->getBody());

        $syRequest->setMethod($method);

        if ($syRequest instanceof \Illuminate\Http\Request && $syRequest->isJson()) {
            $syRequest->request = $syRequest->json();
        }

        return $syRequest;
    }

    private function mapFiles(&$files)
    {
        foreach ($files as &$file) {
            if (is_array($file)) {
                $this->mapFiles($file);
            } elseif ($file instanceof UploadedFileInterface) {
                $tmpname = tempnam(sys_get_temp_dir(), 'upload');
                $this->tempFiles[] = $tmpname;

                if (UPLOAD_ERR_NO_FILE == $file->getError()) {
                    $file = [
                        'error' => $file->getError(),
                        'name' => $file->getClientFilename(),
                        'size' => $file->getSize(),
                        'tmp_name' => $tmpname,
                        'type' => $file->getClientMediaType(),
                    ];
                } else {
                    if (UPLOAD_ERR_OK == $file->getError()) {
                        file_put_contents($tmpname, (string)$file->getStream());
                    }
                    $class = new \ReflectionClass(SymfonyFile::class);
                    if (count($class->getConstructor()->getParameters()) === 6) {
                        // Symfony < v4.1
                        $file = new SymfonyFile(
                            $tmpname,
                            $file->getClientFilename(),
                            $file->getClientMediaType(),
                            $file->getSize(),
                            $file->getError(),
                            true
                        );
                    } else {
                        $file = new SymfonyFile(
                            $tmpname,
                            $file->getClientFilename(),
                            $file->getClientMediaType(),
                            $file->getError(),
                            true
                        );
                    }

                }
            }
        }
    }

    /**
     * Convert Symfony\Component\HttpFoundation\Response to React\Http\Response
     *
     * @param SymfonyResponse $syResponse
     * @param string          $stdout     Additional stdout that was catched during handling a request.
     *
     * @return ResponseInterface
     */
    protected function mapResponse(SymfonyResponse $syResponse, $stdout='')
    {
        // end active session
        if (PHP_SESSION_ACTIVE === session_status()) {
            // make sure open session are saved to the storage
            // in case the framework hasn't closed it correctly.
            session_write_close();
        }

        // reset session_id in any case to something not valid, for next request
        session_id('');

        //reset $_SESSION
        session_unset();
        unset($_SESSION);

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

        // after reading all headers we need to reset it, so next request
        // operates on a clean header.
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

            if ($cookie->getMaxAge()) {
                $cookieHeader .= '; Max-Age=' . $cookie->getMaxAge();
            }

            if ($cookie->isSecure()) {
                $cookieHeader .= '; Secure';
            }
            if ($cookie->isHttpOnly()) {
                $cookieHeader .= '; HttpOnly';
            }

            $cookies[] = $cookieHeader;
        }

        if (isset($nativeHeaders['Set-Cookie'])) {
            $headers['Set-Cookie'] = array_merge((array)$nativeHeaders['Set-Cookie'], $cookies);
        } elseif ($cookies) {
            $headers['Set-Cookie'] = $cookies;
        }

        $psrResponse = new Psr7\Response($syResponse->getStatusCode(), $headers);

        // get contents
        ob_start();
        $syResponse->sendContent();
        $content = @ob_get_clean();

        if ($stdout) {
            $content = $stdout . $content;
        }

        if (!isset($headers['Content-Length'])) {
            $psrResponse = $psrResponse->withAddedHeader('Content-Length', strlen($content));
        }

        $psrResponse = $psrResponse->withBody(Psr7\stream_for($content));

        foreach ($this->tempFiles as $tmpname) {
            if (file_exists($tmpname)) {
                unlink($tmpname);
            }
        }

        return $psrResponse;
    }

    /**
     * @param string $appBootstrap
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
