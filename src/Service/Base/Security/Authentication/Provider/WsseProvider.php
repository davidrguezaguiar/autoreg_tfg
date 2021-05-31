<?php


/**
 *
 * @author ULPGC
 * Realiza la carga de datos de usuario al testigo pasado por parámetro.
 * Esta acción la realiza concretamente el  método authenticate.
 *
 */

namespace App\Service\Base\Security\Authentication\Provider;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationExpiredException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use App\Service\Base\Security\Authentication\Token\WsseUserToken;

class WsseProvider implements AuthenticationProviderInterface
{

    private $userProvider;
    private $cacheDir;

    public function __construct(UserProviderInterface $userProvider, CacheItemPoolInterface $cacheDir)
    {
        $this->userProvider = $userProvider;

        $this->cacheDir = $cacheDir;
    }

    public function authenticate(TokenInterface $token)
    {
        if (is_object($token->getUser())) {

            /** Si el usuario no existe en ULPGES, creamos a mano el objeto usuario,
             *  La particularidad de este tipo de usuarios es que tienen un rol "ROLE_ULPGC",
             *  necesario para poder ser autenticado, ya que un usuario para symfony solo estara
             *  autenticado si count($aRoles) > 0.
             *
             *  La otra particularidad es el tipoUsuario, esta propiedad se encuentra en la tabla TUSUARIOS
             *  de ULPGES, realmente no tiene utilidad y por ello nos valemos de ella, estableciendo el valor
             *  'X' para identificar a aquellos usuarios que habiendo sido autenticados por el CAS, no estan
             *  en ULPGES.
             *
             *  La clase Security/Authentication/Provider/WsseProvider.php utiliza este valor
             *  'X' para que Symfony no vaya a buscar al usuario a la tabla TUSUARIOS y dé por válido al usuario.
             *
             * @see Security/Authentication/Provider/WsseProvider.php
             */

            if ($token->getUser()->getAtipousuario() == 'X') {
                $user = $token->getUser();
            } else {
                $user = $this->userProvider->loadUserByUsername($token->getUser()->getUsername());
            }

            $user->setRoles($token->getUser()->getRoles());

            if (!$user) {
                throw new AuthenticationException("Bad credentials.");
            }

            $authenticatedToken = new WsseUserToken($user->getRoles());
            $authenticatedToken->setUser($user);

            return $authenticatedToken;
        }
        throw new AuthenticationException('The authentication failed.');
    }

    /**
     * This function is specific to Wsse authentication and is only used to help this example
     *
     * For more information specific to the logic here, see
     * https://github.com/symfony/symfony-docs/pull/3134#issuecomment-27699129
     */
    protected function validateDigest($digest, $nonce, $created, $secret)
    {
        // Check created time is not in the future
        if (strtotime($created) > time()) {
            return false;
        }

        // Expire timestamp after 5 minutes
        if (time() - strtotime($created) > 300) {
            return false;
        }

        // Validate that the nonce is *not* used in the last 5 minutes
        // if it has, this could be a replay attack
        if (file_exists($this->cacheDir . '/' . $nonce) && file_get_contents($this->cacheDir . '/' . $nonce) + 300 > time()) {
            throw new AuthenticationExpiredException('Previously used nonce detected');
        }
        // If cache directory does not exist we create it
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        file_put_contents($this->cacheDir . '/' . $nonce, time());

        // Validate Secret
        $expected = base64_encode(sha1(base64_decode($nonce) . $created . $secret, true));

        return $digest === $expected;
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof WsseUserToken;
    }
}
