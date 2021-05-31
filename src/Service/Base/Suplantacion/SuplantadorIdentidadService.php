<?php

/**
 * File:    SuplantadorIdentidadService.php
 * User:    ULPGC
 * Project: symfony4
 */

namespace App\Service\Base\Suplantacion;

use App\Lib\Base\UsuarioSuplantado;
use App\Orm\Base\PermisosUsuarioOrm;
use App\Service\Base\Security\Authentication\Token\WsseUserToken;
use \Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use \Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Bridge\Monolog\Logger;
use App\Lib\Base\Utilidades;

class SuplantadorIdentidadService {

    /**
     * Constantes definidas en config/services.yaml
     * nombre_app_suplantada contiene el identificador de la aplicacion sobre la cual se desea hacer la suplantacion
     */
    const NOMBRE_APP_SUPLANTADA = 'nombre_app_suplantada';
    const SI = 'S';
    const NO = 'N';
    const ENTORNO_DE_DESARROLLO = 'dev';
    const PREFIJO_IDENTIFICADOR_INFORMACION_SUPLANTACION = 'informacion_identidad_suplantada';

    protected $nombreAppSuplantada;
    protected $permisosUsuarioORM;
    protected $bSiEntornoEsDesarrollo;
    protected $container;

    /**
     * SuplantadorIdentidadService constructor.
     *
     * @param PermisosUsuarioOrm $permisosUsuarioORM
     */
    public function __construct(PermisosUsuarioOrm $permisosUsuarioORM, Logger $Logger) {
        $this->setOrm($permisosUsuarioORM);

        $this->getOrm()->setLogger($Logger);
        $this->getOrm()->getLogger()->addDebug('Cargando LOG en "' . basename(__FILE__) . '>' . __FUNCTION__ . '"');
        /**
         * Obtenemos el contenedor del orm para obtener los parametros de configuracion
         */
        $this->setContainer($permisosUsuarioORM->getContainer());
        $this->setNombreAppSuplantada($this->getContainer()->getParameter(self::NOMBRE_APP_SUPLANTADA));

        /**
         * En Symfony v4 el entorno lo obtenemos de la variable $_SERVER['APP_ENV'
         */
        $this->setSiEntornoEsDesarrollo($_SERVER['APP_ENV'] == self::ENTORNO_DE_DESARROLLO);
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event) {
        /**
         * @since 15/03/2018
         *        Se comprueba que el usuario conectado no haya suplantado identidad en otra aplicacion y haya intentado acceder a esta aplicacion
         *        con las credenciales suplantadas
         */
        $this->comprobarSuplantacionIdentidad();
    }

    /**
     * Comprueba si la caracteristica "suplantar identidad" esta habilitada para esta aplicacion con los siguientes criterios:
     *
     * - El entorno de ejecucion es desarrollo
     * - El nombre de la aplicacion suplantada esta establecido en config/services.yaml:nombre_app_suplantada
     *
     * @return bool
     */
    public function esSuplantarIdentidadHabilitada() {
        return $this->getSiEntornoEsDesarrollo() && !empty($this->getNombreAppSuplantada());
    }

