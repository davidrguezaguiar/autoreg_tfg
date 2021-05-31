<?php
/**
 * Created by ULPGC.
 */

namespace App\Controller\Base;

use App\Form\suplantarIdentidad\SuplantarForm;
use App\Lib\Base\UsuarioSuplantado;
use App\Lib\Base\Utilidades;
use App\Service\ARUServices;
use App\Service\Base\CAS\AutenticadorCASService;
use App\Service\Base\Suplantacion\SuplantadorIdentidadService;
use Exception;
use phpCAS;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class IndexController extends AbstractController {

    /**
     * @return Response
     * @param Request                $request
     * @param AutenticadorCASService $autenticadorCAS
     * @Route("/", name="index")
     */
    public function indexAction ( Request $request , AutenticadorCASService $autenticadorCAS ) {


        //La unica accion posible en este proyecto es registro_usuario_paso1
        return $this->redirect($this->generateUrl('_registro_usuarios_paso1'));

    }


    /**
     * @return Response
     * @param Request                $request
     * @param AutenticadorCASService $autenticadorCAS
     * @Route("aplicaciones", name="aplicaciones")
     *
     */
    public function aplicacionesAction ( Request $request , AutenticadorCASService $autenticadorCAS ) {

        //La unica accion posible en este proyecto es registro_usuario_paso1
        return $this->redirect ( $this->generateUrl ( '_registro_usuarios_paso1' ) );
        
    }


    /**
     * accesoAction
     *
     * Envía el usuario a autenticarse a la web del CAS
     * cuando vuelva se va a la pagina principal.
     * @Route("acceso", name="cas_login")
     *
     * @param Request                $request
     * @param AutenticadorCASService $autenticadorCAS
     * @param
     *
     * @return Response
     */
    public function accesoAction ( Request $request , AutenticadorCASService $autenticadorCAS ) {

        /**
         * Se comprueba si está autenticado ya, si autenticado devuelve objeto Usuario
         * si no está autenticado se devuelve FALSE
         */

        /**  Se comprueba si el usuario está autenticado en CAS   //Si es objeto usuario */
        if ( $autenticadorCAS->usuarioAutenticado ( $request ) ) {
            return $this->redirect ( $this->generateUrl ( 'index' ) );
        } else {
            phpCAS::setPostAuthenticateCallback ( 'redirigirAutenticacion' , array( '_cas_homepage' ) );
            //CAS. Forzamos autenticacion
            phpCAS::forceAuthentication ();

            return $this->redirect ( $this->generateUrl ( 'index' ) );
        }

    }
    
    /**
     * salidaAction
     *
     * Funciona publica interface llama a getUsuarioCAS pasando
     * el primer argumento a TRUE ( logOUT )
     * @Route("/logout", name="cas_logout")
     *
     * @param AutenticadorCASService $autenticadorCAS
     */
    public function salidaAction ( AutenticadorCASService $autenticadorCAS ) {
        $autenticadorCAS->cerrarSesion ( $this->generateUrl ( 'index' , array() , UrlGeneratorInterface::ABSOLUTE_URL ) );
    }


    /**
     ************************************************************** *********************************************************************
     * IMPORTANTE: En el proyecto en cuestion, modificar las rutas (parametro @Route) de las acciones de la parte de abajo
     *      habilitarDeshabilitarAplicacionAction
     *      errorAccesoAction
     *      aplicacionDeshabilitadaAction
     * añadiendole el nombre del proyecto
     * descomentar la linea inferior y eliminar la que esta comentada para que acceda a la accion del controlador del proyecto
     * TODO: Ver si puede evitar esta forma modificando la ruta dinamicamente p.e:
     * aplicaciones/{nombreProyecto}/habilitarDeshabilitarAplicacion/{accion}
     * ************************************************************* *********************************************************************
     *
     * /**
     *
     * @Route("/habilitarDeshabilitarAplicacion/{accion}", name="habilitar_deshabilitar_aplicacion")
     * @return array|Response
     */
    public function habilitarDeshabilitarAplicacionAction ( $accion ) {

        /**TODO: cambiar el flag para poner/quitar la aplicacion en mantenimiento. Ahora mismo es el atributo WssseListener::sitioEnMantenimiento*/
        $fichero = $this->getParameter ( 'fichero_aplicacion_deshabilitada' );

        if ( $accion == 0 ) {

            file_put_contents ( $fichero , time () );
        } else {
            unlink ( $fichero );
        }

        return $this->redirect ( $this->generateUrl ( 'aplicaciones' ) );
    }

    /**
     *
     * @Route("/erroracceso", name="error_acceso")
     * @return array
     * @Template("/Base/errorAcceso.html.twig")
     */
    public function errorAccesoAction () {
        return array();
    }


    /**
     * @Route("/aplicaciondeshabilitada", name="aplicacion_deshabilitada")
     * @return array|RedirectResponse
     * @Template("/Base/aplicacionDeshabilitada.html.twig")
     */
    public function aplicacionDeshabilitadaAction ( Request $request ) {

        if ( !$request->getSession ()->get ( 'aplicacionDeshabilitada' ) ) {
            return $this->redirect ( $this->generateUrl ( 'aplicaciones' ) );
        } else {
            return array();
        }
    }

    /**
     * @Route("/incio/desarrollo/suplantaridentidad", name="_suplantar_identidad")
     * @param Request                  $request
     * @param SuplantadorIdentidadService $oSuplantador
     * @Template("/Base/suplantarIdentidad.html.twig")
     * @return RedirectResponse|array
     */
    public function suplantarIdentidadAction ( Request $request, ARUServices $ARUServices , SuplantadorIdentidadService $oSuplantador ) {
        
        if ( $oSuplantador->esSuplantarIdentidadHabilitada () ) {

            $ARUServices->getLogger()->debug('Se entra a suplantar.'); 

            
            $oSuplantarIdentidad     = new SuplantarForm();
            $oFormSuplantarIdentidad = $this->createForm ( SuplantarForm::class , $oSuplantarIdentidad );

            $oFormSuplantarIdentidad->handleRequest ( $request );

            if ( $oFormSuplantarIdentidad->isSubmitted () && $oFormSuplantarIdentidad->isValid () ) {
                
                try{
                    $ARUServices->getLogger()->debug('Suplantando "'.$this->getUser()->getAdnipa().'" => "'.$oSuplantarIdentidad->getDni ().'"' );
                    $oSuplantador->suplantarIdentidad ( $oSuplantarIdentidad->getDni () );
                    return new RedirectResponse( $this->generateURL ( '_registro_usuarios_paso1' ) );
                } catch (Exception $ex) {
                    $ARUServices->getLogger()->debug(Utilidades::controlarError($ex)->getMessage());
                    $oFormSuplantarIdentidad->addError ( new FormError( Utilidades::controlarError($ex)->getMessage() ) );
                }
                
            }
            
            return array( 'form' => $oFormSuplantarIdentidad->createView () );
            
        } else {
        
            $request->getSession ()->getFlashBag ()->add ( 'error' , 'La suplantacion de identidad no esta disponible.' );

            return array();
        }
    }

    /**
     * @Route("/incio/desarrollo/abandonarsuplantaridentidad", name="_abandonar_suplantar_identidad")
     * @param SuplantadorIdentidadService $oSuplantador
     * @return RedirectResponse|array
     */
    public function abandonarSuplantarIdentidadAction ( SuplantadorIdentidadService $oSuplantador ) {

        if ( $oSuplantador->esIdentidadSuplantada () ) {
            /** @var UsuarioSuplantado $oUsuarioSuplantado */
            $oUsuarioSuplantado = $oSuplantador->obtenerInformacionSuplantacion ();
            if ( $oUsuarioSuplantado ) {
                $oSuplantador->deshacerSuplantacionIdentidad ( $oUsuarioSuplantado );
            }
        }

        return new RedirectResponse( $this->generateURL ( '_registro_usuarios_paso1' ) );
    }
}