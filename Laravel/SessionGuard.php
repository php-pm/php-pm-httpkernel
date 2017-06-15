<?php

namespace PHPPM\Laravel;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
	 * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
	 * @param  \Symfony\Component\HttpFoundation\Session\SessionInterface  $session
	 * @param  \Symfony\Component\HttpFoundation\Request  $request
	 * @param  mixed|\Illuminate\Foundation\Application $app
	 * @return void
	 */
	public function __construct($name,
	                            UserProvider $provider,
	                            SessionInterface $session,
	                            Request $request = null,
	                            Application $app)
	{
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
	}
}
