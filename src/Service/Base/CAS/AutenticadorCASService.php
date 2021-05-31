<?php

namespace App\Service\Base\CAS;

use App\Entity\Base\Tusuario;
use App\Lib\Base\Utilidades;
use App\Orm\Base\PermisosUsuarioOrm;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AutenticadorCAS
 *
 * Es instanciada por el controlador para realizar la autenticacion por CAS
 * Se basa en las librerias PHPCAS para la autenticacion del framework
 */
class AutenticadorCASService {
    #Autenticacion: CAS

    //const CAS_HOST = "";
    const CAS_CONTEXT = "/cas";
    const CAS_PORT = 8443;
    //const CAS_REAL_HOSTS = array("", "");

    #Autenticacion= LDAP
    //const LDAP_SERVIDOR = "";
    const LDAP_RAMA = "Todos";
    const LDAP_INICIO = "0";
    const LDAP_WS_BUSQUEDAUSUARIOS = "BusquedaUsuarios";
    const LDAP_CADENA = "";

    /** @var \Doctrine\Common\Persistence\ManagerRegistry */
    private $doctrine;

    /** @var PermisosUsuarioOrm $permisosUsuario */
    private $permisosUsuario;

    /**
    * @var \Symfony\Bridge\Monolog\Logger
    */
    private $Logger;    
    
    /**
     * AutenticadorCASService constructor.
     *
     * @param ManagerRegistry    $oDoctrine
     * @param PermisosUsuarioOrm $oPermisosUsuario
     */
    public function __construct(ManagerRegistry $oDoctrine, PermisosUsuarioOrm $oPermisosUsuario) {
                
        $this->cargarLibreriasCAS();
        $this->doctrine = $oDoctrine;
        $this->permisosUsuario = $oPermisosUsuario;
        $this->Logger  = $oPermisosUsuario->getLogger();
    }

    /**
     * cargarLibreriasCAS
     *
     * Carga las librerias CAS y las configuraciones
     *
     *
     */
    private function cargarLibreriasCAS() {

        if (!defined("PHPCAS_VERSION")) {
            require_once ( __DIR__ . '/../../../Lib/CASLib/CAS.php' );
        }

        /**
         * =========================ATENCION=======================
         * No eliminar estas variables ya que se usan dentro de Lib/CASLib/config.php
         * */
        $cas_host = self::CAS_HOST;
        $cas_port = self::CAS_PORT;
        $cas_context = self::CAS_CONTEXT;

        require_once ( __DIR__ . '/../../../Lib/CASLib/config.php' );
    }

    /**
     * getUsuarioCAS
     *
     * Autentica un usuario contra el CAS y obtiene sus datos del LDAP
     *
     * @param Request $request
     *
     * @return mixed Devuelve objeto Usuario o FALSE en caso de que no esté autenticado
     */
    public function getUsuarioCAS(Request $request) {
        
        $this->permisosUsuario->getLogger()->addDebug(__METHOD__.':'.__LINE__  , array('request' => $request));        

        if (!\phpCAS::clienteCASdefinido()) {

            \phpCAS::client(CAS_VERSION_2_0, self::CAS_HOST, self::CAS_PORT, self::CAS_CONTEXT, true);
        }
        
        


        \phpCAS::handleLogoutRequests(TRUE, self::CAS_REAL_HOSTS);

        /** CAS. No validamos el certificado del servidor. En produccion si que debemos validar. */
        \phpCAS::setNoCasServerValidation();
        
        if ( \phpCAS::isAuthenticated() ){
            $this->permisosUsuario->getLogger()->addDebug('Usuario Autenticado.', array());
        } 
        else{
            $this->permisosUsuario->getLogger()->addDebug('Usuario NO Autenticado', array());
        } 
        
        /**  CAS. Comprobamos si está autenticado ( true si está autenticado ) */
        if (\phpCAS::checkAuthentication()) {
            $this->permisosUsuario->getLogger()->addDebug('Usuario autenticado CAS cargando datos');
            /**
             * Si esta autenticado el objeto usuario se mira si esta en sesion, si no se obtiene de la BD (la primera vez)
             */
            if ($request->getSession()->get('userObject', NULL)) {
                $this->permisosUsuario->getLogger()->addDebug('Cargamos usuario de la Session' , 
                        array('userObject' => $request->getSession()->get('userObject')));
                /** @var Tusuario $oUsuario */
                $oUsuario = $request->getSession()->get('userObject', NULL);
            } else {
                $this->permisosUsuario->getLogger()->addDebug('Cargamos usuario de la TUSUARIOS1' , 
                        array('userObject' => $request->getSession()->get('userObject', NULL)));
                
                $oUsuario = $this->permisosUsuario->getUsuario(\phpCAS::getUser());
            }

            //Si ya es un objeto se intenta recuperar los datos de la BD
            if (is_object($oUsuario)) {
                $this->permisosUsuario->getLogger()->addDebug('Cargamos usuario de la BD' , array('userObject' => $oUsuario));
                $aNombreApellidos = $this->permisosUsuario->obtenerNombreApellidoBD($oUsuario->getAdnipa());

                $oUsuario->setNombre($aNombreApellidos['nombre']);
                $oUsuario->setApellido1($aNombreApellidos['apellido1']);
                $oUsuario->setApellido2($aNombreApellidos['apellido2']);
                $oUsuario->setTipoDocumento($aNombreApellidos['tipoDocumento']);
                $oUsuario->setDenomTipoDocumento($aNombreApellidos['denomTipoDocumento']);
                $oUsuario->setLetraDocumento($aNombreApellidos['letraDocumento']);
                $oUsuario->setDocumentoCompleto($aNombreApellidos['documentoCompleto']);
                $oUsuario->setIpConexion(Utilidades::getClienteIP());
                 
            } else {
                $this->permisosUsuario->getLogger()->addDebug('Cargamos usuario del LDAP' , array('userObject' => $oUsuario));                
                list ( $sNombreUsuario, $sApellido1, $sApellido2 ) = $this->establecerDatosUsuario($oUsuario);
            }
            
            
            

            if (is_null($oUsuario)) {

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
                $oUsuario = new Tusuario();
                $oUsuario->setAdnipa(strtoupper(\phpCAS::getUser()));
                $oUsuario->setAtipousuario('X');
                //$oUsuario->setRoles ( $this->permisosUsuario->obtenerRolesUsuario ( \phpCAS::getUser () ) );                
                $oUsuario->setNombre($sNombreUsuario);
                $oUsuario->setApellido1($sApellido1);
                $oUsuario->setAplicaciones($this->permisosUsuario->obtenerAplicacionesUsuario(\phpCAS::getUser()));
                $oUsuario->setApellido2($sApellido2);
                $oUsuario->setIpConexion(Utilidades::getClienteIP());
                Utilidades::depurarUsuario($oUsuario, 'davidHome', true);
            } else {
                $oUsuario->setAdnipa(strtoupper($oUsuario->getAdnipa()));
            }

            
            $oUsuario->setRoles($this->permisosUsuario->obtenerRolesUsuario(\phpCAS::getUser()));

            $this->saveUser($request, $oUsuario);

            return $oUsuario;
        } else {

            session_destroy();

            return FALSE;
        }
    }

