<?php

namespace PHPPM\Laravel;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Contracts\Session\Session;

class SessionGuard extends \Illuminate\Auth\SessionGuard
{
    protected Application $app;

    public function __construct(
        public readonly string $name,
        UserProvider $provider,
        Session $session,
        Request $request = null,
        Application $app
    ) {
        $this->session = $session;
        $this->request = $request;
        $this->provider = $provider;
        $this->app = $app;
    }

    public function setRequest(Request $request): self
    {
        // reset the current state
        $this->reset();

        // retrieve a new session from the app
        $this->session = $this->app->make('session');

        return parent::setRequest($request);
    }

    public function getName(): string
    {
        return 'login_'.$this->name.'_'.sha1(parent::class);
    }

    public function getRecallerName(): string
    {
        return 'remember_'.$this->name.'_'.sha1(parent::class);
    }

    protected function reset(): void
    {
        $this->user = null;
        $this->lastAttempted = null;
        $this->viaRemember = false;
        $this->loggedOut = false;
        $this->tokenRetrievalAttempted = false;
        $this->recallAttempted  = false;
    }
}