    /**
     * @param $sDni
     * @param $sError
     * @return bool
     */
    public function suplantarIdentidad($sDni) {
        try {
            if ($this->getSiEntornoEsDesarrollo()) {
                if (!empty($this->getNombreAppSuplantada())) {
                    if (!empty($sDni)) {
                        try {
                            $aRoles = $this->obtenerRolesUsuario($sDni);
                            $this->getOrm()->getLogger()->addDebug('Suplantando DNI "' . $sDni . '"', array($sDni));
                            $sNombreUsuario = $this->getOrm()->obtenerNombreCompleto($sDni);
                        } catch (\Exception $ex) {
                            $this->getOrm()->getLogger()->addDebug(\App\Lib\Base\Utilidades::controlarError($ex->getMessage()), array($sDni));
                            throw new \Exception('Ocurrió un error al obtener el nombre del usuario con DNI: ' . $sDni . ' ' . $ex->getMessage());
                        }

                        /**
                         * @var UsuarioSuplantado $oUsuarioSuplantado
                         * Creamos un objeto donde residira toda la informacion acerca de la suplantacion tanto del usuario suplantado
                         * como del usuario impostor
                         */
                        $oUsuarioSuplantado = new UsuarioSuplantado($sDni, $sNombreUsuario, $aRoles, array(), NULL,
                                $this->getNombreAppSuplantada());

                        $aNombreApellidos = $this->getOrm()->obtenerNombreApellidoBD($sDni);

                        $oUsuarioSuplantado->setNombre($aNombreApellidos['nombre']);
                        $oUsuarioSuplantado->setApellido1($aNombreApellidos['apellido1']);
                        $oUsuarioSuplantado->setApellido2($aNombreApellidos['apellido2']);
                        $oUsuarioSuplantado->setTipoDocumento($aNombreApellidos['tipoDocumento']);
                        $oUsuarioSuplantado->setDenomTipoDocumento($aNombreApellidos['denomTipoDocumento']);
                            $oUsuarioSuplantado->setLetraDocumento($aNombreApellidos['letraDocumento']);
                            $oUsuarioSuplantado->setDocumentoCompleto($aNombreApellidos['documentoCompleto']);

                        $bSuplantado = $this->sustituirRolesUsuario($aRoles, $oUsuarioSuplantado);

                        return $bSuplantado;
                    } else {
                        throw new \Exception('No se ha facilitado el dni del usuario que desea suplantar.');
                    }
                } else {
                    throw new \Exception('La suplantacion de identidad en esta aplicacion esta deshabilitada. Establezca el valor del parametro nombre_app_suplantada para usar esta funcionalidad.');
                }
            } else {
                throw new \Exception('Esta funcionalidad esta disponible unicamente en el entorno de desarrollo');
            }
        } catch (\Exception $e) {
            throw new \Exception(\App\Lib\Base\Utilidades::controlarError($e)->getMessage());
        }
    }

