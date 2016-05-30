<?php

namespace PHPPM\Laravel;

use Symfony\Component\HttpFoundation\Request;

class SessionGuard extends \Illuminate\Auth\SessionGuard
{
    /**
    * Set the current request instance.
    *
    * @param  \Symfony\Component\HttpFoundation\Request  $request
    * @return $this
    */
    public function setRequest(Request $request)
    {
        $this->reset();
        $this->session = $request->getSession();

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