    /**
     * @param $sUrlRegresoCAS
     * @return bool
     */
    public function cerrarSesion($sUrlRegresoCAS) {
        \phpCAS::client(CAS_VERSION_2_0, self::CAS_HOST, self::CAS_PORT, self::CAS_CONTEXT, true);

        \phpCAS::handleLogoutRequests(true, self::CAS_REAL_HOSTS);

        /** CAS. No validamos el certificado del servidor. En produccion si que debemos validar. */
        \phpCAS::setNoCasServerValidation();

        /** CAS. Gestionamos las peticiones de logout que lleguen */
        \phpCAS::handleLogoutRequests(true, self::CAS_REAL_HOSTS);

        /** CAS. Forzamos cierre de sesion y redirigimos */
        \phpCAS::logoutWithRedirectService($sUrlRegresoCAS);

        return false;
    }

    /**
     * void function saveUser
     *
     * Guarda un usario en la session
     *
     * @param Request  $request
     * @param Tusuario $user
     */
    public function saveUser(Request $request, $user) {

        $IDAplicacion = $this->permisosUsuario->GenerarIDAplicacion();

        $user->setIDAplicacion($IDAplicacion);

        $this->getLogger()->addDebug(__METHOD__.':'.__LINE__  ,
                array('request' => $request,
                    'user' => $user,
                    'idAplicacion' => $IDAplicacion));

        $request->getSession()->set('userObject', $user);
    }

    /**
     * getDatosUsuarioLDAP
     *
     * Obtiene los datos de un usuario del LDAP a partir  del DNI
     *
     * @param Tusuario $oUsuario
     *
     * @return array
     */
    private function establecerDatosUsuario(&$oUsuario = false) {
        
        $this->permisosUsuario->getLogger()->addDebug(__METHOD__.':'.__LINE__   , array('userObject' => $oUsuario));        

        $sApellido2 = '';
        $sNombreUsuario = '';
        $sApellido1 = '';
        foreach (\phpCAS::getAttributes() as $key => $value) {
            if ($key == 'Nombre') {
                $sNombreUsuario = $value;
            }
            if ($key == 'Apellidos') {
                $sApellido1 = $value;
            }
        }
        if (is_object($oUsuario)) {
            $oUsuario->setNombre($sNombreUsuario);
            $oUsuario->setApellido1($sApellido1);
            $oUsuario->setApellido2($sApellido2);
            $oUsuario->setIpConexion(Utilidades::getClienteIP());
        }


        return array($sNombreUsuario, $sApellido1, $sApellido2);
    }

    /**
     * Se comprueba si el usuario está autenticado en CAS
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return bool
     */
    public function usuarioAutenticado(Request $request) {
        return is_object($this->getUsuarioCAS($request));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Tusuario
     */
    public function obtenerUsuarioAutenticado(Request $request) {
        return $request->getSession()->get('userObject', null);
    }

    /**
     *
     * @return Symfony\Bridge\Monolog\Logger
     */
    public function getLogger() {
        return $this->Logger;
    }

    /**
     *
     * @param \Symfony\Bridge\Monolog\Logger $Logger
     */
    public function setLogger($Logger) {
        $this->Logger = $Logger;
    }

}