    /**
     * @param string $sDni
     * @return array
     * @throws \Exception
     */
    protected function obtenerRolesUsuario($sDni) {
        /**
         * *********************************************************************************************************************************
         * *********************************************************************************************************************************
         * ********************************************************IMPORTANTE***************************************************************
         * *********************************************************************************************************************************
         * *********************************************************************************************************************************
         * Este metodo obtiene los roles que tiene un usuario a partir de ULPGES.VROLESTOTALES que es la forma mas habitual de obtener los
         * roles a suplantar. Si se desea obtener los roles desde otro origen, reescriban este metodo devolviendo un array de roles valido:
         * return array('ROLE_1','ROLE2_2','ROLE_N');
         *
         *
         */
        try {
            $aRoles = $this->getOrm()->obtenerRolesUsuario($sDni);

            if (count($aRoles) == 0) {
                throw new \Exception('Imposible suplantar identidad. El usuario con DNI: ' . $sDni . ' no dispone de roles asociados.');
            }

            return $aRoles;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     *
     * Se va a sesion a recoger el usuario autenticado y sobre el token autenticado de symfony se sobreescriben los roles
     * pasados por parametro
     *
     * Posteriormente se guarda en sesion el nuevo token con los nuevos roles
     *
     * @param array             $aRoles
     * @param UsuarioSuplantado $oUsuarioSuplantado
     * @return bool
     * @throws \Exception
     */
    protected function sustituirRolesUsuario($aRoles, UsuarioSuplantado $oUsuarioSuplantado) {

        /**
         * @var TokenInterface $token
         */
        $token = $this->getContainer()->get('security.token_storage')->getToken();
        if ($token) {
            $user = $token->getUser();

            /**
             * @var Session $session
             */
            $session = $this->getContainer()->get('session');

            /** Grabamos los roles y el dni del usuario impostor en el objeto $oUsuarioSuplantado para poder restablecer los roles al finalizar
             * la suplantacion */
            /** @var \App\Entity\Base\Tusuario $oUsuarioActual */
            $oUsuarioActual = $session->get('userObject', NULL);
            $oUsuarioSuplantado->setDniImpostor($oUsuarioActual->getAdnipa());
            $oUsuarioSuplantado->setRolesImpostor($oUsuarioActual->getRoles());
            $this->establecerInformacionSuplantacion($oUsuarioSuplantado);

            /*             * * Establecemos los nuevos roles del usuario a suplantar en el usuario impostor */
            $user->setRoles($aRoles);
            $myToken = new WsseUserToken($user->getRoles());
            $myToken->setAuthenticated(TRUE);
            $myToken->setUser($user);

            $this->getContainer()->get('security.token_storage')->setToken($myToken);
            $session->set('userObject', $user);

            return TRUE;
        } else {
            throw new \Exception('Imposible suplantar identidad. El usuario actual parece no estar autenticado. Por favor, inicie sesion nuevamente');
        }
    }

    /**
     *
     * Se va a sesion a recoger el usuario impostor y se reestablecen los roles originales del usuario conectado
     *
     * Posteriormente se guarda en sesion el nuevo token con los nuevos roles
     *
     * @param UsuarioSuplantado $oUsuarioSuplantado
     * @return bool
     */
    protected function restituirRolesUsuario(UsuarioSuplantado $oUsuarioSuplantado) {
        /**
         * @var TokenInterface $token
         */
        $token = $this->getContainer()->get('security.token_storage')->getToken();
        if ($token) {
            $user = $token->getUser();

            /*             * * Restablecemos los roles originales del usuario impostor */
            $user->setRoles($oUsuarioSuplantado->getRolesImpostor());
            $myToken = new WsseUserToken($user->getRoles());
            $myToken->setAuthenticated(TRUE);
            $myToken->setUser($user);

            $this->getContainer()->get('security.token_storage')->setToken($myToken);

            /*             * * @var Session $session */
            $session = $this->getContainer()->get('session');
            $session->set('userObject', $user);

            return TRUE;
        }
    }

    /**
     * Elimina la informacion relativa a la suplantacion de identidad y sustituye los roles originales del usuario del CAS
     */
    public function deshacerSuplantacionIdentidad(UsuarioSuplantado $oUsuarioSuplantado) {
        $this->restituirRolesUsuario($oUsuarioSuplantado);

        /*         * * @var Session $session */
        $session = $this->getContainer()->get('session');

        $session->set(self::PREFIJO_IDENTIFICADOR_INFORMACION_SUPLANTACION, NULL);
        $session->remove(self::PREFIJO_IDENTIFICADOR_INFORMACION_SUPLANTACION);
    }

    /**
     * Si se esta suplantando identidad el dni a devolver sera el del usuario suplantado, sino se devuelve NULL
     *
     * @return string|null
     */
    public function obtenerDNIUsuarioSuplantado() {
        $sDni = "";
        if ($this->esIdentidadSuplantada()) {
            $oUsuarioSuplantado = $this->obtenerInformacionSuplantacion();
            if (is_object($oUsuarioSuplantado) && ( $oUsuarioSuplantado instanceof UsuarioSuplantado )) {
                $sDni = $oUsuarioSuplantado->getDni();
            }
        }

        return $sDni;
    }

    /**
     * Si se esta suplantando identidad el dni a devolver sera el del usuario suplantado, sino se devuelve NULL
     *
     * @return string|null
     */
    public function obtenerNombreUsuarioSuplantado() {
        $sNombre = "";
        if ($this->esIdentidadSuplantada()) {
            $oUsuarioSuplantado = $this->obtenerInformacionSuplantacion();
            if (is_object($oUsuarioSuplantado) && ( $oUsuarioSuplantado instanceof UsuarioSuplantado )) {
                $sNombre = $oUsuarioSuplantado->getNombreCompleto();
            }
        }

        return $sNombre;
    }

    /**
     * Este metodo deberia ser invocado desde cualquier lugar del framework para comprobar si se esta suplantando identidad y sobre quien
     * El criterio para averiguar si se esta suplantando la identidad consiste en comprobar los siguientes items:
     *  - Que el entorno de ejecucion sea desarrollo
     *  - Que el parametro nombre_app_suplantada del fichero config/packages/services.yaml contenga un valor <> ''
     *  - Que la aplicacion en la que el usuario suplanto la identidad sea la misma que la aplicacion actual
     *
     * Esto implica que el fichero config/packages/services.yaml contiene un parametro nombre_app_suplantada cuyo valor es <> ''
     *
     * @return bool
     */
    public function esIdentidadSuplantada() {
        return $this->comprobarSuplantacionIdentidad();
    }

    /**
     * Este metodo deberia ser invocado desde cualquier lugar del framework para comprobar si se esta suplantando identidad y sobre quien
     * El criterio para averiguar si se esta suplantando la identidad consiste en comprobar los siguientes items:
     *  - Que el entorno de ejecucion sea desarrollo
     *  - Que el parametro nombre_app_suplantada del fichero config/packages/services.yaml contenga un valor <> ''
     *  - Que la aplicacion en la que el usuario suplanto la identidad sea la misma que la aplicacion actual
     *
     * Esto implica que el fichero config/packages/services.yaml contiene un parametro nombre_app_suplantada cuyo valor es <> ''
     *
     * @return bool
     */
    public function comprobarSuplantacionIdentidad() {
        $bResultado = FALSE;
        if ($this->getSiEntornoEsDesarrollo()) {
            if (!empty($this->getNombreAppSuplantada())) {
                $oUsuarioSuplantado = $this->obtenerInformacionSuplantacion();
                if (is_object($oUsuarioSuplantado) && ( $oUsuarioSuplantado instanceof UsuarioSuplantado )) {
                    $bResultado = $oUsuarioSuplantado->getAplicacionSuplantada() == $this->getNombreAppSuplantada();

                    /** Si se ha llegado implica que se esta ejecutando la suplantacion en una aplicacion diferente a la que el usuario
                     *  suplanto por lo tanto, restituimos los roles originales del usuario impostor */
                    if (!$bResultado) {
                        $this->deshacerSuplantacionIdentidad($oUsuarioSuplantado);
                    }
                }
            }
        } else {
            /**
             * @since 12/04/2018
             *        Si se cambia el entorno de desarrollo (con la suplantacion activa) a produccion, la suplantacion permanece
             */
            if (!empty($this->getNombreAppSuplantada())) {
                $oUsuarioSuplantado = $this->obtenerInformacionSuplantacion();
                if (is_object($oUsuarioSuplantado) && ( $oUsuarioSuplantado instanceof UsuarioSuplantado )) {
                    $this->deshacerSuplantacionIdentidad($oUsuarioSuplantado);
                }
            }
        }

        return $bResultado;
    }

    /**
     * Este metodo guarda en sesion la informacion relativa al usuario suplantado y al usuario impostor
     *
     * El criterio para grabar y recuperar esta informacion consiste en establecer el objeto de tipo UsuarioSuplantado
     * en la la variable de sesion cuyo zocalo se construye dinamicamente atendiendo al parametro 'nombre_app_suplantada' de forma que:
     *
     * $_SESSION['informacion_identidad_suplantada_actasweb_tft'] = $oUsuarioSuplantado si se esta suplantando identidad en actasweb_tft
     * (por ejemplo)
     *
     * Esto implica que el fichero config/services.yaml contiene un parametro nombre_app_suplantada cuyo valor es 'actasweb_tft'
     *
     * @param UsuarioSuplantado $oUsuarioSuplantado
     */
    protected function establecerInformacionSuplantacion(UsuarioSuplantado $oUsuarioSuplantado) {
        /*         * * @var Session $session */
        $session = $this->getContainer()->get('session');
        $session->set(self::PREFIJO_IDENTIFICADOR_INFORMACION_SUPLANTACION, $oUsuarioSuplantado);
    }

    /**
     * @return UsuarioSuplantado|null
     */
    public function obtenerInformacionSuplantacion() {
        /*         * * @var Session $session */
        $session = $this->getContainer()->get('session');

        return $session->get(self::PREFIJO_IDENTIFICADOR_INFORMACION_SUPLANTACION, NULL);
    }

    /**
     * @return mixed
     */
    protected function getNombreAppSuplantada() {
        return $this->nombreAppSuplantada;
    }

    /**
     * @param mixed $nombreAppSuplantada
     */
    protected function setNombreAppSuplantada($nombreAppSuplantada) {
        $this->nombreAppSuplantada = $nombreAppSuplantada;
    }

    /**
     * @return PermisosUsuarioOrm
     */
    protected function getOrm() {
        return $this->permisosUsuarioORM;
    }

    /**
     * @param PermisosUsuarioOrm $permisosUsuarioORM
     */
    protected function setOrm(PermisosUsuarioOrm $permisosUsuarioORM) {
        $this->permisosUsuarioORM = $permisosUsuarioORM;
    }

    /**
     * @return \Symfony\Component\DependencyInjection\Container
     */
    protected function getContainer() {
        return $this->container;
    }

    /**
     * @param mixed $container
     */
    protected function setContainer($container) {
        $this->container = $container;
    }

    /**
     * @return mixed
     */
    public function getSiEntornoEsDesarrollo() {
        return $this->bSiEntornoEsDesarrollo;
    }

    /**
     * @param mixed $bSiEntornoEsDesarrollo
     */
    protected function setSiEntornoEsDesarrollo($bSiEntornoEsDesarrollo) {
        $this->bSiEntornoEsDesarrollo = $bSiEntornoEsDesarrollo;
    }

    /**
     * 
     * @return Logger
     */
    public function getLogger() {
        return $this->getOrm()->getLogger();
    }

}
