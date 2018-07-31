<?php

namespace PHPPM\Laravel;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Contracts\Session\Session;

class SessionGuard extends \Illuminate\Auth\SessionGuard
{

    /**
     * App instance
     *
     * @var mixed|\Illuminate\Foundation\Application $app
     */
    protected $app;

    /**
     * Create a new authentication guard.
     *
     * @param  string  $name
     * @param  \Illuminate\Contracts\Auth\UserProvider   $provider
     * @param  \Illuminate\Contracts\Session\Session     $session
     * @param  \Symfony\Component\HttpFoundation\Request $request
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public function __construct(
        $name,
        UserProvider $provider,
        Session $session,
        Request $request = null,
        Application $app
    ) {
        $this->name = $name;
        $this->session = $session;
        $this->request = $request;
        $this->provider = $provider;
        $this->app = $app;
    }

    /**
     * Set the current request instance.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        // reset the current state
        $this->reset();

        // retrieve a new session from the app
        $this->session = $this->app->make('session');

        return parent::setRequest($request);
    }
    
    /**
     * Get a unique identifier for the auth session value.
     *
     * @return string
     */
    public function getName()
    {
        return 'login_'.$this->name.'_'.sha1(parent::class);
    }

    /**
     * Get the name of the cookie used to store the "recaller".
     *
     * @return string
     */
    public function getRecallerName()
    {
        return 'remember_'.$this->name.'_'.sha1(parent::class);
    }
    
    /**
     * Reset the state of current class instance.
     *
     * @return void
     */
    protected function reset()
    {
        $this->user = null;
        $this->lastAttempted = null;
        $this->viaRemember = false;
        $this->loggedOut = false;
        $this->tokenRetrievalAttempted = false;
        $this->recallAttempted  = false;
    }
}
