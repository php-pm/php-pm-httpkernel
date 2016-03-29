<?php

namespace PHPPM\Symfony;

/**
 * Since PHP-PM needs to generate its session ids on its own due to the fact that session_destroy()
 * does not reset session_id() nor does it generate a new one for a new session, we need
 * to overwrite Symfonys NativeSessionStorage that uses session_regenerate_id() which generates
 * the weaker session ids from PHP. So we need to overwrite the method regenerate(), to set a better
 * session id afterwards.
 */
class StrongerNativeSessionStorage extends \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage
{
    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false, $lifetime = null)
    {
        //since session_regenerate_id also places a setcookie call, we need to deactivate this, to not have
        //two Set-Cookie headers
        ini_set('session.use_cookies', 0);
        if ($isRegenerated = parent::regenerate($destroy, $lifetime)) {
            $params = session_get_cookie_params();

            session_id(\PHPPM\Utils::generateSessionId());

            setcookie(
                session_name(),
                session_id(),
                $params['lifetime'] ? time() + $params['lifetime'] : null,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        ini_set('session.use_cookies', 1);

        return $isRegenerated;
    }

}