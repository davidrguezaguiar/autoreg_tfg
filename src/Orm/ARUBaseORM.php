<?php

/**
 * File:    ARUBaseORM.php
 * User:    ULPGC
 * Project: Aplicación de registro de usuarios
 */

namespace App\Orm;

use App\Lib\Base\Utilidades;
use App\Lib\ARULiterales;
use App\Orm\Base\GenericOrm;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ARUBaseORM extends GenericOrm {

    /**
     * conexion para procedimientos almacenados
     */
    const DBAL_CONNECTION = 'comunDB';

    /**
     * conexion para el uso de doctrine
     */
    const ORM_CONNECTION = 'comundb_connection';
    const ESQUEMA = 'COMUN';
    const PAQUETE = 'PackRegistroWeb';

    /**
     * GMBaseORM constructor.
     *
     * @param ManagerRegistry              $doctrine
     * @param ContainerInterface $container
     */
    public function __construct(ManagerRegistry $doctrine, ContainerInterface $container, AuthorizationCheckerInterface $authorizationChecker, LoggerInterface $ulpgcLogger) {



        parent::__construct($doctrine, $container, $authorizationChecker, self::ESQUEMA, self::PAQUETE, self::DBAL_CONNECTION,
                self::ORM_CONNECTION, $ulpgcLogger);
    }

    /**
     * getMensajeBienvenida
     *
     * OBtiene el mensaje de bienvenida de la BBDD
     *
     * @return string
     */
    public function getMensajeBienvenida() {

        $conexion = $this->getConnection('FUNCMENSAJEINICIAL');        
        try {
            $sMensajeBienvenida = $conexion->getWrappedStatement()->FUNCMENSAJEINICIAL();
        } catch (Exception $ex) {

            throw Utilidades::controlarError($ex, $this->getLogger());
        }


        return $sMensajeBienvenida;
    }

    /**
     * getMensajeCodigoVerificacion
     *
     * Obtiene el mensaje del codigo de verificacion de la BBDD
     *
     * @return string
     */
    public function getMensajeCodigoVerificacion() {

        $conexion = $this->getConnection('FUNCMENSAJEVERIFICA');

        try {

            $sMensajeCodigoVerificacion = $conexion->getWrappedStatement()->FUNCMENSAJEVERIFICA();
        } catch (Exception $ex) {
            throw Utilidades::controlarError($ex, $this->getLogger());
        }



        return $sMensajeCodigoVerificacion;
    }

    /**
     * comprobarExisteIdentidad
     *
     * Comprueba si el documento identificativo existe en la base de datos
     *
     * @return boolean
     */
    public function comprobarExisteIdentidad($documentoIdentidad) {


        $conexion = $this->getConnection('FUNCEXISTEDNI');

        try {
            $sResultado = $conexion->getWrappedStatement()->FUNCEXISTEDNI($documentoIdentidad);

            $this->getLogger()->addDebug('Respuesta metodo  [' . __CLASS__ . '>' . __FUNCTION__ . ':' . __LINE__ . ']',
                    array('parametros' => array($documentoIdentidad),
                        'resultado' => $sResultado));

            if ($sResultado == 'S') {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $this->getLogger());
            return false;
        }
    }

    /**
     * comprobarCorreosExistentes
     *
     * Comprueba si el documento identificativo existe en la base de datos
     *
     * @return array
     */
    public function comprobarCorreosExistentes($documentoIdentidad) {

        $aListaCorreos = array();

        //Se genera codigo de usuario para realizar las comprobaciones
        $sCodigoUsuario = $this->getCodigoUsuario($documentoIdentidad);
        //Se comprueba si existe el usuario actualmente si no existe se devuelve array vacio
        if (!( $this->comprobarExisteIdentidad($sCodigoUsuario) )) {

            return $aListaCorreos;
        }

        $conexion = $this->getConnection('PROCLISTADOEMAILS');

        try {
            /** get datos centro */
            $cursorListaCorreos = $conexion->getWrappedStatement()->PROCLISTADOEMAILS($sCodigoUsuario, null);

            if ($cursorListaCorreos) {

                while ($row = $conexion->getWrappedStatement()->fetchCursor($cursorListaCorreos)) {

                    $sMensaje = strtolower($row['AMAILA']);
                    $sMensaje = preg_replace('([^A-Za-z0-9])', '', $sMensaje);

                    if ($sMensaje == ARULiterales::mensajeUsuarioYaRegistrado || $sMensaje == ARULiterales::mensajeUsuarioYaRegistrado2 || !filter_var($row['AMAILA'], FILTER_VALIDATE_EMAIL)) {
                        $sError = $row['AMAILA'];
                        return $sError;
                    }

                    $aListaCorreos[] = array('amaila' => $row['AMAILA'],
                                             'amaila_ofuscado' => $row['AMAILA_OFUSCADO'],
                    );
                }
            }

            $this->getLogger()->addDebug('Respuesta metodo  [' . __CLASS__ . '>' . __FUNCTION__ . ':' . __LINE__ . ']',
                    array('parametros' => array($sCodigoUsuario),
                          'resultado'  => $aListaCorreos));

            return $aListaCorreos;
        } catch (\Doctrine\DBAL\DBALException $ex) {
            Utilidades::controlarError($ex, $this->getLogger());
            return false;
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $this->getLogger());
            return false;
        }
    }

    /**
     * pedirCodigoVerificacion
     *
     * Esta funcion llama a varias funciones del ORM para comprobar la existencia del usuario en el LDAP o la BD y posteriormente si todo
     * ha ido correctamente se le envia el codigo de verificacion para que siga el proceso de alta en el registro.
     *
     * @param mixed $documentoIdentidad docuemnto de identificacion utilizado
     * @param string $correoElectronico  correo electronico para recibir el codigo de verificacion
     * @param string $sError Mensaje de error que se devuelve
     * @return boolean
     */
    public function pedirCodigoVerificacion($documentoIdentidad, $correoElectronico, &$sError = '') {

        try {


            if (strlen($documentoIdentidad) < 4) {

                $sError = 'RUE003: ' . $this->getError('RUE003');

                //Registro del error en el log
                Utilidades::controlarError($sError, $this->getLogger());
                return false;
            }

            $documentoIdentidad = $this->getCodigoUsuario($documentoIdentidad);

            if ($this->existeDNIenLDAP($documentoIdentidad)) {
                $sError = 'RUE001: ' . $this->getError('RUE001');

                //Registro del error en el log
                Utilidades::controlarError($sError, $this->getLogger());

                $sCodigoUsuario = $this->getCodigoUsuario($documentoIdentidad);

                $sError = 'RUE002: ' . sprintf($this->getError('RUE002'), $sCodigoUsuario);

                //Registro del error en el log
                Utilidades::controlarError($sError, $this->getLogger());

                //Registro del error en el log
                Utilidades::controlarError('Error ' . sprintf($this->getError('RUE001')) . ' Documento: ' . $documentoIdentidad . ' Codigo Usuario:' . $sCodigoUsuario, $this->getLogger());
                return false;
            }


            //Si existe registro anterior hay que devolver el codigo y un error informando al usuario de que tiene que introducir los datos
            // suministrados para entrar.
            if ($this->existeRegistroAnterior($documentoIdentidad)) {

                $sCodigoUsuario = $this->getCodigoUsuario($documentoIdentidad);

                $sError = 'RUE002: ' . sprintf($this->getError('RUE002'), $sCodigoUsuario);
                Utilidades::controlarError($sError, $this->getLogger());

                //Registro del error en el log
                Utilidades::controlarError('Error ' . sprintf($this->getError('RUE001')) . ' Documento: ' . $documentoIdentidad . ' Codigo Usuario:' . $sCodigoUsuario, $this->getLogger());

                return false;
            }

            $conexion = $this->getConnection('PROCENVIACORREOVERIFICA');
        } catch (Exception $ex) {
            throw Utilidades::controlarError($ex, $this->getLogger());
        }

        try {

            if (Utilidades::esDesarrollo()) {
                $this->getLogger()->addDebug('Se reescribe el correo de destino al de desarrollo',
                        array('documento' => $documentoIdentidad,
                            'correoOriginal' => $correoElectronico,
                            'correoDesarrollo' => ARULiterales::correoDesarrollo)
                );
                $correoElectronico = ARULiterales::correoDesarrollo;
            } else {
                //Linea para depuracion en produccion
                $this->getLogger()->addDebug('Llamando a enviar correo verifica',
                        array('documento' => $documentoIdentidad,
                            'correo' => $correoElectronico));
            }
            /** No devuelve nada este procedimiento */
            $conexion->getWrappedStatement()->PROCENVIACORREOVERIFICA($documentoIdentidad, $correoElectronico);

            return true;
        } catch (\Exception $ex) {

            Utilidades::controlarError($ex, $this->getLogger());

            $sError = Utilidades::comprobarErrorOracle($ex);
            return false;
        }
    }

    /**
     * comprobarCodigoVerificacion
     *
     * Se comprueba si el codigo de verificacion casa para el documento identificativo suministrado
     *
     * @param mixed $documentoIdentidad Documento de identificacion
     * @param mixed $codigoVerificacion Codigo de verificacion recibido en el correo electronico
     * @param string $sError Mensaje de error que se devuelve en caso de fallo
     * @return boolean
     */
    public function comprobarCodigoVerificacion($documentoIdentidad, $codigoVerificacion, &$sError = '') {

        if (!( isset($documentoIdentidad) && isset($codigoVerificacion) )) {
            $sError = 'RUE005: ' . $this->getError('RUE005');

            Utilidades::controlarError($sError, $this->getLogger());

            return false;
        }


        try {
            $conexion = $this->getConnection('FUNCVALIDACODIGOVERIFICA');

            $sResultado = $conexion->getWrappedStatement()->FUNCVALIDACODIGOVERIFICA($documentoIdentidad, $codigoVerificacion);

            if ($sResultado == 'S') {
                return true;
            } else {

                $sError = 'RUE006: ' . sprintf($this->getError('RUE006'), $codigoVerificacion, $documentoIdentidad);

                Utilidades::controlarError($sError, $this->getLogger());

                return false;
            }
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $this->getLogger());

            $sError = Utilidades::comprobarErrorOracle($ex);
            return false;
        }
    }

    /**
     * Registra un nuevo usuario en la tabla registroweb
     *
     * @param array  $aDatosUsuario Datos del formulario del usuario
     * @param string $sClaveUsuario Clave del usuario que viene recogida por el PL/SQL
     * @param string $sError Mensaje de error
     * @return boolean
     */
    public function guardarUsuarioNuevo($aDatosUsuario, &$sClaveUsuario = '', &$sError = '') {

        $sCodigoUsuario = $this->getCodigoUsuario($aDatosUsuario['identidad']);

        $conexion = $this->getConnection('PROCGUARDARDATOSCLAVE');

        $sSexo = $aDatosUsuario['sexo'] == 'mujer' ? 'M' : 'V';

        $documentoIdentidad = $aDatosUsuario['identidad'];

        if ($this->existeDNIenLDAP($documentoIdentidad)) {
            $sError = 'RUE001: ' . $this->getError('RUE001');

            Utilidades::controlarError($sError, $this->getLogger(), $sError, array('documentoIdentidad' => $documentoIdentidad));

            return false;
        }


        //Si existe registro anterior hay que devolver el codigo y un error informando al usuario de que tiene que introducir los datos
        // suministrados para entrar.
        if ($this->existeRegistroAnterior($documentoIdentidad)) {

            $sCodigoUsuario = $this->getCodigoUsuario($documentoIdentidad);

            $sError = 'RUE002: ' . sprintf($this->getError('RUE002'), $sCodigoUsuario);

            Utilidades::controlarError($sError, $this->getLogger(), $sError, array('documentoIdentidad' => $documentoIdentidad, 'codigoUsuario' => $sCodigoUsuario));

            return false;
        }

        try {

            //El PLSQL de registro de usuario manda un correo por defecto, 
            // si estamos en desarrollo no lo mando
            if (Utilidades::esDesarrollo()) {
                $cEnviarCorreo = 'N';
            } else {
                $cEnviarCorreo = 'S';
            }

            /** Regisrtar un usuario nuevo
             *
             * pa_Usuario IN VARCHAR2 ,pa_Dnipa IN VARCHAR2 ,pa_Clave OUT VARCHAR2 ,pa_Ape1a IN VARCHAR2 ,pa_Ape2a IN VARCHAR2 ,
             * pa_Nomba IN VARCHAR2 ,pa_Sexoa IN VARCHAR2 ,pa_Fnaca IN VARCHAR2 ,pa_Maila IN VARCHAR2 : = '?' ,
             * pa_Movil IN VARCHAR2 : = '?' ,pa_Dinivalido IN VARCHAR2 : = '?' ,pa_Dfinvalido IN VARCHAR2 : = '?' ,pa_Comentario IN VARCHAR2 : = '?'
             *
             *
             */
            $conexion->getWrappedStatement()->PROCGUARDARDATOSCLAVE($sCodigoUsuario, $aDatosUsuario['identidad'], $sClaveUsuario,
                    $aDatosUsuario['apellido1'], $aDatosUsuario['apellido2'],
                    $aDatosUsuario['nombre'], $sSexo, $aDatosUsuario['fechaNacimiento'],
                    $aDatosUsuario['correoElectronico'], $aDatosUsuario['telefonoMovil'], '?',
                    '?', ARULiterales::TEXTO_COMENTARIO_CREACION_USUARIO, $aDatosUsuario['ipCliente'], $cEnviarCorreo);
        } catch (\Exception $ex) {

            Utilidades::controlarError($ex, $this->getLogger());
            $sError = Utilidades::comprobarErrorOracle($ex);
            return false;
        }

        if (isset($sClaveUsuario)) {
            return true;
        } else {
            return false;
        }
    }

    /*     * ***********************************************************
     * PRIVADAS
     * *********************************************************** */

    /**
     * existeDNIenLDAP
     * 
     * Comprueba si existe el documento de identidad en el LDAP.
     * 
     * @param type $documentoIdentidad
     * @return boolean
     */
    private function existeDNIenLDAP($documentoIdentidad) {


        $conexion = $this->getConnection('FUNCDNIEXISTEENLDAP');

        try {

            $sResultado = $conexion->getWrappedStatement()->FUNCDNIEXISTEENLDAP($documentoIdentidad);


            if ($sResultado == 'S') {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $this->getLogger());
            return false;
        }
    }

    /**
     * existeRegistroAnterior
     *
     * Comprueba si existe un registro anterior de este usuario.
     * 
     * @param type $documentoIdentidad
     * @return boolean
     */
    private function existeRegistroAnterior($documentoIdentidad) {

        $conexion = $this->getConnection('FUNCDNIEXISTEENREGISTRO');

        try {
            $resultado = $conexion->getWrappedStatement()->FUNCDNIEXISTEENREGISTRO($documentoIdentidad);

            if ($resultado == 'S') {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $this->getLogger());
            return false;
        }
    }

    /**
     * getCodigoUsuario
     *
     * Comprueba si existe un registro anterior de este usuario.
     *
     * @param type $documentoIdentidad
     * @return string
     */
    private function getCodigoUsuario($documentoIdentidad) {

        $conexion = $this->getConnection('FUNCCODIGODEDNI');

        try {
            $sResultado = $conexion->getWrappedStatement()->FUNCCODIGODEDNI($documentoIdentidad);

            $this->getLogger()->addDebug('Respuesta metodo  [' . __CLASS__ . '>' . __FUNCTION__ . ':' . __LINE__ . ']',
                    array('parametros' => array($documentoIdentidad),
                        'resultado' => $sResultado,));

            return $sResultado;
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $this->getLogger());
            return '';
        }
    }

    /**
     * Obtiene un mensaje de error de la lista de literales
     * @param type $constante
     * @return type
     * @throws type
     */
    private function getMensaje($constante) {
        try {
            $RELiterales = new ARULiterales();

            return $RELiterales->getConstante($constante);
        } catch (Exception $ex) {
            throw Utilidades::controlarError($ex, $this->getLogger());
        }
    }

    /**
     * Refactorizacion se cogen todos los mensajes de la lista de literales
     *  se mantiene el getError por compatibilidad en codigo antiguo
     * @param type $constante
     * @return type
     * @throws type
     */
    public function getError($constante) {
        try {
            return $this->getMensaje($constante);
        } catch (Exception $ex) {
            throw Utilidades::controlarError($ex, $this->getLogger());
        }
    }

    /**
     * registraUsuario
     *
     * Registra un usuario en la BD
     *
     */
    public function registraUsuario($aDatosUsuario) {

        $sError = '';
        $sClaveUsuario = '';


        foreach ($aDatosUsuario as $valor) {
            if ($valor === FALSE) {
                throw Utilidades::controlarError($this->getError('RUE007'), $this->getLogger());
            }
        }

        try {
            /**
             * La clave del usuario viene por el PLSQL que se llama para dar de alta al usuario
             */
            $bResultado = $this->guardarUsuarioNuevo($aDatosUsuario, $sClaveUsuario, $sError);
            return $bResultado;
        } catch (\Exception $ex) {
            throw Utilidades::controlarError($ex, $this->getLogger());
        }
    }

    /**
     * Si se pasa un numero entonces e sque ha elegido de los correos posbiles
     *  que le ofrecemos por medio de "comprobarCorreosExistentes", entonces
     *  tenemos que calcular cual ha elegido.
     * 
     * @param type $sCorreoElectronico
     * @param type $sIdentidad
     * @param ARUBaseORM $ARUBaseORM
     * @return type
     * @throws type
     */
    public function calcularCorreoElectronicoValido($sCorreoElectronico, $sIdentidad) {
        $respuestaCorreoElectronico = '';

        if (is_numeric($sCorreoElectronico)) {
            $aCorreosUsuario = $this->comprobarCorreosExistentes($sIdentidad);
            if (is_array($aCorreosUsuario) && key_exists('amaila', $aCorreosUsuario[$sCorreoElectronico])) {
                $respuestaCorreoElectronico = $aCorreosUsuario[$sCorreoElectronico]['amaila'];
            } else {
                throw Utilidades::controlarError(ARULiterales::RUE004, $this->getLogger(),
                        'No se ha localizado el correo en "comprobarCorreosExistentes"',
                        array('correoUsuario' => $sCorreoElectronico,
                              'correosExistentes' => $aCorreosUsuario,
                              'identificador' => $sIdentidad));
            }
            return $respuestaCorreoElectronico;
        } else {
            return $sCorreoElectronico;
        }
    }

    /**
     * 
     * Comprueba los datos del usuario contra el PID:
     *  - Numero de soporte
     *  - Fecha de caducidad del DNI/NIE
     * 
     * @param string $numeroDocumento Numero del documento a comprobar
     * @param string $tipoDocumento Tipo de documento DNI|NIF|NIE
     * @param string $sNumeroSoporte Numero de soporte del documento
     * @param object $oFechaCaducidad Tipo DateTime, recuerda que se debe crear la fecha con hora 00:00:00
     * @return boolean
     * @throws \Exception
     */
    public function comprobarIdentidadPID($numeroDocumento, $tipoDocumento = '', $sNumeroSoporte = '', $oFechaCaducidad = '') {

        try {
            $this->getLogger()->addDebug('Entrando en metodo [' . __FUNCTION__ . ']');

            /**
             * Se ponen vacios porque el WS de la DGP no verifica que lo que se 
             *  pase case con lo que hay en sus BD
             * 
             */
            $sNombreCompleto = '';
            $sNombre = '';
            $sApellido1 = '';
            $sApellido2 = '';
            $sAnioNacimiento = '';

            //si viene vacio se intenta sacar el tipo de documento 
            if (empty($tipoDocumento)) {
                switch (Utilidades::calcularTipoDocumentoIdentificativo($numeroDocumento)) {

                    case 1:
                        $tipoDocumento = 'NIE';
                        break;
                    case 2:
                        $tipoDocumento = 'DNI';
                        break;

                    case 0:
                    default :
                        throw Utilidades::controlarError('No se puede determinar el tipo de documento [' . $numeroDocumento . ']');
                }
            }

            /**
             * FUNCPID_CONSULTADATOSIDENTIDAD llama a la Función FUNC_WSPID_SVDDGPCIWS02 
             */
            $conexion = $this->getConnection('FUNCPID_CONSULTADATOSIDENTIDAD', 'COMUN', 'PACKWSPID');

            //Respuesta es tipo string:
            //nombre|Apellido1|Apellido2|Fecha de nacimiento|Nombre padre|Nombre Madre| Fecha Caducidad documento| ID Solicitud | Sexo
            //@ejemplo:
            //RICARDO|GARCIA|GARCIA|03/11/1967|ANTONIO|MARIA CARMEN|12/01/2020|25987|M
            $sRespuesta = $conexion->getWrappedStatement()->FUNCPID_CONSULTADATOSIDENTIDAD(
                    $tipoDocumento,
                    Utilidades::comprobarDocumentoIdentificativo($numeroDocumento, true),
                    $sNombreCompleto,
                    $sNombre,
                    $sApellido1,
                    $sApellido2,
                    $sNumeroSoporte,
                    $sAnioNacimiento
            );

            $aRespuestaDatosIdentidad = explode('|', $sRespuesta);

            $this->getLogger()->addDebug('Consulta PID [FUNCPID_CONSULTADATOSIDENTIDAD]', array('respuesta' => $sRespuesta));

            //Si la fecha de caducidad no esta vacia se comprueba que casa con la proporcionada
            if (!(empty($oFechaCaducidad)) && $aRespuestaDatosIdentidad[0] !== ARULiterales::ABREVIATURA_NO) {

                //Se debe incluir la hora 00:00:00 para comprobar en el mismo tiempo las dos fechas
                $oFechaCaducidadBD = \DateTime::createFromFormat('dmY H:i:s', Utilidades::cambiarFormatoFecha($aRespuestaDatosIdentidad[6]) . ' 00:00:00');

                $this->getLogger()->addDebug('Comprobando fechas [FUNCPID_CONSULTADATOSIDENTIDAD]',
                        array('fechaCaducidad' => $oFechaCaducidad, 'fechaCaducidadBD' => $oFechaCaducidadBD,));

                if ($oFechaCaducidad != $oFechaCaducidadBD) {
                    $aRespuestaDatosIdentidad = [];
                    $aRespuestaDatosIdentidad[0] = 'N';
                    $aRespuestaDatosIdentidad[2] = '-1';
                    $aRespuestaDatosIdentidad[3] = 'La fecha de caducidad no coincide con la indicada.';
                }
            }

            return $aRespuestaDatosIdentidad;
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Al comprobar el PID para [FUNCPID_CONSULTADATOSIDENTIDAD].'
            );
        }
    }

    /**
     * Realiza la validación de los datos del registro de usuarios
     * 
     * @param type $request
     * @return boolean
     * @throws \Exception
     */
    public function validarDatosRegistroUsuarioPaso2($request, $sCorreoElectronico) {

        try {
            //Datos a comprobar
            $sTelefonoMovil = $request->get('telefonoMovil', FALSE);
            $sCorreoElectronico2 = $request->get('correoElectronico2', FALSE);

            if (!$sTelefonoMovil) {
                $sCodigoError = 'RUE015';
                return $sCodigoError;
            }

            // Si es numerico entendemos que es uno de los correos que le hemos 
            // dado a elegir y ya se ha calculado, por lo que saltamos a comprobar
            // que el correo2 sea el mismo
            if (is_numeric($request->get('correoElectronico1'))) {
                if ($sCorreoElectronico && filter_var($sCorreoElectronico, FILTER_VALIDATE_EMAIL)) {
                     return TRUE;
                        
                } else {
                    $sCodigoError = 'RUE013';
                    return $sCodigoError;
                }
                
            } else {
                
                if ($sCorreoElectronico && filter_var($sCorreoElectronico, FILTER_VALIDATE_EMAIL)) {
                    if ($sCorreoElectronico2 && (strcasecmp($sCorreoElectronico, $sCorreoElectronico2) == 0)) {
                        return TRUE;
                    } else {
                        $sCodigoError = 'RUE014';
                        return $sCodigoError;
                    }
                } else {
                    $sCodigoError = 'RUE013';
                    return $sCodigoError;
                }                
                
            }

            return TRUE;
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Al validar los datos del registro de usuarios Paso 2.'
            );
        }
    }

    /**
     * Realiza la validación de los datos del registro de usuarios
     * 
     * @param type $request
     * @return boolean
     * @throws \Exception
     */
    public function validarDatosRegistroUsuarioPaso3($request) {

        try {
            //Datos a comprobar
            $sCodigoVerificacion = $request->get('codigoVerificacion', FALSE);
            $sPassword1 = $request->get('password1', FALSE);
            $sPassword2 = $request->get('password2', FALSE);

            if (!$sCodigoVerificacion || !$sPassword1 || !$sPassword2) {
                $sCodigoError = 'RUE018';
                return $sCodigoError;
            }

            if (strcasecmp($sPassword1, $sPassword2) != 0) {
                $sCodigoError = 'RUE019';
                return $sCodigoError;
            }
            return TRUE;
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Al validar los datos del registro de usuarios Paso 2.'
            );
        }
    }

    /**
     * Realiza la validación del código Captcha
     * 
     * @param type $request
     * @return boolean
     * @throws \Exception
     */
    public function comprobarCaptchaRegistroUsuario($request) {

        try {
            //Datos a comprobar
            $sCodigoCaptchaUsuario = $request->get('imagenRobot', FALSE);
            $sCodigoCaptchaRegistro = $request->getSession()->get('verificacionCaptcha');

            //Se comprueba que el usuario haya introducido el código Captcha, que el código a verificar exista en sesión y que ambos sean correctos
            if ($sCodigoCaptchaUsuario === FALSE) {
                $sCodigoError = 'RUE016';
                return $sCodigoError;
            } elseif ($sCodigoCaptchaRegistro === FALSE) {
                $sCodigoError = 'RUE017';
                return $sCodigoError;
            } else {

                if (!( $sCodigoCaptchaUsuario === $sCodigoCaptchaRegistro )) {
                    $sCodigoError = 'RUE009';
                    return $sCodigoError;
                } else {
                    return TRUE;
                }
            }

            return TRUE;
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Al validar el Captcha en el paso 1 del registro de usuarios.'
            );
        }
    }

    /**
     * Realiza la validación del código Captcha
     * 
     * @param type $request
     * @param boolean $bBorrarDatosSesion Ponerlo a TRUE para borrar los datos de sesion $aDatosSesionRegistro
     * @return boolean|array
     * @throws \Exception
     */
    public function generarDatosSesionRegistroUsuario($request, $bBorrarDatosSesion = '') {

        try {

            if ($bBorrarDatosSesion === TRUE) {
                //Se borran los datos del usuario de la sesión.
                $request->getSession()->set('identidad', FALSE);
                $request->getSession()->set('soporteID', FALSE);
                $request->getSession()->set('fechaID', FALSE);
                $request->getSession()->set('nombre', FALSE);
                $request->getSession()->set('apellido1', FALSE);
                $request->getSession()->set('apellido2', FALSE);
                $request->getSession()->set('sexo', FALSE);
                $request->getSession()->set('fechaNacimiento', FALSE);
                $request->getSession()->set('telefonoMovil', FALSE);
                $request->getSession()->set('correoElectronico', FALSE);
                $request->getSession()->set('codigoVerificacion', FALSE);
                $request->getSession()->set('password', FALSE);
                $request->getSession()->set('ipCliente', FALSE);
                $request->getSession()->set('ValidadoPaso1', FALSE);
                $request->getSession()->set('ValidadoPaso2', FALSE);
            }

            //Se capturan los datos desde la sesión  
            $aDatosSesionRegistro = array(
                'identidad' => $request->getSession()->get('identidad', FALSE),
                'soporteID' => $request->getSession()->get('soporteID', FALSE),
                'fechaID' => $request->getSession()->get('fechaID', FALSE),
                'nombre' => $request->getSession()->get('nombre', FALSE),
                'apellido1' => $request->getSession()->get('apellido1', FALSE),
                'apellido2' => $request->getSession()->get('apellido2', FALSE),
                'nombreCompleto' => ucwords(strtolower($request->getSession()->get('nombre', FALSE) . " " . $request->getSession()->get('apellido1', FALSE) . " " . $request->getSession()->get('apellido2', FALSE))),
                'sexo' => $request->getSession()->get('sexo', FALSE),
                'fechaNacimiento' => $request->getSession()->get('fechaNacimiento', FALSE),
                'telefonoMovil' => $request->getSession()->get('telefonoMovil', FALSE),
                'correoElectronico' => $request->getSession()->get('correoElectronico', FALSE),
                'codigoVerificacion' => $request->getSession()->get('codigoVerificacion', FALSE),
                'password' => $request->getSession()->get('password', FALSE),
                'ipCliente' => $request->getClientIp()
            );

            return $aDatosSesionRegistro;
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Al generar el array de datos desde la sesión del registro de usuarios.'
            );
        }
    }
    
    /**
     * Funcion auxiliar usada para obtener los codigos de verificacion de los 
     *  usuarios sin tener que esperar por el correo.
     * 
     * @param type $sDocumentoIdentificativo
     * @return type
     * @throws \Exception
     */
    public function obtenerCodigoVerificacion($sDocumentoIdentificativo){
        
        try {
            
            //Si no estamos en desarrollo retornamos un numero aleatorio
            if (Utilidades::esDesarrollo() === FALSE){
                return random_int('123456789', '987654321');
            }
            
            $conexion = $this->getConnection('FUNCCODIGOVERIFICACION');

            $sResultado = $conexion->getWrappedStatement()->FUNCCODIGOVERIFICACION($sDocumentoIdentificativo);

            //Se logea el acceso.
            $this->getLogger()->addAlert('Se ha solicitado el código de veriicación de ['.$sDocumentoIdentificativo.'] directamente.', 
                    array(
                        'documento' => $sDocumentoIdentificativo,
                        'Resultado' => $sResultado,
                        '$SERVER'   => $_SERVER));
            
            
            return $sResultado;
            
            
        } catch (Exception $exc) {
            throw Utilidades::controlarError($exc, $this->getLogger(),
                    'Error al intentar localizar el codigo de verificación de ['.$sDocumentoIdentificativo.']');
        }
        
    }
                       
}
