<?php

/**
 * File: PermisosUsuario.php
 * User: ULPGC
 * Email: desarrollo@ulpgc.es
 * Description: UTF-8
 * Realiza la carga de aplicaciones y roles que posee un usuario
 * Accede a la BD para obtenerlos (esquema ULPGES)
 */

namespace App\Orm\Base;

use App\Entity\Base\Tusuario;
use App\Lib\Base\Utilidades;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class PermisosUsuario
 */
class PermisosUsuarioOrm extends GenericOrm {

    /**
     * conexion para procedimientos almacenados
     */
    const DBAL_CONNECTION = 'ulpges';

    /**
     * conexion para el uso de doctrine
     */
    const ORM_CONNECTION = 'ulpges_connection';
    const ESQUEMA = 'ULPGES';
    const PAQUETE = 'PACKSYMFONY';

    public function __construct(
            ManagerRegistry $doctrine,
            ContainerInterface $container,
            AuthorizationCheckerInterface $authorizationChecker,
            LoggerInterface $logger
    ) {


        parent::__construct($doctrine, $container, $authorizationChecker, self::ESQUEMA, self::PAQUETE, self::DBAL_CONNECTION,
                self::ORM_CONNECTION, $logger);
        $this->getLogger()->addDebug('Cargando LOG en "' . basename(__FILE__) . '>' . __FUNCTION__ . '"');
    }

    /**
     * getUsuario
     * Devuelve un usuario con los roles y aplicaciones a las que tiene acceso
     *
     * @param $paDni
     *
     * @return Tusuario
     */
    public function getUsuario($paDni) {

        $paDni = strtoupper($paDni);


        $oRepository = $this->getManager(Tusuario::class);


        $oUsuario = $oRepository->findOneBy(array('adnipa' => $paDni));


        if ($oUsuario) {

            $oUsuario->setAplicaciones($this->obtenerAplicacionesUsuario($paDni));

            $oUsuario->setRoles($this->obtenerRolesUsuario($paDni));

            $oUsuario->setIpConexion($_SERVER['REMOTE_ADDR']);


            return $oUsuario;
        }

        return $oUsuario;
    }

    /**
     *
     * @param string $paDni : Dni del usuario al que se obtiene las aplicaciones
     * @return array $Aplicaciones. Aplicaciones del entorno Symfony que el usuario tiene
     *                      asignada en el esquema ULPGES
     */
    public function obtenerAplicacionesUsuario($paDni) {

        $this->getLogger()->addDebug(__METHOD__ . ':' . __LINE__, array('sdni' => $paDni));
        $conexion = $this->getConnection('PROCOBTENERAPLICACIONESUSUARIO');

        $cursor = $conexion->getWrappedStatement()->PROCOBTENERAPLICACIONESUSUARIO($paDni, NULL);


        $aAplicaciones = array();


        if ($cursor) {

            while ($aplicacion = $conexion->getWrappedStatement()->fetchCursor($cursor)) {

                $aAplicaciones[] = $aplicacion['APLICACION'];
            }
        }

        return $aAplicaciones;
    }

    /**
     *
     * @param string $paDni : Dni del usuario al que se obtiene los roles
     * @return array $aRoles. Roles para las aplicaciones Symfony que el usuario tiene
     *                      asignados en el esquema ULPGES
     */
    public function obtenerRolesUsuario($paDni) {

        $conexion = $this->getConnection('PROCOBTENERROLESUSUARIO');

        $cursor = $conexion->getWrappedStatement()->PROCOBTENERROLESUSUARIO($paDni, null);

        $aRoles = array();

        if ($cursor) {

            while ($rol = $conexion->getWrappedStatement()->fetchCursor($cursor)) {

                $aRoles[] = $rol['ROL'];
            }
        }

        $this->getLogger()->debug('Usuario autenticado "' . $paDni . '"', $aRoles);

        return $aRoles;
    }

    /**
     * Obtiene el nombre completo del usuario
     *
     * @param $sDni
     * @param $sError
     * @return string
     *
     */
    public function obtenerNombreCompleto($sDni) {
        try {

            if (empty($sDni)) {
                throw new Exception('No se ha facilitado dni para obtener el nombre del usuario mediante FUNCAPENOM');
            }

            $conexion = $this->getConnection('FUNCAPENOM');

            $sResultado = $conexion->getWrappedStatement()->FUNCAPENOM($sDni);


            if (preg_match('/^\?{3}.*\?{3}/', $sResultado)) {
                throw new Exception(Utilidades::controlarError('No se encuentra el usuario en la BD')->getMessage());
            }

            return $sResultado;
        } catch (Exception $e) {
            throw new Exception(Utilidades::controlarError($e)->getMessage());
        }
    }

    /**
     *
     * @param string $sDni
     * @return type
     * @throws type
     */
    public function obtenerNombreApellidoBD($sDni) {

        try {

            $conexion = $this->getDoctrine()->getConnection($this->getDbalConnection());

            $sqlDatosPersonales = 'select PACKDATOSPERSONALES.FuncAnomba(\'' . $sDni . '\') NOMBRE, '
                    . 'PACKDATOSPERSONALES.FuncAape1a(\'' . $sDni . '\')  APE1, '
                    . 'PACKDATOSPERSONALES.FuncAape2a(\'' . $sDni . '\')  APE2, '
                    . 'PACKDATOSPERSONALES.FuncCtdoca(\'' . $sDni . '\') tipoDocumento, '
                    . 'PACKDATOSPERSONALES.FuncDenomTipoDoc(PACKDATOSPERSONALES.FuncCtdoca(\'' . $sDni . '\') ) denomTipoDocumento, '
                    . 'PACKDATOSPERSONALES.FuncLetraNIF(\'' . $sDni . '\') letraDocumento, '
                    . 'PACKDATOSPERSONALES.FuncNIF(\'' . $sDni . '\') documentoCompleto from dual';


            $this->getLogger()->addDebug(__METHOD__ . ':' . __LINE__ . '> Nombre apellido usuario [' . $sDni . ']', array('SQL' => $sqlDatosPersonales));
            /** @var \Doctrine\DBAL\Statement $stmt */
            $stmt = $conexion->prepare($sqlDatosPersonales);

            $stmt->execute();


            while ($aRow = $stmt->fetch()) {
                $this->getLogger()->addDebug(__METHOD__ . ':' . __LINE__ . '> Resultado para [' . $sDni . ']', array('Resultado' => $aRow));
                return array('nombre' => $aRow['NOMBRE'], 'apellido1' => $aRow['APE1'],
                    'apellido2' => $aRow['APE2'], 'tipoDocumento' => $aRow['TIPODOCUMENTO'],
                    'denomTipoDocumento' => $aRow['DENOMTIPODOCUMENTO'],
                    'letraDocumento' => $aRow['LETRADOCUMENTO'],
                    'documentoCompleto' => $aRow['DOCUMENTOCOMPLETO']);
            }
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger()
            );
        }
    }
    
    /**
     * Obtiene el nombre de la aplicacion y genera un identificador
     * 
     * @return string sha1 
     */
    public function GenerarIDAplicacion (){
        if ( $this->getContainer()->hasParameter('nombre_app_suplantada') ){
            return sha1($this->getContainer()->getParameter('nombre_app_suplantada'));
        }
        else{
            return sha1('GeneradoAutomaticamente');
        }
    }
}
