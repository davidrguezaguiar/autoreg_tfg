<?php
/**
 * File: WsseUserToken.php
 * User: ULPGC
 * Email: desarrollo@ulpgc.es 
 * Description: UTF-8
 * Representa los datos de autenticacion del usuario en la peticion.
 * Cuando el usuario se autentica, se genera una marca (token) 
 * para almacenar los datos del usuario a traves del contexto de seguridad.
 */

namespace App\Service\Base\Security\Authentication\Token ;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class WsseUserToken extends AbstractToken
{
    public $created ;
    public $digest ;
    public $nonce ;

    public function __construct(array $roles = array())
    {
        parent::__construct($roles) ;

        // If the user has roles, consider it authenticated
        $this->setAuthenticated(count($roles) > 0) ;
    }


    /**
     * Returns the user credentials.
     *
     * @return mixed The user credentials
     */
    public function getCredentials()
    {
        // TODO: Implement getCredentials() method.
        return '';
    }

} 