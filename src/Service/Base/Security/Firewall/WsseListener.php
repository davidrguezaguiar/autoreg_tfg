<?php

/**
 * File: WsseListener.php
 * User: ULPGC
 * Email: desarrollo@ulpgc.es
 * Description: UTF-8
 */

namespace App\Service\Base\Security\Firewall;

use App\Entity\Base\Tusuario;
use App\Lib\Base\Utilidades;
use App\Service\Base\CAS\AutenticadorCASService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use App\Service\Base\Security\Authentication\Token\WsseUserToken;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\HttpKernel\Event\RequestEvent;
/**
 * Class WsseListener
 *
 */
class WsseListener  {

    protected $securityContext;
    protected $authenticationManager;
    protected $routing;
    protected $contenedor;
    protected $autenticadorCAS;
    protected $aplicacionDeshabilitada;


    /**
     * WsseListener constructor.
     *
     * @param TokenStorageInterface                                                          $securityContext
     * @param \Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface $authenticationManager
     * @param \Symfony\Component\Routing\RouterInterface                                     $routing
     * @param \Symfony\Component\DependencyInjection\ContainerInterface                      $container
     * @param AutenticadorCASService                                                         $autenticadorCAS
     */
    function __construct ( TokenStorageInterface $securityContext , AuthenticationManagerInterface $authenticationManager ,
                           RouterInterface $routing , Container $container , AutenticadorCASService $autenticadorCAS ) {
        $this->securityContext         = $securityContext;
        $this->authenticationManager   = $authenticationManager;
        $this->routing                 = $routing;
        $this->contenedor              = $container;
        $this->autenticadorCAS         = $autenticadorCAS;

        $fichero = $this->contenedor->getParameter ('fichero_aplicacion_deshabilitada');
        $this->aplicacionDeshabilitada = file_exists ( $fichero) ? TRUE : FALSE;

    }

    /**
     * This interface must be implemented by firewall listeners.
     *
     * @param GetResponseEvent $event
     */
    public function __invoke ( RequestEvent  $event ) {

        $session = $event->getRequest ()->getSession ();

        /** Se comprueba si el usuario está autenticado en CAS */
        if ( $session->get ( 'userObject' ) instanceof Tusuario ) {
            $oUser = $session->get ( 'userObject' );
        } else {
            $oUser = $this->autenticadorCAS->getUsuarioCAS ( $event->getRequest () );
        }

        if ( is_object ( $oUser ) ) {
            try {
                $token = new WsseUserToken();
                
                $token->setUser ( $oUser );
                $authToken = $this->authenticationManager->authenticate ( $token );
                $this->securityContext->setToken ( $authToken );

                if ( ($this->aplicacionDeshabilitada) and ( $this->routing->getContext()->getPathInfo() != '/habilitarDeshabilitarAplicacion/1' ) ) {

                    $session->set ( 'aplicacionDeshabilitada' , '1' );
                    /**
                     * Si la peticion viene deshabilitar aplicacion, no se redirige otra vez a esta, ya que si no da un error
                     * TO_MANY_REDIRECT. TODO: Puede que no sea la mejor solucion
                     */
                    if ($this->routing->getContext ()->getPathInfo () != '/aplicaciondeshabilitada') {
                        $event->setResponse ( new RedirectResponse( $this->routing->generate ( 'aplicacion_deshabilitada' , array() ) ) );
                    }

                    $event->stopPropagation ();
                } else {
                    $session->remove ('aplicacionDeshabilitada');
                }


            } catch ( AuthenticationException $failed ) {
                $event->setResponse ( new RedirectResponse( $this->routing->generate ( 'error_acceso' , array() ) ) );

                return;
            }
        } else {

            $this->securityContext->setToken ( NULL );
            $event->setResponse ( new RedirectResponse( $this->routing->generate ( 'cas_login' , array() ) ) );

            return;
        }
    }
}
