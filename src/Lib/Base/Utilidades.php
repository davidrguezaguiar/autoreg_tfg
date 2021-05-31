<?php

/**
 * File: Utilidades.php
 * User: ULPGC
 * Email: desarrollo@ulpgc.es
 * Description: UTF-8
 */

namespace App\Lib\Base;

use App\Entity\Base\Tusuario;
use App\Service\Base\Suplantacion\SuplantadorIdentidadService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Monolog\Logger;
use DateTime;
use DateInterval;
use Swift_Message;
use Swift_Mailer;
use Swift_SmtpTransport;
use Swift_Attachment;
use Symfony\Component\DependencyInjection\Argument\ServiceLocator;

/**
 * Class Utilidades
 *
 */
class Utilidades {

    //const HOSTSMTP = '';
    const ULiteralNegativo = 'N';
    const ULiteralPositivo = 'S';
    const ULiteralFirmaDenegada = 'D';

    /**
     *
     * depurarSeguimiento
     *
     * Muestra por pantalla HORA:MINUTOS:SEGUNDOS:MICROSEGUNDOS para realizar una traza así como un contador que va aumentando cada vez que
     * se llama.
     *
     * @staticvar int $contadorSeguimiento
     * @param type    $IP
     * @param type    $texto
     * @param boolean $bExit
     */
    static function depurarSeguimiento($IP, $texto = '', $bExit = false) {

        static $contadorSeguimiento = 0;

        $contadorSeguimiento++;

        $mensaje = '';

        $dateTime = new \DateTime();

        $mensaje = $contadorSeguimiento . '.- ' . $dateTime->format('H:i:s:u') . ' -- ' . $texto;

        self::depurarIP($mensaje, $IP, $bExit = false, $request = null, $sTipoDeMensaje = 'warn', debug_backtrace());
    }

    static function depurarSeguimientoMensaje($texto = '') {
        static $contadorSeguimiento = 0;

        $contadorSeguimiento++;

        $mensaje = '';

        $dateTime = new \DateTime();

        $mensaje = $contadorSeguimiento . '.- ' . $dateTime->format('H:i:s:u') . ' -- ' . $texto;

        return $mensaje;
    }

    /**
     * INTERFACES DE LLAMADA A DEPURAR con getNormalized
     * Ejemplo de uso:
     *      Utilidades::depurarExit($oUsuario, $request);
     *
     * @param $var
     * @param $bExit
     *
     * @return mixed
     */
    static function depurarNormalize($var, $bExit = false) {
        self::depurarInterfaceado(Utilidades::getNormalized($var), $request = null, $sTipoMensaje = null, debug_backtrace(), $bExit);
    }

    /**
     * INTERFACES DE LLAMADA A DEPURAR con varios argumentos
     * Ejemplo de uso:
     *      Utilidades::depurarArgumentos($argumento1, ..., $argumentoN, 'ip', '206', 'exit');
     *
     *
     * Se pasa el numero de variables que se quieran mostrar por pantalla y saldrá un mensaje que lista los argumentos con numeros y cada
     *  valor de agurmento entre '-', ejemplo de salida:
     *
     * Argumento "1": -aaabbb- , Argumento "2": -pepepe-, Argumento "N": -Valor-
     *
     * !!!!!!ATENCION!!!!!
     * >IP
     * Si se quiere mostrar el mensaje para una sola ip se debe incluir la palabra 'ip' en cualquier parte pero el siguiente argumento debe
     *  ser la IP (XXX.XXX.XXX.XXX) que se quiera usar para mostrar el mensaje
     *
     * >Exit
     * Si se quiere usar un exit en el se debeb escribir en cualquier argumento 'exit'
     *
     * @param mixed el numero de argumentos
     *
     * @return mixed
     */
    static function depuraArgumentos() {

        $bIP = false;
        $bEXIT = false;
        $numeroIP = '';
        $mensajeConArgumentos = '';

        $totalArgumentos = func_num_args();

        for ($contadorArgumentos = 0; $totalArgumentos > $contadorArgumentos; $contadorArgumentos++) {

            if (is_string(func_get_arg($contadorArgumentos)) && strtolower(func_get_arg($contadorArgumentos)) === 'ip') {
                $bIP = true;
                $contadorArgumentos++;
                $numeroIP = func_get_arg($contadorArgumentos);
            } elseif (is_string(func_get_arg($contadorArgumentos)) && strtolower(func_get_arg($contadorArgumentos)) === 'exit') {
                $bEXIT = true;
            } else {
                if (is_array(func_get_arg($contadorArgumentos))) {
                    $mensajeConArgumentos .= 'Argumento "' . $contadorArgumentos . '":-' . print_r(func_get_arg($contadorArgumentos), true) . '-';
                } else {
                    $mensajeConArgumentos .= 'Argumento "' . $contadorArgumentos . '":-' . func_get_arg($contadorArgumentos) . '-';
                }
            }
        }

        if ($bIP) {

            self::depurarIP($mensajeConArgumentos, $numeroIP, $bEXIT, null, 'warn', debug_backtrace());
        } elseif ($bEXIT) {
            self::depurarExit($mensajeConArgumentos, debug_backtrace());
        } else {
            self::depurar($mensajeConArgumentos);
        }
    }

    /**
     *
     * depurarAFichero
     *
     * saca la depuracion de una variable y la escribe en un fichero temporal dentro de sys_get_temp_dir con la palabra 'Depuracion_$IP_'
     *  se borran siempre los ficheros generados con anterioridad, sino quieres que pase eso se debe poner $bBorrarAnteriores = FALSE
     *
     * @param mixed   $mensaje           Mensaje a depurar
     * @param string  $IP                Ip del depurador, si se especifica 'respuestaExterna' se valida automaticamente la IP y el fichero
     *                                   sera Depuracion_respuestaExterna_
     * @param boolean $bBorrarAnteriores Indica si borra los ficheros de depuracion anteriores, si es respuestaExterna_ por defecto NO SE
     *                                   BORRAN
     * @param boolean $bExit             Si salir al ejecutar comando
     * @param type    $request
     * @param string  $sTipoDeMensaje
     * @param boolean $debugBackTrace
     */
    static function depurarAFichero(
            $mensaje,
            $IP,
            $bBorrarAnteriores = true,
            $bExit = false,
            $request = null,
            $sTipoDeMensaje = 'warn',
            $debugBackTrace = false
    ) {

        $bExterna = false;
        if ($IP == 'respuestaExterna') {
            $bExterna = true;
        }
        if (Utilidades::depurarEsIP($IP) || $bExterna) {

            if ($bBorrarAnteriores && $bExterna === false) {
                $comandoBorrado = 'rm -rf ' . sys_get_temp_dir() . '/Depuracion_' . $IP . '_*';

                @exec($comandoBorrado);
                if ($bExterna === false) {
                    Utilidades::depurarIP('Borrados los ficheros de depuracion anteriores.', $IP);
                }
            }

            $ficheroDepuracion = tempnam(sys_get_temp_dir(), 'Depuracion_' . $IP . '_');

            file_put_contents($ficheroDepuracion, print_r($mensaje, true));

            if ($bExterna === false) {
                Utilidades::depurarIP('Fichero creado de depuracion en: "' . $ficheroDepuracion . '"',
                        $IP,
                        $bExit = false,
                        $request = null,
                        $sTipoDeMensaje = 'warn',
                        $debugBackTrace = false);
            }
        }
    }

    /**
     * INTERFACES DE LLAMADA A DEPURAR con EXIT
     * Ejemplo de uso:
     *      depurarExit($oUsuario, $request);
     *
     * @param $var
     *
     * @return mixed
     */
    static function depurarExit($var, $debugBackTrace = false) {
        if ($debugBackTrace === false) {
            self::depurarInterfaceado($var, $request = null, $sTipoMensaje = null, debug_backtrace(), $bExit = true);
        } else {
            self::depurarInterfaceado($var, $request = null, $sTipoMensaje = null, $debugBackTrace, $bExit = true);
        }
    }

    /**
     * depurarIP
     *
     * Muestra un mensaje por pantalla solo para la IP que se le pase por parametro.
     *
     * @param $mensaje
     * @param $IP
     * @param $bExit
     * @param $request
     * @param $sTipoDeMensaje
     *
     * @return mixed
     */
    static function depurarIP($mensaje, $IP, $bExit = false, $request = null, $sTipoDeMensaje = 'warn', $debugBackTrace = false) {
        //Si existe el parametro $IP y no es nulo se comprueba si coninciden las ip, sino coincide
        // sale sin mostrar el mensaje

        if (Utilidades::depurarEsIP(($IP))) {
            echo "Mostrando para IP: " . $IP . "<br>";
            if ($debugBackTrace === false) {
                self::depurarInterfaceado($mensaje, $request, $sTipoDeMensaje, debug_backtrace(), $bExit);
            } else {
                self::depurarInterfaceado($mensaje, $request, $sTipoDeMensaje, $debugBackTrace, $bExit);
            }
        }
    }

    /**
     * depurarUsuario
     *
     * Muestra un mensaje por pantalla solo para el usuario seleccionado, este usuario
     *  se carga en parametros de Symfony com un array en yaml, ejemplo(respetar las tabulaciones):
     * parameters:
     * depuracion:
     *
     * @param $mensaje
     * @param $IP
     * @param $bExit
     * @param $request
     * @param $sTipoDeMensaje
     *
     * @return mixed
     */
    static function depurarUsuario($mensaje, $usuario, $bExit = false, $request = null, $sTipoDeMensaje = 'warn', $debugBackTrace = false) {
        //Si existe el parametro $IP y no es nulo se comprueba si coninciden las ip, sino coincide
        // sale sin mostrar el mensaje
        $IP = null;
        if (defined('depuracionIP')) {

            if (array_key_exists($usuario, depuracionIP)) {
                $IP = depuracionIP[$usuario];
            }

            if (Utilidades::depurarEsIP(($IP))) {
                echo "Mostrando para IP: " . $IP . "<br>";
                if ($debugBackTrace === false) {
                    self::depurarInterfaceado($mensaje, $request, $sTipoDeMensaje, debug_backtrace(), $bExit);
                } else {
                    self::depurarInterfaceado($mensaje, $request, $sTipoDeMensaje, $debugBackTrace, $bExit);
                }
            }
        }
    }

    // -------------------------------------------------------------------------------------------------------------
    //
    // 								FUNCION PARA DEVOLVER LA FECHA ACTUAL EN CASTELLANO
    //
    // -------------------------------------------------------------------------------------------------------------

    /**
     * @param $var
     * @param $bExit
     * @param $request
     * @param $sTipoMensaje
     * @param $aTrazaScript
     *
     * @return mixed
     */
    static function depurarInterfaceado($var, $request = '', $sTipoMensaje = 'debug', $aTrazaScript = '', $bExit = FALSE) {
        $mensajeAsterisco = "";
        /**
         * @author <david.rodriguez@ulpgc.es>
         * @since Modificacion para que se vea el depurar encima del fixed de la cabecera.
         */
        $mensajeCabecera = '<div style="z-index: 9999;clear: both;display: block;top: 0;left: 0;background: aliceblue;/*! border: 10px red; */padding: 1em;">';
        if (is_object($request) && $bExit === FALSE) {

            $request->getSession()->getFlashBag()->add(
                    'advertencia',
                    "Llamada a depurar en: " . $aTrazaScript[0]['file'] . ':' . $aTrazaScript[0]['line']
            );

            $request->getSession()->getFlashBag()->add(
                    $sTipoMensaje,
                    print_r($var, TRUE)
            );
        } else {
            if ($bExit === TRUE) {
                $mensajeExit = '<br><br>Forzado el exit por una llamada de depuraci&oacute;n en:<br> <strong>'
                        . $aTrazaScript[0]['file'] . ':' . $aTrazaScript[0]['line'] . '</strong><br><br>';
                $mensajeAsterisco = str_repeat('*', 100);
                $mensajeAsterisco .= '<h1 style="color:red; margin-left:1em;"> ADVERTENCIA </h1>';
                $mensajeAsterisco .= str_repeat('*', 100);
                $mensajeCabecera .= $mensajeAsterisco . "" . $mensajeExit . "" . $mensajeAsterisco . "<br><br><pre>";
            } else {
                $mensajeExit = '<br><br>Llamada de depuraci&oacute;n en:<br> <strong>' . $aTrazaScript[0]['file'] . ':'
                        . $aTrazaScript[0]['line'] . '</strong><br><br>';
                $mensajeCabecera .= $mensajeExit . '<pre>';
            }
            echo $mensajeCabecera;
            if (is_object($var)) {
                print_r(\Doctrine\Common\Util\Debug::export($var, 5));
            } elseif (is_bool($var)) {
                var_dump($var);
            } else {
                print_r($var);
            }
            echo "</pre></div>";
        }

        if ($bExit) {

            exit($mensajeAsterisco . '<br><br>Forzado el exit por una llamada de depuraci&oacute;n en:<br> <strong>'
                    . $aTrazaScript[0]['file'] . ':' . $aTrazaScript[0]['line'] . '</strong><br><br>'
                    . $mensajeAsterisco);
        }
    }

    /**
     *
     *  Crea una exception y controla si estamos en desrrollo incluye la funcion y la linea donde se produjo el error
     *  Comprueba primero si es un error oracle si lo es sale formateado sino sale el mensaje tal cual.
     *
     *
     * @param string|\Exception $mensajeError     Mensaje del error en formato string o una \Exception
     * @param Logger            $Logger
     * @param string            $mensajeLogger Si se deja vacio se cogera el mensaje de $exception
     * @param array             $arrayLogger   Array con los parametros que se quieran incluir, se incluye siempre por defecto la
     *                                            Exception.
     * @return \Exception
     */
    public static function controlarError($exception, Logger $Logger = null, $mensajeLogger = '', $arrayLogger = []) {

        $aTrazaScript = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $codigoExcepcion = '99999';

        if ($exception instanceof \Exception) {
            $codigoExcepcion = $exception->getCode();
            //Para la 223 no se muestra y solo en la red
            if (Utilidades::esDesarrollo() && !Utilidades::depurarEsIP(223) && Utilidades::depurarESULPGC()) {
                $aTrazaExcepcion = $exception->getTrace();
                $line = '';
                if (isset($aTrazaExcepcion[1]['line'])) {
                    $line = $aTrazaExcepcion[1]['line'];
                }
                $mensajeError = $aTrazaExcepcion[2]['function'] . ':' . $line . '> ' . $exception->getMessage();
            } else {
                $mensajeError = $exception->getMessage();
            }
        } else {
            $mensajeError = $exception;
        }

        /**
         * El 'preg_replace' elimina la parte de ' in / .... ' del mensaje para que no salga el ruta del fichero PHP
         *
         * Ejemplo:
         *      $mensaje = 'Error ORA-20907: ORA-20600: No se ha encontrado la solicitud o no esta enviada in /var/www/html/php7/symfony4/GastosMenores/vendor/doctrine/dbal/lib/Doctrine/DBAL/Driver/OCI8/OCI8StatementUlpgc.php:760'
         *
         *      Retorna => 'Error ORA-20907: ORA-20600: No se ha encontrado la solicitud o no esta enviada';
         */
        $sMensajeError = preg_replace('/\sin\s\/.*OCI8.*:\d+$/', '', Utilidades::comprobarErrorOracle($mensajeError));

        if (Utilidades::esDesarrollo() && !Utilidades::depurarEsIP(223) && Utilidades::depurarESTIC()) {

            $sMensajeError = $aTrazaScript[1]['function'] . ':' . $aTrazaScript[0]['line'] . '> ' . $sMensajeError;

            /**
             *
             * Se elimina el 'handleRaw' dentro de la traza de error ya que es del Kernel del Symfony y no es util para depurar.
             *
             * Ejemplo:
             *  $mensaje = crearSolicitudAction:234> handleRaw:149> obtenerOrganosDeContratacion:398>  Notice: Undefined variable: aOrganismosContratacion
             *
             *  Retorna => crearSolicitudAction:234> obtenerOrganosDeContratacion:398> Notice: Undefined variable: aOrganismosContratacion
             */
            $sMensajeError = preg_replace('/\shandleRaw\:\d+>/', '', $sMensajeError);
        }

        //Se usa el log de Symfony para guardar los mensajes
        if ($Logger instanceof Logger) {

            $arrayLogger['exception'] = $exception;

            if ($mensajeLogger) {
                $Logger->err($mensajeLogger, $arrayLogger);
            } else {
                $Logger->err($sMensajeError, $arrayLogger);
            }
        }

        return new \Exception($sMensajeError, $codigoExcepcion);
    }

    /**
     * Elimina las cadenas ORA-Codigo que tenga el mensaje error
     *
     * @param $e
     * @return mixed
     */
    public static function formatearError($e) {
        $sMensajeError = self::comprobarErrorOracle($e);
        $sMensajeError = preg_replace('/(in\s*\/var\/.*php.*)/', '', $sMensajeError);
        $sMensajeError = preg_replace('/(.*ORA-\d+:(.*))/', '\2', $sMensajeError);

        return $sMensajeError;
    }

    /**
     *
     * @param \Exception $e
     * @param bool       $bCodigoError      Indica si se devuelve un array con el codigo de error y el mensaje
     * @param bool       $bMensajeCompleto
     * @param bool       $bMensajeComentado Devuelve un array con un 'mensajeUsuario' y un 'mensajeComentado' este ultimo para usar entre
     *                                      <!-- -->
     *
     * @return mixed
     */
    public static function comprobarErrorOracle($e, $bCodigoError = false, $bMensajeCompleto = false, $bMensajeComentado = false) {

        if ($e instanceof \Exception) {
            $mensajeError = $e->getMessage();
        } else {
            $mensajeError = $e;
        }

        if (preg_match('/ORA-\d+/im', $mensajeError, $aMatches)) {

            switch ($aMatches[0]) {

                //Restricción de clave primaria
                case 'ORA-00001':
                    $mensajeReturn = 'Ya existe un elemento con los mismos datos.';
                    break;

                //Error de clave extranjera
                case 'ORA-02292':
                    $mensajeReturn = 'Existen datos dependientes de este elemento.';
                    break;
                default :
                    if ($bMensajeCompleto) {
                        $mensajeReturn = 'Error ' . $aMatches[0] . ': ' . $mensajeError;
                    } elseif ($bMensajeComentado) {

                        preg_match('/(ORA-\d+:(.*)$)/im', $mensajeError, $aMensajeUsuario);

                        preg_match_all('/(ORA-\d+:.*$)/im', $mensajeError, $aCodigoMensaje);

                        $mensajeComentado = '';

                        foreach ($aCodigoMensaje[0] as $valor) {
                            $mensajeComentado .= $valor . ' ';
                        }

                        return array('mensajeUsuario' => $aMensajeUsuario[2], 'mensajeComentado' => $mensajeComentado);
                    } else {
                        preg_match('/(ORA-\d+:.*$)/im', $mensajeError, $aCodigoMensaje);
                        $mensajeReturn = 'Error ' . $aCodigoMensaje[0];
                    }
            }


            if ($bCodigoError) {
                return array('mensaje' => $mensajeReturn, 'codigoError' => $aMatches[0]);
            } else {
                return $mensajeReturn;
            }
        } else {
            return $mensajeError;
        }
    }

    /**
     * Interface de depurarEsIP, funcion amigable
     *
     * @param string $sUsuarioDepuracion
     * @return boolean
     */
    public static function depurarEsUsuario($sUsuarioDepuracion) {
        return self::depurarEsIP($sUsuarioDepuracion);
    }

    /**
     * devuelve TRUE|FALSE si la ip pasada por parametro coincide con la ip del cliente.
     *
     * @param string $IP IP a comprobar
     *
     * @return boolean
     */
    public static function depurarEsIP($IP) {

        if (isset($IP)) {

            //Si la ip no es valida se comprueba si incluyendo la subred lo es.
            if (!(filter_var($IP, FILTER_VALIDATE_IP))) {

                $IP_AUX = $IP;

                $IP = '' . $IP;

                //En caso de que aun asi no sea valida se sale sin mostrar el mensaje.
                if (!(filter_var($IP, FILTER_VALIDATE_IP))) {

                    //Comprobamos si existe depuracion IP 
                    if (defined('depuracionIP')) {
                        //Depuracion IP va por nombre de usuario
                        if (array_key_exists($IP_AUX, depuracionIP)) {
                            $IP = depuracionIP[$IP_AUX];
                        }

                        if (!(filter_var($IP, FILTER_VALIDATE_IP))) {
                            return false;
                        }
                    } else {

                        return false;
                    }
                }
            }

            if (!preg_match('/^' . $IP . '/', $_SERVER['REMOTE_ADDR'])) {
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Comprueba que la IP pertenece al rango de ULPGC o a los usuarios externos
     * habilitados por parameters:
     * parameters:
     * depuracion:
     * 'lh': 127.0.0.1
     *
     * @return boolean
     */
    public static function depurarESTIC() {

        $IP = '';

        if (defined('depuracionIP')) {

            if (is_array(depuracionIP)) {
                foreach (depuracionIP as $usuario => $IP) {
                    if ($usuario === 'todos') {
                        return true;
                    }
                    if (preg_match('/^' . $IP . '/', $_SERVER['REMOTE_ADDR'])) {
                        return true;
                    }
                }
            }
        } elseif (preg_match('/^' . $IP . '/', $_SERVER['REMOTE_ADDR'])) {
            return true;
        }

        return false;
    }

    /**
     * Devuelve si se esta ejecutando el script en un servidor de ULPGC, solo
     *  en desarrollo
     *
     * @return boolean
     */
    public static function depurarESServidor() {
        if (self::esDesarrollo()) {
            if (defined('depuracionIP') && isset(depuracionIP['servidor'])) {
                if ($_SERVER['SERVER_ADDR'] === depuracionIP['servidor']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $texto
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function respuestaOK($texto = '') {

        return new \Symfony\Component\HttpFoundation\Response($texto, \Symfony\Component\HttpFoundation\Response::HTTP_OK);
    }

    // -------------------------------------------------------------------------------------------------------------

    /**
     * @param string $texto
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function respuestaAuthRequired($texto = '') {
        return new \Symfony\Component\HttpFoundation\Response($texto, \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
    }

    // -------------------------------------------------------------------------------------------------------------

    /**
     * @param string $texto
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function respuestaNoAutenticado($texto = '') {
        return new \Symfony\Component\HttpFoundation\Response($texto, \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------------------------------------------

    /**
     * @param string|\Exception $texto
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function respuestaError($texto = '') {
        if ($texto instanceof \Exception) {
            return new \Symfony\Component\HttpFoundation\Response($texto->getMessage(), \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        } else {
            return new \Symfony\Component\HttpFoundation\Response($texto, \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * INTERFACES DE LLAMADA A DEPURAR con mensajes SYMFONY
     * Ejemplo de uso:
     *      Utilidades::depurar($oUsuario, $request);
     *
     * @param $var
     * @param $request
     * @param $sTipoMensaje
     * @param $debugBackTrace si es llamado desde otro depurar hay que pasarle el debug para saber cual fue el script original de la
     *                        llamada.
     *
     * @return mixed
     */
    public static function depurar($var, $request = '', $sTipoMensaje = 'debug', $debugBackTrace = false) {
        if ($debugBackTrace === false) {
            self::depurarInterfaceado($var, $request, $sTipoMensaje, debug_backtrace());
        } else {
            self::depurarInterfaceado($var, $request, $sTipoMensaje, $debugBackTrace);
        }
    }

    /**
     * Devuelve el objeto facilitado por parametro como un array
     * Util para objetos anidados de gran tamaño (Doctrine)
     *
     * @param object $object
     *
     * @return mixed
     */
    public static function getNormalized($object) {
        if (is_object($object)) {
            $aEncoders = array(new XmlEncoder(), new JsonEncoder());
            $aNormalizers = array(new ObjectNormalizer());
            $oSerializer = new Serializer($aNormalizers, $aEncoders);

            $aNormalizers[0]->setCircularReferenceLimit(1);

            $aNormalizers[0]->setCircularReferenceHandler(function ($object) {
                return null;
            });

            return $oSerializer->normalize($object);
        } else {
            $aResultado = array();
            if (is_array($object)) {

                if (count($object) > 0) {
                    foreach ($object as $row) {
                        $aResultado[] = self::getNormalized($row);
                    }
                } else {
                    $aResultado[] = array();
                }
            }

            return $aResultado;
        }
    }

    /**
     * Devuelve el objeto facilitado por parametro como un array
     * Util para objetos anidados de gran tamaño (Doctrine)
     *
     * @param object $object
     * @param string $type [json,xml]
     *
     * @return mixed
     */
    public static function getSerialized($object, $type = 'json') {
        $aEncoders = array(new XmlEncoder(), new JsonEncoder());
        $aNormalizers = array(new ObjectNormalizer());
        $oSerializer = new Serializer($aNormalizers, $aEncoders);

        $aNormalizers[0]->setCircularReferenceLimit(1);

        $aNormalizers[0]->setCircularReferenceHandler(function ($object) {
            return null;
        });

        return $oSerializer->serialize($object, $type);
    }

    /**
     * @param Request $request
     * @return null|string
     */
    public static function getDNIUsuarioConectado(Request $request) {
        $sesion = $request->getSession();
        /**
         * @var \App\Entity\Base\Tusuario $oUsuario
         */
        $oUsuario = $sesion->get('userObject');
        if ($oUsuario) {
            return $oUsuario->getDni();
        } else {
            return null;
        }
    }

    /**
     * @param Request $request
     * @return null|string
     */
    public static function getNIFUsuarioConectado(Request $request) {
        $sesion = $request->getSession();
        /**
         * @var \App\Entity\Base\Tusuario $oUsuario
         */
        $oUsuario = $sesion->get('userObject');
        if ($oUsuario) {
            return $oUsuario->getDni() . $oUsuario->getletraDni();
        } else {
            return null;
        }
    }

    /**
     * @param Request $request
     * @return null|string
     */
    public static function getUsuarioConectado(Request $request) {
        $sesion = $request->getSession();

        return $sesion->get('userObject');
    }

    /**
     * @param Request $request
     * @return null|string
     */
    public static function getUsuarioSuplantado(Request $request) {
        $sesion = $request->getSession();
        /**
         * @var \App\Entity\Base\Tusuario $oUsuario
         */
        $oUsuarioSuplantado = $sesion->get('informacion_identidad_suplantada');

        $oUsuario = new Tusuario();

        $oUsuario->setDni($oUsuarioSuplantado->getDni());
        $oUsuario->setNombre($oUsuarioSuplantado->getNombreCompleto());
        $oUsuario->setRoles($oUsuarioSuplantado->getRolesSuplantados());
        $oUsuario->setCentros($oUsuarioSuplantado->getCentrosSuplantados());

        return $oUsuario;
    }

    /**
     * Si se esta suplantando identidad el dni a devolver sera el del usuario suplantado, sino se devuelve NULL
     *
     * @param SuplantadorIdentidadService $oSuplantadorService
     *
     * @return string|null
     */
    public static function getDNIUsuarioSuplantado(SuplantadorIdentidadService $oSuplantadorService) {
        return $oSuplantadorService->obtenerDNIUsuarioSuplantado();
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
     * @param SuplantadorIdentidadService $oSuplantadorService
     *
     * @return bool
     */
    public static function esIdentidadSuplantada(SuplantadorIdentidadService $oSuplantadorService) {
        return $oSuplantadorService->esIdentidadSuplantada();
    }

    /**
     * Comprueba si la suplantacion de identidad esta habilitada
     */
    public static function esSuplantacionIdentidadHabilitada(Container $oContenedor) {
        return empty($oContenedor->getParameter('nombre_app_suplantada'));
    }

    /**
     * @param      $asunto
     * @param      $from
     * @param      $to
     * @param      $plantilla
     * @param null $aVariable
     * @return bool
     */
    public static function enviarMail($from, $to, $asunto, $body, $rutafichero = null, $sNombreFichero = null) {

        $host = self::HOSTSMTP;

        $transporte = new Swift_SmtpTransport($host);

        $mail = new Swift_Mailer($transporte);

        $mensaje = new Swift_Message($asunto, $body, 'text/html');

        $mensaje->setFrom(array($from));

        $mensaje->setTo(array($to));

        //si se va a enviar un fichero
        if ($rutafichero) {
            $agregarFichero = new Swift_Attachment();

            //si viene el nombre del fichero, se cambia
            if ($sNombreFichero) {
                $ficheroAgregado = $agregarFichero->fromPath($rutafichero)->setFilename($sNombreFichero);
            } else {
                $ficheroAgregado = $agregarFichero->fromPath($rutafichero);
            }

            $mensaje->attach($ficheroAgregado);
        }

        $resultado = $mail->send($mensaje);

        return $resultado;
    }

    /**
     * *
     * @return string [05 de Junio de 2020]
     */
    public static function obtenerFechaActual() {
        $nMes = date('n');
        $sMes = Utilidades::obtenerMes($nMes);

        $sFecha = date('\a j \d\e \%\s \d\e Y');

        return sprintf($sFecha, $sMes);
    }

    /**
     * @param $nMes
     *
     * @return string
     */
    public static function obtenerMes($nMes) {
        $sMes = "";
        switch ($nMes) {
            case '1':
                $sMes = "Enero";
                break;
            case '2':
                $sMes = "Febrero";
                break;
            case '3':
                $sMes = "Marzo";
                break;
            case '4':
                $sMes = "Abril";
                break;
            case '5':
                $sMes = "Mayo";
                break;
            case '6':
                $sMes = "Junio";
                break;
            case '7':
                $sMes = "Julio";
                break;
            case '8':
                $sMes = "Agosto";
                break;
            case '9':
                $sMes = "Septiembre";
                break;
            case '10':
                $sMes = "Octubre";
                break;
            case '11':
                $sMes = "Noviembre";
                break;
            case '12':
                $sMes = "Diciembre";
                break;
        }

        return $sMes;
    }

    /**
     * esDesarrollo
     *
     * La funcion devuelve si se esta o no en desarrollo.
     *
     * @return FALSE
     */
    public static function esDesarrollo() {
        if ($_SERVER['APP_ENV'] === 'dev') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $sInformacion
     */
    public static function mostrarInformacion($sInformacion) {
        $session = new Session();

        $session->getFlashBag()->add('informacion', $sInformacion);
    }

    /**
     * @param $sAdvertencia
     */
    public static function mostrarAdvertencia($sAdvertencia) {
        $session = new Session();

        $session->getFlashBag()->add('advertencia', $sAdvertencia);
    }

    /**
     * @param $sError
     */
    public static function mostrarError($sError) {


        $session = new Session();

        $session->getFlashBag()->add('error', $sError);
    }

    /**
     *
     * Une diferentes PDFs en uno solo, debe existir el gs32 y gs64 en el directorio 'bin_app' definido en services.yaml
     *
     * @param Container $oContenedor
     * @param type      $arrayPDFUnir
     * @param type      $nombrePDFfinal
     * @return boolean
     * @throws \Exception
     */
    public static function unirPDF($oContenedor, $arrayPDFUnir, $nombrePDFfinal = null) {

        $ubicacionBinarioGS = $oContenedor->getParameter('bin_app');
        $listaPDFUnir = '';

        //comprobar si el sistema es de 32bits o 64bits
        if (strlen(decbin(~0)) == 32) {
            $ficheroGS = 'gs32';
        } else {
            $ficheroGS = 'gs64';
        }

        $comandoGS = $ubicacionBinarioGS . '/' . $ficheroGS . ' -dNOPAUSE -sDEVICE=pdfwrite -sOUTPUTFILE=%s -dBATCH %s';


        if (is_array($arrayPDFUnir)) {
            foreach ($arrayPDFUnir as $ficheroPDF) {
                $listaPDFUnir .= '"' . $ficheroPDF . '" ';
            }
            //$listaPDFUnir = implode(' ', $arrayPDFUnir).'"';
        } elseif (is_string($arrayPDFUnir)) {
            $listaPDFUnir = '"' . $arrayPDFUnir . '"';
        }

        if (is_null($nombrePDFfinal)) {
            $nombrePDFfinal = tempnam(sys_get_temp_dir(), 'unionPDF');
        }

        $comandoGSEjecutar = sprintf($comandoGS, $nombrePDFfinal, $listaPDFUnir);

        $ultimaLinea = exec($comandoGSEjecutar, $salidaPantalla, $retornoEjecucion);

        if ($retornoEjecucion != 0) {
            if (Utilidades::esDesarrollo()) {
                throw new \Exception('Error al realizar la unión de PDF.--' . $ultimaLinea . '---<pre>' . print_r($salidaPantalla,
                                true) . '</pre>---' . $retornoEjecucion . '<br>Comando:' . $comandoGSEjecutar);
            } else {
                throw new \Exception('Error al realizar la unión de PDF.');
            }

            return false;
        }

        return $nombrePDFfinal;
    }

    /**
     * Se crea una marca de agua en el PDF, sino se especifica nada
     *  se pondra la palabra "BORRADOR", para cambiar la palabra hay que cambiar
     *  el fichero marcaAguaPDF (es un .ps)
     *
     * @param Container $oContenedor
     * @param string    $nombrePDFOrigen Ruta PATH hasta el fichero con el nombre del fichero incluido ejemplo /tmp/php7/ACTAS/pedrito.pdf
     * @param string    $nombrePDFfinal  Nombre opcional del fichero resultante SIEMPRE se crearan en el directorio temporal
     * @return string Ruta completa del fichero generado con la marca de agua (incluye nombre del fichero)
     */
    public static function crearMarcaAguaPDF($oContenedor, $nombrePDFOrigen, $nombrePDFfinal = null) {

        $ubicacionBinarioGS = $oContenedor->getParameter('bin_app');

        //Comprobamos si existe el parametro con el nombre del fichero de marca de agua
        if ($oContenedor->hasParameter('marcaAguaPDF')) {

            $ficheroMarcaAgua = $ubicacionBinarioGS . DIRECTORY_SEPARATOR . $oContenedor->getParameter('marcaAguaPDF');
        } else {
            $ficheroMarcaAgua = $ubicacionBinarioGS . DIRECTORY_SEPARATOR . 'marcaAgua.ps';
        }

        if (file_exists($ficheroMarcaAgua) === false) {
            throw self::controlarError('No exite el PS para la marca de agua. [' . $ficheroMarcaAgua . ']');
        }
        //creamos la ruta real al fichero
        $nombrePDFOrigenPATH = realpath($nombrePDFOrigen);

        if (file_exists($nombrePDFOrigenPATH) === false) {
            throw self::controlarError('No exite el pdf de origen [' . $nombrePDFOrigenPATH . ']');
        }

        //Existe el parametro ruta temporal 
        if ($oContenedor->hasParameter('ruta_temporal')) {
            //Debe tener permisos de lectura sino se cogera (sys_get_temp_dir) lo hace tempnam automaticamente
            $directorioTemporal = $oContenedor->getParameter('ruta_temporal');
        } else {

            //Sino existe se coge el de por defecto del sistema
            $directorioTemporal = sys_get_temp_dir();
        }

        //Si el nombre del fichero es nulo creamos uno nuestro
        if (is_null($nombrePDFfinal)) {
            $nombrePDFfinalDIRTEMP = tempnam($directorioTemporal, 'marcaDeAguaPDF');
        } else {
            file_put_contents($directorioTemporal . DIRECTORY_SEPARATOR . $nombrePDFfinal, '');
            $nombrePDFfinalDIRTEMP = realpath($directorioTemporal . DIRECTORY_SEPARATOR . $nombrePDFfinal);
        }

        //comprobar si el sistema es de 32bits o 64bits
        if (strlen(decbin(~0)) == 32) {
            $ficheroGS = 'gs32';
        } else {
            $ficheroGS = 'gs64';
        }

        /**
         *   Ejemplo del comando correcto:
         *      /var/www/html/php7/symfony4/ARU/bin/gs64 -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=watermarked.pdf marcaAgua.ps Informe_Experto_TP20FP012_42847639.pdf
         */
        $comandoGS = $ubicacionBinarioGS . '/' . $ficheroGS . ' -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOUTPUTFILE=%s -dBATCH %s %s';

        $comandoGSEjecutar = sprintf($comandoGS, $nombrePDFfinalDIRTEMP, $ficheroMarcaAgua, $nombrePDFOrigenPATH);

        $ultimaLinea = exec($comandoGSEjecutar, $salidaPantalla, $retornoEjecucion);

        if ($retornoEjecucion != 0) {
            if (Utilidades::esDesarrollo()) {
                throw new \Exception('Error al realizar la unión de PDF.--' . $ultimaLinea . '---<pre>' . print_r($salidaPantalla,
                                true) . '</pre>---' . $retornoEjecucion . '<br>Comando:' . $comandoGSEjecutar);
            } else {
                throw new \Exception('Error al realizar la unión de PDF.');
            }

            return false;
        }

        return $nombrePDFfinalDIRTEMP;
    }

    /**
     * @param ContainerInterface $oContenedor
     * @return \SoapClient
     */
    static function conectarFirmaElectronicaULPGC($oContenedor, $bDepuracion = false) {

        //Se usa para produccion ya que redirige a 8443 con certificado no valido.
        $sslContext = array();

        $hostFirma = $oContenedor->getParameter('hostWSFirma');

        $directorioCertificado = $oContenedor->getParameter('directorioCertificado');

        $contextOptions = array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'verify_depth' => 5,
                'CN_match' => $oContenedor->getParameter('hostFirmadoWSFirma'),
                'disable_compression' => true,
                'SNI_enabled' => true,
                'ciphers' => $oContenedor->getParameter('ciphersWSFirma'),
            ),
        );
        //Con esto permitimos o no la comprobar el certificado con el que se ha firmado  la url de conexion, usando el certificado
        //  que tenemos almacenado nosotros
        if ($oContenedor->getParameter('usarCAFirma')) {
            $contextOptions['ssl']['cafile'] = realpath($directorioCertificado) . DIRECTORY_SEPARATOR . $oContenedor->getParameter('autoridadCertificadoraWSFirma');
        }


        $sslContext = stream_context_create($contextOptions);

        $opcionesSOAP = array(
            'cache_wsdl' => 0,
            'trace' => true,
            'soap_version' => SOAP_1_1,
            'exceptions' => true,
            'typemap' => array(
                array(
                    'type_ns' => 'http://www.w3.org/2003/05/soap-envelope',
                    'type_name' => 'soap',
                ),
            ),
            'stream_context' => $sslContext,
        );

        if ($bDepuracion) {
            Utilidades::depurar('Host de conexion:"' . $hostFirma . '"');
            Utilidades::depurar('Opciones de contexto SSL: <pre>' . print_r($contextOptions, true) . '</pre>');
            Utilidades::depurar('Opciones de conexion Soap: <pre>' . print_r($opcionesSOAP, true) . '</pre>');
        }

        return new \SoapClient($hostFirma, $opcionesSOAP);
    }

    /**
     * conecta con los WS de la ULPGC
     *
     * @param Container $oContenedor
     * @return \SoapClient
     */
    public static function conectarWSSedeElectronicaULPGC($oContenedor) {


        $hostFirma = $oContenedor->getParameter('WSSedeElectronicaULPGC');
        $opcionesSOAP = array(
            'cache_wsdl' => 0,
            'trace' => true,
            'soap_version' => SOAP_1_1,
            'exceptions' => true,
            'typemap' => array(
                array(
                    'type_ns' => 'http://www.w3.org/2003/05/soap-envelope',
                    'type_name' => 'soap',
                ),
            ),
        );

        return new \SoapClient($hostFirma, $opcionesSOAP);
    }

    /**
     *
     * envia un documento a bandeja firma para ser firmado por el remitente ASINCRONO
     *
     * - Campos obligatorios del array:
     *
     *      remitente = DNI o usuario de bandeja firma del que envia el documento
     *      destinatarios = Array de dnis de los destinatarios a quienes se le envia el documento 'array ('123456789','987654321', ... )'
     *      documentoFirmar = Array del documento a firmar Ejemplo:
     *                          $datosEnvioFirma['documentoFirmar']) = array('nombre' => 'PruebasPDF.pdf',
     *                            'mime' => 'application/pdf',
     *                            'contenido' => file_get_contents('/tmp/hola1.pdf'), // NO PASAR EL base64_encode ya lo hace el solo.
     *                            'tipodocumento' => 'GENERICO')
     *      asunto = Asunto del mensaje que se envia al destinatario, como el asunto de un correo.
     *      descripcuion = Descripcion que se envia al destinatario de la peticion de firma, como el cuerpo de un correo.
     *
     * - Campos opcionales del array:
     *      referencia = Numero de referencia del envio si no se dispone se genera uno entre 1-9999 (int)
     *      sistema = Sistema que se va a usar para firmar, [ulpgc]
     *      fechaCaducidad = fecha en formato ISO 8601 se puede sacar con 'date ('c', time())'; que indica hasta cuando es valido el
     *      documento para firmar notificarCambiosPeticion (boolean) = Indica si se tiene que solicitar al WS que notifique los cambios en
     *      la peticion
     * firmaCascada = Indica si debe firmarse en orden de 'remitente' o no el documento.
     *
     * @param array              $datosEnvioFirma
     * @param ContainerInterface $oContenedor
     * @param string             $sError
     * @param GIService          $GIService
     * @return boolean|int
     * @throws \Exception
     * @example symfony4\ProgramasIntercambio\src\Controller\Base\IndexController.php enviarDocumentoEFirma URL
     */
    public static function enviarDocumentoFirmaElectronica($datosEnvioFirma, $oContenedor, &$sError) {

        /* El log queda comentado, descomentar para pruebas */

        //Utilidades::guardarLogPruebasEnvioPortafirmas("Entro en función enviarDocumentoFirmaElectronica", $sError);     
        date_default_timezone_set('UTC');

        $remitenteDNI = isset($datosEnvioFirma['remitente']) ? $datosEnvioFirma['remitente'] : false;
        $destinatarios = is_array($datosEnvioFirma['destinatario']) ? $datosEnvioFirma['destinatario'] : false;


        /*  Utilidades::guardarLogPruebasEnvioPortafirmas("Remitente      : " . $remitenteDNI, $sError);                 
          foreach ($destinatarios as $aDestinatario) {
          Utilidades::guardarLogPruebasEnvioPortafirmas("Destinatario   : " . $aDestinatario, $sError);
          } */

        /*
          DocumentoWS {
          string nombre;
          string mime;
          base64Binary contenido;
          string tipodocumento;
          FirmaWS firmas;
          } */
        $documentoFirmarWS = is_array($datosEnvioFirma['documentoFirmar']) ? array($datosEnvioFirma['documentoFirmar']) : false;
        $documentosAdjunto = isset($datosEnvioFirma['documentosAdjunto']) ? $datosEnvioFirma['documentosAdjunto'] : false;
        $asunto = isset($datosEnvioFirma ['asunto']) ? $datosEnvioFirma ['asunto'] : false;
        $descripcion = isset($datosEnvioFirma ['descripcion']) ? $datosEnvioFirma ['descripcion'] : false;


        $referenciaEnvio = isset($datosEnvioFirma['referencia']) ? $datosEnvioFirma['referencia'] : random_int(1, 9999);
        $sistema = isset($datosEnvioFirma['sistema']) ? $datosEnvioFirma['sistema'] : 'ULPGC';
        $fechaCaducidadFirma = isset($datosEnvioFirma['fechaCaducidadFirma']) ? $datosEnvioFirma['fechaCaducidadFirma'] : '';
        $fechaInicioFirma = isset($datosEnvioFirma['fechaInicioFirma']) ? $datosEnvioFirma['fechaInicioFirma'] : date('c',
                        time());

        /* Utilidades::guardarLogPruebasEnvioPortafirmas("Asunto                : " . $asunto, $sError);
          Utilidades::guardarLogPruebasEnvioPortafirmas("Descripcion           : " . $descripcion, $sError);
          Utilidades::guardarLogPruebasEnvioPortafirmas("Referencia Envio      : " . $referenciaEnvio, $sError);
          Utilidades::guardarLogPruebasEnvioPortafirmas("Sistema               : " . $sistema, $sError);
          Utilidades::guardarLogPruebasEnvioPortafirmas("Fecha Caducidad Firma : " . $fechaCaducidadFirma, $sError);
          Utilidades::guardarLogPruebasEnvioPortafirmas("Fecha Inicio Firma    : " . $fechaInicioFirma, $sError); */

        if (isset($datosEnvioFirma['notificarCambiosPeticion'])) {

            $direccionAvisoActualizacion = sprintf($oContenedor->getParameter('esqueletoAvisoActualizacionWSFirma'), $remitenteDNI,
                    $oContenedor->getParameter('protocoloNotificacionWSFirma'),
                    $oContenedor->getParameter('hostNotificacionWSFirma'),
                    $oContenedor->getParameter('puertoNotificacionWSFirma'),
                    $datosEnvioFirma['uriNotificacionWSFirma'],
                    $remitenteDNI,
                    $oContenedor->getParameter('sistemaNotificacionWSFirma'),
                    $referenciaEnvio);

            //Utilidades::guardarLogPruebasEnvioPortafirmas("Remitente DNI    : " . $remitenteDNI, $sError);                    
        } else {
            $direccionAvisoActualizacion = '';
        }


        $firmaCascada = isset($datosEnvioFirma['firmaCascada']) ? $datosEnvioFirma['firmaCascada'] : 'false';


        $clienteWS = Utilidades::conectarFirmaElectronicaULPGC($oContenedor);


        /* RemitenteWS {
          string usuario;
          boolean notificaAviso;
          boolean notificaEmail;
          boolean notificaMovil;
          }
         */
        $remitenteWS = array(
            'usuario' => $remitenteDNI,
            'notificaAviso' => 'false',
            'notificaEmail' => 'false',
            'notificaMovil' => 'false',
        );

        $prioridad = 1;

        //Para depurarcion del error -3 
        $destinatariosUsados = '';

        $intentoEntrega = 0;

        //Se intenta entrega el documento dos veces
        $ncodError = 0;
        do {
            //El contador debe empezar en uno de acuerdo a las especificaciones de BandejaFirma
            $contadorOrden = 1;

            // Utilidades::guardarLogPruebasEnvioPortafirmas("Entramos en el DO. Dos intentos de entrega del documento. Intento número: " . $contadorOrden, $sError);  

            /* EntregaWS {
              long orden;
              string usuario;
              string rol;
              }
             */
            $entregaWS = array();
            $logDestinatario = '';
            foreach ($destinatarios as $dniDestinatario) {

                if ($intentoEntrega > 0) {
                    //Esta es la segunda vez comprobando si existe NIE y si es asi con el formato 2 de nie (X005 => X05) 
                    //Si es problema del remitente
                    //  Utilidades::guardarLogPruebasEnvioPortafirmas("                                                       ", $sError);                                   
                    //  Utilidades::guardarLogPruebasEnvioPortafirmas("SI EL PROBLEMA ES EL REMITENTE                         ", $sError); 

                    if ($respuestaPeticion->codigo == '-7') {
                        $remitenteWS['usuario'] = Utilidades::comprobarDocumentoIdentificativo($remitenteDNI, true, true);
                        //    Utilidades::guardarLogPruebasEnvioPortafirmas("Si el código de respuestaPeticion es -7. El remitente pasa a ser    : " . $remitenteWS['usuario'], $sError);                                                
                    } else {
                        $remitenteWS['usuario'] = Utilidades::comprobarDocumentoIdentificativo($remitenteDNI, true, false);
                        //   Utilidades::guardarLogPruebasEnvioPortafirmas("Si el código de respuestaPeticion no es -7. El remitente pasa a ser : " . $remitenteWS['usuario'], $sError); 
                    }

                    // Utilidades::guardarLogPruebasEnvioPortafirmas("SI EL PROBLEMA ES EL DESTINATARIO                         ", $sError);                                         
                    //Si es problema del destinatario    
                    if ($respuestaPeticion->codigo == '-3') {

                        $entregaWS[] = array(
                            'usuario' => Utilidades::comprobarDocumentoIdentificativo($dniDestinatario, true, true),
                            'orden' => $contadorOrden,
                            'rol' => '',
                        );
                        //  Utilidades::guardarLogPruebasEnvioPortafirmas("Si el código de respuestaPeticion es -3. El destinatario pasa a ser    : " . $entregaWS[0]['usuario'], $sError);                                                 
                    } else {
                        $entregaWS[] = array(
                            'usuario' => Utilidades::comprobarDocumentoIdentificativo($dniDestinatario, true, false),
                            'orden' => $contadorOrden,
                            'rol' => '',
                        );
                        //    Utilidades::guardarLogPruebasEnvioPortafirmas("Si el código de respuestaPeticion no es -3. El destinatario pasa a ser : " . $entregaWS[0]['usuario'], $sError);                                          
                    }
                    $logDestinatario .= Utilidades::comprobarDocumentoIdentificativo($dniDestinatario, true, true) . '[' . $contadorOrden . ']#';
                    //Utilidades::guardarLogPruebasEnvioPortafirmas("log Destinatario: " . $logDestinatario, $sError);                                         
                } else {
                    //Utilidades::guardarLogPruebasEnvioPortafirmas("Primer intento de entrega con los datos de la BBDD sin modificar", $sError);       
                    //Esta es la primera vez con el DNI segun viene de la BBDD
                    $entregaWS[] = array(
                        'usuario' => Utilidades::comprobarDocumentoIdentificativo($dniDestinatario, true, false),
                        'orden' => $contadorOrden,
                        'rol' => '',
                    );
                    $remitenteWS['usuario'] = Utilidades::comprobarDocumentoIdentificativo($remitenteDNI, true, false);

                    $logDestinatario .= Utilidades::comprobarDocumentoIdentificativo($dniDestinatario, true, true) . '[' . $contadorOrden . ']#';


                    //Utilidades::guardarLogPruebasEnvioPortafirmas("Remitente (RemitenteWS['usuario']) : " . $remitenteWS['usuario'] , $sError);                                            
                    //Utilidades::guardarLogPruebasEnvioPortafirmas("log Destinatario                   : " . $logDestinatario, $sError);                                            
                }
                $contadorOrden++;
            }
            $intentoEntrega++;
            //Utilidades::guardarLogPruebasEnvioPortafirmas("Intento de entrega :" . $intentoEntrega, $sError); 

            $destinatariosUsados .= '|||Intento ' . $intentoEntrega . '#Remitenten:' . $remitenteWS['usuario'] . '##Destinatarios:' . $logDestinatario;
            //Utilidades::guardarLogPruebasEnvioPortafirmas("Destinatarios Usados : " . $destinatariosUsados, $sError);                         

            $peticionWS = array(
                'referencia' => $referenciaEnvio,
                'sistema' => $sistema,
                'firmaCascada' => $firmaCascada,
                'fechaInicio' => $fechaInicioFirma,
                'fechaCaducidad' => $fechaCaducidadFirma,
                'remitente' => $remitenteWS,
                'direccionAvisoActualizacion' => $direccionAvisoActualizacion,
                'asunto' => $asunto,
                'prioridad' => $prioridad,
                'descripcion' => $descripcion,
                'documentos' => $documentoFirmarWS,
                'destinatarios' => $entregaWS,
            );

            /* Utilidades::guardarLogPruebasEnvioPortafirmas("                                            ", $sError);  
              Utilidades::guardarLogPruebasEnvioPortafirmas("DATOS DE LA PETICIÓN (peticionWS)           ", $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Referencia                  : " . $referenciaEnvio, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Sistema                     : " . $sistema, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Firma Cascada               : " . $firmaCascada, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Fecha Inicio                : " . $fechaInicioFirma, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Fecha Caducidad             : " . $fechaCaducidadFirma, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Remitente                   : " . $remitenteWS['usuario'], $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("DireccionAvisoActualizacion : " . $direccionAvisoActualizacion, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Asunto                      : " . $asunto, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Prioridad                   : " . $prioridad, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Descripcion                 : " . $descripcion, $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Datos del parámetro entregaWS " , $sError); */

            /* foreach ($entregaWS as $key => $value) {
              Utilidades::guardarLogPruebasEnvioPortafirmas("Usuario                   : " . $value['usuario'], $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Orden                     : " . $value['orden'], $sError);
              Utilidades::guardarLogPruebasEnvioPortafirmas("Rol                       : " . $value['rol'], $sError);
              } */


            if ($documentosAdjunto !== false) {
                $peticionWS['documentosAdjunto'] = $documentosAdjunto;
            }

            /**
             * stdClass Object
             * (
             * [codigo] => 0
             * [estado] => La petición ha sido generada correctamente.
             * [idPeticion] => 7343
             * [hash] => FvNmDaEfzOIkx685T72UnPh0qgFEwrds820Xa6CUhvk=
             * )
             */
            $respuestaPeticion = $clienteWS->altaPeticion($peticionWS);


            //Se intenta entregar el documento dos veces una con el documento identificativo "normal" y la segunda vez con la version 2 del NIE
        } while ($intentoEntrega < 2 && $respuestaPeticion->codigo != 0);

        //Depuracion de ficheros
        if (Utilidades::depurarEsIP('206')) {
            $documentoFirmarWS[0]['contenido'] = 'Vaciado para depuracion';
            $peticionWSAUX = $peticionWS;
            $peticionWSAUX['documentos'] = $documentoFirmarWS;
            Utilidades::depurarIP($peticionWSAUX, 206);
            file_put_contents(@tempnam(DIRECTORIO_TEMPORAL, 'PeticionEnviada'), print_r($peticionWSAUX, true));
        }

        $documentoFirmarWS[0]['contenido'] = '';
        $peticionWS['documentos'] = $documentoFirmarWS;

        if (Utilidades::depurarEsIP('206') && Utilidades::esDesarrollo()) {
            Utilidades::depurarIP($respuestaPeticion, 206);
            file_put_contents(@tempnam(DIRECTORIO_TEMPORAL, 'RespuestaPeticion'), print_r($respuestaPeticion, true));
        }

        if ($respuestaPeticion->codigo == 0) {
            // Utilidades::guardarLogPruebasEnvioPortafirmas("Código de respuesta : " . $respuestaPeticion->codigo, $sError);                 
            return $respuestaPeticion->idPeticion;
        } else {

            if ($respuestaPeticion->codigo == '-3' || $respuestaPeticion->codigo == '-7') {
                //    Utilidades::guardarLogPruebasEnvioPortafirmas("Código de respuesta : " . $respuestaPeticion->codigo, $sError);
                $sError = 'Al realizar la petición de firma, codigo error devuelto: ' . $respuestaPeticion->codigo . ' ' . $respuestaPeticion->estado . ' - Destinatarios usados: ' . $destinatariosUsados;

                return false;
            }
            if (Utilidades::esDesarrollo() || preg_match('/64404/', $remitenteWS)) {
                $sError = 'Al realizar la petición de firma, codigo error devuelto: ' . $respuestaPeticion->codigo . ' ' . $respuestaPeticion->estado . PHP_EOL
                        . print_r($peticionWS, true);
                ;
            } else {
                $sError = 'Al realizar la petición de firma, codigo error devuelto: ' . $respuestaPeticion->codigo . ' ' . $respuestaPeticion->estado;
            }

            return false;
        }
    }

    /**
     * 
     * @param type $datosEnvioFirma
     * @param type $oContenedor
     * @return type
     * @throws \Exception
     */
    public static function enviarDocumentoFirmaElectronicaSello($datosEnvioFirma, $oContenedor) {

        date_default_timezone_set('Europe/London');

        $ipServidor = isset($datosEnvioFirma['ipServidor']) ? $datosEnvioFirma['ipServidor'] : false; 
        $usuarioWS = isset($datosEnvioFirma['usuarioWS']) ? $datosEnvioFirma['usuarioWS'] : false; 
        $claveWS = isset($datosEnvioFirma['claveWS']) ? $datosEnvioFirma['claveWS'] : false; 
        $remitenteDNI = isset($datosEnvioFirma['remitente']) ? $datosEnvioFirma['remitente'] : false;
        /*
          DocumentoWS {
          string nombre;
          string mime;
          base64Binary contenido;
          string tipodocumento;
          FirmaWS firmas;
          } */
        $documentoFirmarWS = is_array($datosEnvioFirma['documentoFirmar']) ? $datosEnvioFirma['documentoFirmar'] : false;
        $asunto = isset($datosEnvioFirma ['asunto']) ? $datosEnvioFirma ['asunto'] : false;
        $descripcion = isset($datosEnvioFirma ['descripcion']) ? $datosEnvioFirma ['descripcion'] : false;

        $oDateTime = new DateTime();
        $time = $oDateTime->getTimestamp();
        $oDateTime->add(new DateInterval("PT1H"));
        $fecha = $oDateTime->format(DateTime::ATOM);
        $cadena = $ipServidor . $usuarioWS . $claveWS . $time . '000';  // $time en segundos, pasarlo a milisegundos
        $password = md5($cadena);

        $referenciaEnvio = isset($datosEnvioFirma['referencia']) ? $datosEnvioFirma['referencia'] : random_int(1, 9999);
        $sistema = isset($datosEnvioFirma['sistema']) ? $datosEnvioFirma['sistema'] : 'ULPGC';
        $fechaCaducidadFirma = isset($datosEnvioFirma['fechaCaducidadFirma']) ? $datosEnvioFirma['fechaCaducidadFirma'] : '';
        $fechaInicioFirma = isset($datosEnvioFirma['fechaInicioFirma']) ? $datosEnvioFirma['fechaInicioFirma'] : $fecha;
        $selloFirma = isset($datosEnvioFirma['selloFirma']) ? $datosEnvioFirma['selloFirma'] : 'ulpgc';


        $peticionAutofirmaWS = array(
            'usuario' => $usuarioWS,
            'password' => $password,
            'sello' => $selloFirma, // sellos posibles: secretaria / gerencia / rector / ulpgc
            'fecha' => $fecha,
            'referencia' => $referenciaEnvio,
            'sistema' => $sistema,
            'fechaInicio' => $fechaInicioFirma,
            'fechaCaducidad' => $fechaCaducidadFirma,
            //  'documentos'     => $documentoFirmarWS,
            'remitente' => $remitenteDNI,
            'asunto' => $asunto,
            'descripcion' => $descripcion,
            'documento' => $documentoFirmarWS,
        );


        $clienteWS = Utilidades::conectarFirmaElectronicaULPGC($oContenedor);

        $RespuestaWS = $clienteWS->firmaAutomatica($peticionAutofirmaWS);

        if ($RespuestaWS->codigo == 0) {
            return $RespuestaWS->informe->recibo;
        } else {
            throw new \Exception('codigo error devuelto: [' . $RespuestaWS->codigo . '] ' . $RespuestaWS->estado);
        }
    }

    /**
     * Comprueba si existe una peticion y la devuelve.
     *
     * Una respuesta de bandeja firma es un XML:
     *
     * <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
     * <soapenv:Body>
     * <ns1:obtenerPeticionResponse xmlns:ns1="http://bandejafirmaservice.ws.bandejafirma.tsol.com/">
     * <referencia>9409</referencia>
     * <sistema>ULPGC</sistema>
     * <firmaCascada>true</firmaCascada>
     * <fechaInicio>2018-06-20T10:14:03.000+00:00</fechaInicio>
     * <fechaCaducidad>1970-02-01T12:41:24.286+00:00</fechaCaducidad>
     * <remitente>
     * <usuario>%DNIUSUARIO%</usuario>
     * <notificaAviso>true</notificaAviso>
     * <notificaEmail>true</notificaEmail>
     * <notificaMovil>true</notificaMovil>
     * </remitente>
     * <direccionAvisoActualizacion/>
     * <asunto>Prueba envio firma</asunto>
     * <prioridad>1</prioridad>
     * <descripcion>Firmame esto porfiii..</descripcion>
     * <documentos>
     * <nombre>PruebasPDF.pdf</nombre>
     * <mime>application/pdf</mime>
     * <contenido>...base64_encode %File%... </contenido>
     * <tipodocumento>GENERICO</tipodocumento>
     * <firmas>
     * <usuario>%DNIFirmante%</usuario>
     * <contenidoFirma> ... base_64_encode %File%... </contenidoFirma>
     * <fecha>2018-06-20T11:13:30.000+00:00</fecha>
     * </firmas>
     * </documentos>
     * <destinatarios>
     * <orden>1</orden>
     * <usuario>78514510P</usuario>
     * <rol/>
     * </destinatarios>
     * <estadosNotificacion xsi:nil="1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/>
     * <documentosAdjunto>
     * <nombre/>
     * <contenido>IA==</contenido>
     * <mime/>
     * </documentosAdjunto>
     * </ns1:obtenerPeticionResponse>
     * </soapenv:Body>
     * </soapenv:Envelope>
     *
     *
     *
     * @param string $idPeticionFirma
     * @return boolean
     * @throws \Exception
     */
    public static function obtenerPeticionFirma($idPeticionFirma, $oContenedor) {

        $clienteWS = Utilidades::conectarFirmaElectronicaULPGC($oContenedor);

        $respuestaWS = $clienteWS->obtenerPeticion($idPeticionFirma);
        //$respuestaWS = $clienteWS->obtenerInformesFirmaPeticion($idPeticionFirma);
        if (strlen($respuestaWS->documentos->contenido) < 2) {
            throw new \Exception('No existe la petición solicitada.');
        }

        return $respuestaWS;
    }

    /**
     *
     * Método que comprueba si un certificado con el que se ha firmado un PDF es valido o no, si se pasa la variable $bComprobarDNIFirmante
     *  este metodo devolvera el DNI del certificado firmante o falso.
     *
     * @param XML       $xmlPagina1 XML Formado  partir de la plantilla renderView('/Base/validacionFirmaPDF.html.twig', array());
     * @param Container $oContenedor
     * @return array 'estado' => TRUE|FALSE , 'detalle' => Indica el texto del zocalo "detalle" de la respuesta de validaFirma,
     *                              'dniCertificado' => El dni del certificado usado si 'estado'===true sino vacio ''.
     * @example \symfony4\ProgramasIntercambio\src\Controller\Base\IndexController.php metodo validarFirmaElectronicaPDF
     *
     */
    public static function comprobarCertificadoFirmaPDF($xmlPagina1, $oContenedor) {

        try {
            //Variable usada para que en la respuesta de la peticion se informe del comando usado y de la maquina real en la que se
            // ejecuto ya que estan balanceados
            $bDepurar = false;

            //conexion a WS
            $respuestaWS = Utilidades::conectarWSSedeElectronicaULPGC($oContenedor);


            $aParametros = array("xml" => base64_encode($xmlPagina1), "depurar" => $bDepurar);


            $aRespuesta = unserialize(base64_decode($respuestaWS->__soapCall("validarFirmaActa", $aParametros)));

            $xmlRespuesta = simplexml_load_string(preg_replace('/^-\s+\</', '<', base64_decode($aRespuesta['xmlRespuesta'])));

            //Se coloca un or por si acaso a veces de vuelve true y a veces la string true
            if ($xmlRespuesta->respuesta->Respuesta->estado == 'true' || $xmlRespuesta->respuesta->Respuesta->estado == true) {

                $certificadoDocumento = $xmlRespuesta->respuesta->Respuesta->descripcion->validacionFirmaElectronica->informacionAdicional->firmante->certificado;

                return array(
                    'estado' => true,
                    'detalle' => (string) $xmlRespuesta->respuesta->Respuesta->descripcion->validacionFirmaElectronica->detalle,
                    'dniCertificado' => Utilidades::obtenerDNICertificado($certificadoDocumento),
                );
            } else {
                return array(
                    'estado' => false,
                    'detalle' => (string) $xmlRespuesta->respuesta->Respuesta->descripcion->validacionFirmaElectronica->detalle,
                    'dniCertificado' => '',
                );
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        }
    }

    /**
     * obtiene el informe de una peticion
     *
     * @param string $idPeticionFirma Codigo peticion de BandejaFirma
     * @param type   $oContenedor
     * @return mixed false o $respuestaWS
     */
    public static function obtenerInformeFirma($idPeticionFirma, $oContenedor) {

        $clienteWS = Utilidades::conectarFirmaElectronicaULPGC($oContenedor);

        $respuestaWS = $clienteWS->obtenerInformesFirmaPeticion($idPeticionFirma);

        if ($respuestaWS->totalInformesFirmaPeticion > 0 && is_object($respuestaWS->informes)) {
            return $respuestaWS;
        } else {
            return false;
        }
    }

    /**
     * obtiene el CSV del documento a firmar
     *
     * @param string $idPeticionFirma Codigo peticion de BandejaFirma
     * @param type   $oContenedor
     * @return mixed false o el CSV del documetno
     */
    public static function obtenerCSVDocumentoFirmado($idPeticionFirma, $oContenedor) {
        $respuestaWS = Utilidades::obtenerInformeFirma($idPeticionFirma, $oContenedor);
        if ($respuestaWS === false) {
            return false;
        } else {
            return $respuestaWS->informes->codigoSeguroVerificacion;
        }
    }

    /**
     *
     * @param string $pathPdf Ruta del fichero
     * @return mixed  devuelve el certificado en formato PCKS7Binary o falso si falla algo
     */
    public static function extraerCertificadoPDF($pathPdf) {


        $content = file_get_contents($pathPdf);

        //ByteRange [0 257 54259 55888 ]
        //ByteRange[0 257 54259 55888 ]
        $regexp = '/ByteRange[\s+]?\[[\s+]?(\d+)[\s+]?(\d+)[\s+]?(\d+)/'; // subexpressions are used to extract b and c

        $result = [];
        preg_match_all($regexp, $content, $result);

        // $result[2][0] and $result[3][0] are b and c
        if (isset($result[2]) && isset($result[3]) && isset($result[2][0]) && isset($result[3][0])) {
            $start = $result[2][0];
            $end = $result[3][0];
            if ($stream = fopen($pathPdf, 'rb')) {
                $signature = stream_get_contents($stream, $end - $start - 2, $start + 1); // because we need to exclude < and > from start and end

                fclose($stream);
            }
            if (strlen($signature) < 1) {
                return false;
            }

            return chunk_split(base64_encode(hex2bin($signature)), 64);
        }

        return false;
    }

    /**
     * extrae el DNI de un certificado FNMT o DNIE en formato PEM sacandolo del subject => serialnumber
     *
     * @param x509 $certificadoX509
     * @return string
     */
    public static function obtenerDNICertificado($certificadoX509) {
        $beginpem = "-----BEGIN CERTIFICATE-----";
        $endpem = "-----END CERTIFICATE-----";

        $sDNI = '';
        //Se eliminan los posibles espacios en blanco
        $certificadoX509 = trim($certificadoX509);

        if (preg_match('/' . $beginpem . '/', $certificadoX509)) {
            // Se agrega la cabecera y el final del certificado
            $certificado = $certificadoX509;
        } else {
            $certificado = $beginpem . PHP_EOL . $certificadoX509 . PHP_EOL . $endpem;
        }

        // Se carga un recurso nuevo con el certificado
        $cert = openssl_x509_parse($certificado);

        /**
         * - Certificado FNMT
         * [serialNumber] => IDCES-12345678Z
         *
         * - Certificado DNIE
         * [serialNumber] => 12345678Z
         */
        $sDNI = preg_replace('/IDCES-/', '', $cert['subject']['serialNumber']);

        return $sDNI;
    }

    /**
     * calcularLetraDNI
     *
     * Devuelve el digito de control de un DNI
     *
     * @param string $sDni
     * @return char
     */
    public static function calcularLetraDNI($sDni) {

        return substr("TRWAGMYFPDXBNJZSQVHLCKEO", $sDni % 23, 1);
    }

    /**
     * calcularNIE
     *
     * Devuelve el digito de control de un NIE
     *
     * @param string $sDocumento
     * @return char
     */
    private static function calcularNIE($sDocumento) {

        $digitosReemplazo = array(0 => 'X', 1 => 'Y', 2 => 'Z');

        $documentoValidar = array_search(substr($sDocumento, 0, 1), $digitosReemplazo) . substr($sDocumento, 1);

        return substr("TRWAGMYFPDXBNJZSQVHLCKEO", $documentoValidar % 23, 1);
    }

    /**
     * esNIE
     *
     * Devuelve TRUE|FALSO si un documento identificativo es NIE
     *
     * @param type $sDocumento
     * @return boolean
     */
    private static function esNIE($sDocumento) {
        switch (substr($sDocumento, 0, 1)) {
            case 'X':
            case 'Y':
            case 'Z':
                return true;

            default:
                return false;
        }
    }

    /**
     * Comprobamos si tiene ocho digitos seguidos de una letra, opcional
     *
     * @param type $sDocumento
     * @return type
     */
    private static function esDNI($sDocumento) {

        return preg_match('/^\d{8}([a-zA-Z]{1})?$/', $sDocumento);
    }

    /**
     * comprobarDocumentoIdentificativo
     *
     * Esta funcion realiza las siguientes acciones:
     *  - Comprueba si es VALIDO un documento tipo NIE,NIF,DNI
     *      > ejemplo comprobarDocumentoIdentificativo(Z7357048C) =>[NIE] return TRUE ; comprobarDocumentoIdentificativo(Z7357048D) =>
     *      [NIE] return FALSE
     *  - Devuelve un documento identificativo con su digito de control.
     *      > ejemplo comprobarDocumentoIdentificativo(Z7357048,TRUE) => [NIE] return Z7357048C ;
     *      comprobarDocumentoIdentificativo(59069654,TRUE) [DNI] return 59069654L
     *
     * @param type    $sDocumento          documento a verificar
     * @param boolean $bDevolverCompletado devuelve el documento con digito de control incluido
     * @param boolean $bVersion2NIE        Devuelve el nie cambiando X0 => X para los casos en que el NIE empiece por X0 y tenga nueve
     *                                     caracteres
     * @return mixed Boolean si solo se quiere comprovar la validez del documento y STRING si se quiere el documento completo
     * @throws \Exception
     */
    public static function comprobarDocumentoIdentificativo($sDocumento, $bDevolverCompletado = false, $bVersion2NIE = false) {

        $tamanioDocumento = strlen($sDocumento);

        if ($tamanioDocumento == 9 && preg_match('/X0\d{6}\w{1}$/', $sDocumento)) {

            //Se fuerza a 8 ya que mpieza por X0 y viene con dígito de control al final
            if (!preg_match('/X0\d{7}/', $sDocumento)) {
                $sDocumento = substr($sDocumento, 0, 8);
                $tamanioDocumento = strlen($sDocumento);
            }
        }

        if ($tamanioDocumento == 10 && preg_match('/X00\d{6}\w{1}$/', $sDocumento)) {

            //Se fuerza a 9 el documento quitando el doble cero inicial
            $sDocumento = substr($sDocumento, 0, 9);
            $tamanioDocumento = strlen($sDocumento);
        }
        //Se comprueba si el documento tiene nueve digitos o si tiene ocho y se quiere devolver completo
        if ($tamanioDocumento == 9 || ($bDevolverCompletado && $tamanioDocumento == 8)) {

            //Si es NIE con 9 caracteres no hay digito de control incluido
            if (self::esNIE($sDocumento) && strlen($sDocumento) == 9 && preg_match('/^X0/', $sDocumento)) {

                //El digito de control siempre esta en el mismo sitio sea NIE,NIF,DNI(el DNI no tiene)
                $digitoControlDocumento = substr($sDocumento, 8, 1);

                //Se pasan los 8 primeros caracteres.. sin el digito de control para el calculo del mismo
                $documentoSinDigitoControl = substr($sDocumento, 0);
            } else {
                //El digito de control siempre esta en el mismo sitio sea NIE,NIF,DNI(el DNI no tiene)
                $digitoControlDocumento = substr($sDocumento, 8, 1);

                //Se pasan los 8 primeros caracteres.. sin el digito de control para el calculo del mismo
                $documentoSinDigitoControl = substr($sDocumento, 0, 8);
            }
            //Si es NIE se calcula el digito de control como nie
            if (self::esNIE($sDocumento)) {

                $digitoControlCalculado = self::calcularNIE($documentoSinDigitoControl);
            } else {
                $digitoControlCalculado = self::calcularLetraDNI($documentoSinDigitoControl);
            }

            //Si se quiere devolver completo no se comprueba el digito de control ya que lo hemos calculado nosotros y deberia estar bien xD
            if ($bDevolverCompletado) {
                //La version 2 del nie devuelve el NIE sin el 'X0' delante cambiando 'X0' => 'X'
                if ($bVersion2NIE && self::esNIE($sDocumento) && strlen($sDocumento) == 9 && preg_match('/^X0/', $sDocumento)) {
                    return 'X' . substr($documentoSinDigitoControl, 2) . $digitoControlCalculado;
                } else {
                    return $documentoSinDigitoControl . $digitoControlCalculado;
                }
            }

            //Si el digito de control calculado coincide con eldigito de control pasado en el documento validamos la operacion
            if ($digitoControlDocumento == $digitoControlCalculado) {
                return true;
            }
        }

        if ($tamanioDocumento < 9) {
            throw self::controlarError('El documento no tiene el tama&ntilde;o apropiado ( 9 car&aacute;cteres ) "' . $sDocumento . '".');
        }

        throw self::controlarError('No se ha podido determinar la validez del documento "' . $sDocumento . '".');
    }

    /**
     * calcularDocumentoIdentificativo
     *
     *  Calcula la letra de un documento tipo NIE,NIF,DNI y devuelve el documento completo con 9 caracteres
     *
     * @param string $sDocumento
     * @return string
     */
    public static function calcularDocumentoIdentificativo($sDocumento) {

        try {
            return self::comprobarDocumentoIdentificativo($sDocumento, true);
        } catch (\Exception $ex) {
            throw self::controlarError($ex->getMessage());
        }
    }

    /**
     * Devuelve el texto [NIE|DNI|''] Dependiendo del tipo de documento identificativo
     * calculado
     *
     * @param type    $sDni
     * @param boolean $bException Devuelve una exception si no encuentra el tipo
     * @return string
     * @throws type
     */
    public static function tipoDocumentoIdentificativo($sDni, $bException = false) {
        switch (SELF::calcularTipoDocumentoIdentificativo($sDni)) {
            case 1:
                return 'NIE';
            case 2:
                return 'DNI';
            case 0:
            default:
                if ($bException) {
                    throw Utilidades::controlarError('No se puede determinar el tipo de documento [' . $sDni . ']');
                } else {
                    return '';
                }
        }
    }

    /**
     * Devuelve el tipo [NIE|DNI] de documento pasado
     *
     * @param string $sDocumento
     * @return int [0,1,2] => 0 No se sabe cual es, 1 => NIE, 2 => DNI
     */
    public static function calcularTipoDocumentoIdentificativo($sDocumento) {

        try {


            $sDocumentoCompleto = self::comprobarDocumentoIdentificativo($sDocumento, true);

            if (self::esNIE($sDocumentoCompleto)) {
                return 1;
            } elseif (preg_match('/^\d{8}\w{1}$/', $sDocumentoCompleto)) {
                return 2;
            } else {
                return 0;
            }
        } catch (\Exception $ex) {
            return 0;
            throw $ex;
        }
    }

    /**
     * Devuelve un array por defecto con el numero decuenta.
     *
     * @param type $cuentaIBAN Cuenta en formato iban ES7620770024003102575766
     * @param type $devolverString
     * @return mixed devuelve Array o strig con el CCC dependiendo de si devolverString=TRUE, si devuelve array es formado:
     *                         array('entidad' => $entidad
     *                         , 'sucursal' => $sucursal
     *                         , 'digitocontrol' => $digitoControl
     *                         , 'numerocuenta' => $numeroCuenta);
     */
    public static function IBANaCCC($cuentaIBAN, $devolverString = false) {
        //El codigo iban tiene un maximo de 34 caracteres y un minimo de 4
        try {

            if (strlen($cuentaIBAN) < 35 && strlen($cuentaIBAN) > 4) {
                $paisIBAN = substr($cuentaIBAN, 0, 2);

                /* PATRONES DE CUENTAS BANCARIAS */
                /*  A - Primeros caracteres del Código SWIFT
                  B - Código de banco
                  C - Número de cuenta
                  D - Dígitos de control / Claves
                  O - Código de oficina
                  S - Código de tipo de cuenta
                  x - Dígitos de control para el IBAN */

                switch ($paisIBAN) {
                    //España  --  	BBBB OOOO DDCC CCCC CCCC
                    case 'ES':
                        $entidad = substr($cuentaIBAN, 4, 4);
                        $sucursal = substr($cuentaIBAN, 8, 4);
                        $digitoControl = substr($cuentaIBAN, 12, 2);
                        $numeroCuenta = substr($cuentaIBAN, 14);
                    //Alemania  -- BBBB BBBB CCCC CCCC CC
                    case 'DE':
                    //Austria  --  BBBB BCCC CCCC CCCC
                    case 'AT':
                    //Bélgica  -- BBBC CCCC CCDD
                    case 'BE':
                    //Bulgaria  --	AAAA OOOO DDCC CCCC CC
                    case 'BG':
                    //Chipre  --  	BBBS SSSS CCCC CCCC CCCC CCCC
                    case 'CY':
                    //Croacia  --  	BBBB BBBC CCCC CCCC C
                    case 'HR':
                    //Dinamarca  --	BBBB CCCC CCCC CC
                    case 'DK':
                    //Eslovaquia  --  BBBB CCCC CCCC CCCC CCCC
                    case 'SK':
                    //Eslovenia  --  BBBB BCCC CCCC CDD
                    case 'SI':
                    //Estonia  --  	BBBB CCCC CCCC CCCD
                    case 'EE':
                    //Finlandia  --  BBBB BBCC CCCC CD
                    case 'FI':
                    //Francia  --  	BBBB BOOOO OCCC CCCC CCCC DD
                    case 'FR':
                    //Grecia  --  	BBBB BBBC CCCC CCCC CCCC CCC
                    case 'GR':
                    //Hungría  --  	BBBO OOOD CCCC CCCC CCCC CCCD
                    case 'HU':
                    //Irlanda  --  	AAAA BBBB BBCC CCCC CC
                    case 'IE':
                    //Italia  --  	DBBB BBOO OOOC CCCC CCCC CCC
                    case 'IT':
                    //Letonia  --  	BBBB CCCC CCCC CCCC C
                    case 'LV':
                    //Lituania  --  BBBB BCCC CCCC CCCC C
                    case 'LT':
                    //Luxemburgo  --  BBBC CCCC CCCC CCCC
                    case 'LU':
                    //Malta  --  BBBB SSSS SCCC CCCC CCCC CCCC CCC
                    case 'MT':
                    //Países Bajos  --  BBBB CCCC CCCC CC
                    case 'NL':
                    //Polonia  --  	BBBB BBBD CCCC CCCC CCCC CCCC
                    case 'PL':
                    //Portugal  --  BBBB OOOO CCCC CCCC CCCD D
                    case 'PT':
                    //Reino Unido  --  BBBB SSSS SSCC CCCC CC
                    case 'GB':
                    //República Checa  --  BBBB SSSS SSCC CCCC CCCC
                    case 'CZ':
                    //Rumania  -- BBBB CCCC CCCC CCCC CCCC
                    case 'RO':
                    //Suecia  --  BBBB CCCC CCCC CCCC CCCC
                    case 'SE':
                }
            } else {

                return false;
            }

            if ($devolverString) {
                return $entidad . $sucursal . $digitoControl . $numeroCuenta;
            } else {
                return array(
                    'entidad' => $entidad
                    ,
                    'sucursal' => $sucursal
                    ,
                    'digitocontrol' => $digitoControl
                    ,
                    'numerocuenta' => $numeroCuenta,
                );
            }
        } catch (\Exception $ex) {
            throw Utilidades::controlarError($ex);
        }
    }

    /**
     *
     * Valor para validar una cuenta bancaria IBAN
     *
     *
     */
    public static function validarIBAN($iban) {
        // $this->validarCCC($iban);
        # definimos un array de valores con el valor de cada letra

        $letras = array(
            "A" => 10,
            "B" => 11,
            "C" => 12,
            "D" => 13,
            "E" => 14,
            "F" => 15,
            "G" => 16,
            "H" => 17,
            "I" => 18,
            "J" => 19,
            "K" => 20,
            "L" => 21,
            "M" => 22,
            "N" => 23,
            "O" => 24,
            "P" => 25,
            "Q" => 26,
            "R" => 27,
            "S" => 28,
            "T" => 29,
            "U" => 30,
            "V" => 31,
            "W" => 32,
            "X" => 33,
            "Y" => 34,
            "Z" => 35,
        );

        # Eliminamos los posibles espacios al inicio y final

        $iban = trim($iban);
        # Convertimos en mayusculas

        $iban = strtoupper($iban);
        # eliminamos espacio y guiones que haya en el iban

        $iban = str_replace(array(" ", "-"), "", $iban);

        if (strlen($iban) == 24) {

            # obtenemos los codigos de las dos letras

            $valorLetra1 = $letras[substr($iban, 0, 1)];

            $valorLetra2 = $letras[substr($iban, 1, 1)];

            # obtenemos los siguientes dos valores

            $siguienteNumeros = substr($iban, 2, 2);

            $valor = substr($iban, 4, strlen($iban)) . $valorLetra1 . $valorLetra2 . $siguienteNumeros;


            if (bcmod($valor, 97) == 1) {

                return true;
            } else {

                return false;
            }
        } else {

            return false;
        }
    }

    /**
     * Devuelve si un CCC  es valido o no
     *
     * @param string $ccc => $this->getCCC();
     * @return boolean
     */
    public static function validarCCC($ccc) {
        //$ccc sería el 20770338793100254321
        $valido = true;

        if (strlen($ccc) != 20 || empty($ccc)) {
            return false;
        }
        ///////////////////////////////////////////////////
        //    Dígito de control de la entidad y sucursal:
        //Se multiplica cada dígito por su factor de peso
        ///////////////////////////////////////////////////
        $suma = 0;
        $suma += $ccc[0] * 4;
        $suma += $ccc[1] * 8;
        $suma += $ccc[2] * 5;
        $suma += $ccc[3] * 10;
        $suma += $ccc[4] * 9;
        $suma += $ccc[5] * 7;
        $suma += $ccc[6] * 3;
        $suma += $ccc[7] * 6;

        $division = floor($suma / 11);
        $resto = $suma - ($division * 11);
        $primer_digito_control = 11 - $resto;
        if ($primer_digito_control == 11) {
            $primer_digito_control = 0;
        }

        if ($primer_digito_control == 10) {
            $primer_digito_control = 1;
        }

        if ($primer_digito_control != $ccc[8]) {
            $valido = false;
        }

        ///////////////////////////////////////////////////
        //            Dígito de control de la cuenta:
        ///////////////////////////////////////////////////
        $suma = 0;
        $suma += $ccc[10] * 1;
        $suma += $ccc[11] * 2;
        $suma += $ccc[12] * 4;
        $suma += $ccc[13] * 8;
        $suma += $ccc[14] * 5;
        $suma += $ccc[15] * 10;
        $suma += $ccc[16] * 9;
        $suma += $ccc[17] * 7;
        $suma += $ccc[18] * 3;
        $suma += $ccc[19] * 6;

        $division = floor($suma / 11);
        $resto = $suma - ($division * 11);
        $segundo_digito_control = 11 - $resto;

        if ($segundo_digito_control == 11) {
            $segundo_digito_control = 0;
        }
        if ($segundo_digito_control == 10) {
            $segundo_digito_control = 1;
        }

        if ($segundo_digito_control != $ccc[9]) {
            $valido = false;
        }

        return $valido;
    }

    /**
     * Calcula el IBAN a partir de un CCC y codigo pais
     *
     * @param string $codigoPais
     * @param string $ccc
     * @return string
     */
    public static function calcularIBAN($codigoPais, $ccc) {
        if ($ccc) {
            $pesos = array(
                'A' => '10',
                'B' => '11',
                'C' => '12',
                'D' => '13',
                'E' => '14',
                'F' => '15',
                'G' => '16',
                'H' => '17',
                'I' => '18',
                'J' => '19',
                'K' => '20',
                'L' => '21',
                'M' => '22',
                'N' => '23',
                'O' => '24',
                'P' => '25',
                'Q' => '26',
                'R' => '27',
                'S' => '28',
                'T' => '29',
                'U' => '30',
                'V' => '31',
                'W' => '32',
                'X' => '33',
                'Y' => '34',
                'Z' => '35',
            );
            $dividendo = $ccc . $pesos[substr($codigoPais, 0, 1)] . $pesos[substr($codigoPais, 1, 1)] . '00';
            $digitoControl = 98 - bcmod($dividendo, '97');
            if (strlen($digitoControl) == 1) {
                $digitoControl = '0' . $digitoControl;
            }

            return $codigoPais . $digitoControl . $ccc;
        } else {
            return $ccc;
        }
    }

    /**
     *
     * compruba si una variables es array y es mayor a 0
     *
     * @param array $arrayComprobar
     * @return boolean
     */
    public static function ComprobarArray($arrayComprobar) {

        if (is_array($arrayComprobar) && count($arrayComprobar) > 0) {

            return true;
        } else {

            return false;
        }
    }

    /**
     *
     * Ordena un array de objetos doctrine por medio de un metodo ( getDni, getMunicipio, getPepito )
     *  esto se ha tenido que hacer ya que el Doctrine devolvia la ordenacion primero ordenando las mayusculas luego las minusculas y
     *  posteriormente ordenaba las palabras con tilde.
     *
     *  Ejemplo [ A(tilde)nima,  Asistencia, A(tilde)rbol, Dedo, nombre, alma ]:
     *
     *      (Sin usar esta funcion)
     *      - Asistencia
     *      - Dedo
     *      - alma
     *      - nombre
     *      - A(tilde)nima
     *      - A(tilde)rbol
     *
     *      (Usando esta funcion)
     *      - alma
     *      - A(tilde)nima
     *      - Asistencia
     *      - A(tilde)rbol
     *      - Dedo
     *      - nombre
     *
     * @param array  $arrayObjetos     Array de objetos doctrine
     * @param string $metodoOrdenacion Campo del objeto que se usara para ordenar ( ejemplo => getADES40 )
     * @return array
     */
    public static function OrdenarArrayObjetosDoctrine($arrayObjetos, $metodoOrdenacion) {
        $arrayObjetosOrdenados = [];

        $aOrden = [];

        foreach ($arrayObjetos as $index => $objeto) {

            $aOrden[call_user_func_array(array($objeto, $metodoOrdenacion), array())] = $index;
        }

        $LocaleAnterior = setlocale(LC_COLLATE, "0");
        setlocale(LC_COLLATE, 'es_ES.utf8');

        uksort($aOrden, 'strcoll');

        setlocale(LC_COLLATE, $LocaleAnterior);

        reset($arrayObjetos);

        foreach ($aOrden as $posicionArray) {
            $arrayObjetosOrdenados[] = $arrayObjetos[$posicionArray];
        }

        return $arrayObjetosOrdenados;
    }

    /**
     * Se pasa una fecha a formato SPAIN
     *
     * @param type $fechaISO
     * @return DateTime
     */
    public static function obtenerFechaParaBD($fechaISO) {
        $fechaBD = str_replace('/', '-', $fechaISO); //si no se hace este reemplazo, se interpreta la fecha en formato americano
        $fechaBD = date('Y-m-d h:i:s', strtotime($fechaBD));
        $fechaBD = new DateTime($fechaBD);

        return $fechaBD;
    }

    /**
     * @param $object
     * @return array. Transforma el objeto pasado a un array.
     * También convierte array de array si se le pasa array de objetos, dando igual
     * la "profuncidad" de estos
     * @throws \ReflectionException
     */
    public static function objectToArray($object) {

        $res = array();
        if (is_array($object)) {
            foreach ($object as $k => $v) {
                if (is_object($v) || is_array($v)) {
                    $v = self::objectToArray($v);
                }
                $res[$k] = $v;
            }

            return $res;
        }
        $rc = new \ReflectionClass(get_class($object));
        $metodos = $rc->getMethods();
        $res = array();
        foreach ($metodos as $met) {
            $name = $met->getName();
            if (!$met->isPublic() || !preg_match('/^get/', $name) ||
                    $met->getNumberOfParameters() > 0
            ) {
                continue;
            }
            $val = $object->$name();
            $name = strtolower(preg_replace('/^get/', '', $name));
            if (is_object($val)) {
                $val = self::objectToArray($val);
            } else {
                if (is_array($val)) {
                    $val = self::objectToArray($val);
                }
            }
            $res[$name] = $val;
        }

        return $res;
    }

    /**
     * ofusca un correo electronico, sustituye por '*' todos los caracteres del correo menos el primero y el anterior a la '@' ejemplo:
     *
     *  pepejuanandres@gmail.com => p************s@gmail.com
     *
     * @param string $correo Correo electronico a ofuscar
     *
     * @return string correo electronico ofuscado
     */
    public static function ofuscarCorreo($correo) {

        $ocurrencias = array();

        preg_match('/^((.).*(.))(\@.*)/', $correo, $ocurrencias);

        if (count($ocurrencias) > 0) {
            return $ocurrencias[2] . str_repeat('*', (strlen($ocurrencias[1]) - 2)) . $ocurrencias[3] . $ocurrencias[4];
        } else {
            return $correo;
        }
    }

    /**
     * Comprueba si se puede acceder al controlador de comprobaciones
     *
     * Solo se puede accceder si es de ULPGC, esta el entorno en desarrollo y es
     *  un servidor de ULPGC
     *
     */
    public static function controlAccesoComprobaciones() {
        //Si esta definida la constante y es un array compramos, en caso contrario
        // entendemos que esta desactivado este control.
        if (defined('depuracionIP') && is_array(depuracionIP)) {
            
            if (self::esDesarrollo() &&
                    (self::depurarESServidorSI() || self::depurarESULPGC() && self::depurarESServidorULPGC())
            ) {
                return true;
            } else {
                return false;
            }
        }else{
            return true;
        }
    }

    /*     * *
     * Devuelve pagina no encontrada 404
     */

    public static function respuestaError404() {

        $texto = 'Pagina no encontrada';

        return new \Symfony\Component\HttpFoundation\Response($texto, \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND);
    }

    /**
     * Procesa una respuesta enviada desde porta firmas al evento firmar|denegar, devuelve un array del estilo:
     *      array[
     *              'firmante', DNI con letra en mayuscula
     *              'entorno', => entorno que se le paso al enviar la petición de firma en el campo 'sistema' de
     *              (enviarDocumentoFirmaElectronica)
     *              'remitente', => DNI con letra en mayuscula
     *              'hash', => $Hash devuelto del portafirmas
     *              'referenciaSolicitud', => referencia enviada al pedir la firma en el campo 'referencia' de
     *              (enviarDocumentoFirmaElectronica)
     *              'referenciaFirma', (En caso de rechazada viene aqui 'D') => Texto o 'D'
     *              'firmada' => S|N
     *              'texto' => Viene la palabra 'FIRMADO' o el motivo denegacion si referenciaFirma == 'D'
     *              'xml' => El contenido del XML procesado en RAW
     *           ]
     *
     *
     *
     * <?xml version="1.0" encoding="UTF-8"?>
     * <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
     * xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
     *  <soapenv:Body>
     *      <ns1:actualizarFirmas soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns1="urn:UpdateFirmas">
     *          <in0 xsi:type="xsd:string">Destinatario</in0>
     *          <in1 xsi:type="xsd:string">hash#idsolicitud</in1>
     *          <in2 xsi:type="xsd:string">numeroReferencia</in2> <!-- rechazada 'D' , firmada numero -->
     *          <in3 xsi:type="xsd:string">texto</in3> <!-- Si rechazada aqui viene el motivo del rechazo si es Firmada vendra la palabra
     *          FIRMADO -->
     *          <in4 xsi:type="xsd:string">remitente</in4>
     *          <in5 xsi:type="xsd:string">sistema</in5>
     *      </ns1:actualizarFirmas>
     *  </soapenv:Body>
     * </soapenv:Envelope>
     *
     *
     *
     *
     *
     * @param string  Se pasa directamente un xml cargado previamente con file_get_content o un $request->getContent
     * @return array
     * @throws \Exception
     */
    public static function procesarRespuestaPortaFirmas($xmlCargado) {

        $aRespuesPortaFirmas = [];

        try {


            //Se realiza una limpieza de los XML ya que sino SIMPLEXML no lo parsea adecuadamente
            $xmlPreproceso = preg_replace('/<\/ns1.*/', '</Body>', preg_replace('/<soapenv:Envelope.*UpdateFirmas">/', '<Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">', $xmlCargado));

            //Se carga el XML
            $xml = simplexml_load_string($xmlPreproceso);

            #####
            # Carga de variables 
            #####
            #
            //Dni de quien firmo la peticion
            $aRespuesPortaFirmas['firmante'] = strtoupper($xml->in0);

            //Viene un hash '#' y la referencia enviada a portafirmas
            // ejemeplo: '<in1 xsi:type="xsd:string">4ZXauZDT62fplJKxKuRqt22ENbocdyBDFg8ZAeaJeK4=#2-219</in1>'
            //Se guarda el hash por un lado y la referencia por otro 
            list($aRespuesPortaFirmas['hash'], $aRespuesPortaFirmas['referenciaSolicitud']) = explode('#', $xml->in1);

            //Referencia de la firma creada por PortaFirmas
            $aRespuesPortaFirmas['referenciaFirma'] = (string) $xml->in2;

            //Texto devuelto en la firma
            $aRespuesPortaFirmas['texto'] = (string) $xml->in3;

            //Comprobamos si ha sido denegada
            if ($aRespuesPortaFirmas['referenciaFirma'] == self::ULiteralFirmaDenegada) {
                $aRespuesPortaFirmas['firmado'] = self::ULiteralNegativo;
            } else {
                $aRespuesPortaFirmas['firmado'] = self::ULiteralPositivo;
            }

            //Se carga el espacio de trabajo o entorno a que hace referencia la peticion
            $aRespuesPortaFirmas['entorno'] = (string) $xml->in5;

            //Dni Remitente de la firma
            $aRespuesPortaFirmas['remitente'] = strtoupper($xml->in4);

            //Se guarda el XML procesado por si se quiere usar para otra cosa
            $aRespuesPortaFirmas['xml'] = $xml;

            return $aRespuesPortaFirmas;
        } catch (Exception $ex) {

            throw Utilidades::controlarError($ex);
        }
    }

    /**
     *
     * Descarga un fichero a partir de un stream, lo pone temporalmente en el servidor y lo envia a descargar
     *
     * @param mixed   $streamFichero   Contenido del fichero
     * @param string  $sNombreFichero  Nombre del fichero que se le pone al descargar
     * @param boolean $bForzarDescarga Indica si el fichero se va a descargar(true) o abrir en el Navegador(false)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function descargaFicheroPDF($streamFichero, $sNombreFichero, $bForzarDescarga = true) {

        if (!empty($streamFichero)) {
            //Preparamos un fichero temporal
            $tmpName = @tempnam(DIRECTORIO_TEMPORAL, 'descargaPDF');

            //Cargamos el fichero temporal
            file_put_contents($tmpName, $streamFichero);

            //Obtenemos el tipo mime del fichero
            if (!function_exists('mime_content_type')) {
                $mimeType = trim(shell_exec('file -b --mime-type ' . $tmpName));
            } else {
                $mimeType = mime_content_type($tmpName);
            }

            if (!empty($streamFichero)) {
                $response = new Response();
                $response->headers->set('Content-Type', $mimeType);

                if ($bForzarDescarga) {
                    $response->headers->set('Content-Disposition',
                            'attachment;filename="' . utf8_decode($sNombreFichero) . '.pdf');
                } else {
                    $response->headers->set('Content-Disposition',
                            'filename="' . utf8_decode($sNombreFichero) . '.pdf');
                }

                $response->setContent(file_get_contents($tmpName));

                //Borramos el fichero temporal
                unlink($tmpName);

                return $response;
            }
        }

        return self::respuestaError('El fichero es un parametro necesario');
    }

    /**
     * Funcion usada para incluir variables de desarrrollo donde se altere las llamadas a WS o BD
     *
     * @param Request                   $request
     * @param \App\Service\ARUServices $ARUServices
     */
    public static function pruebasDesarrollo(Request $request, \App\Service\ARUServices $ARUServices) {

        $aVariablesDesarrollo = new \Doctrine\Common\Collections\ArrayCollection();

        if (Utilidades::esDesarrollo() && $request->get('Desarrollo', false)) {

            $aVariablesDesarrollo = new \Doctrine\Common\Collections\ArrayCollection($request->get('Desarrollo', array()));

            Utilidades::mostrarInformacion('Se esta incluyendo variables de desarrollo que alteran el funcionamiento');
            $ARUServices->getLogger()->addInfo('Se estan incluyendo variables de desarrollo. ',
                    array(
                        'variables' => $request->get('Desarrollo', false),
                        'session' => $aVariablesDesarrollo,
            ));
        }

        if (Utilidades::esDesarrollo()) {
            $oSession = $request->getSession();
            $oSession->set('Desarrollo', $aVariablesDesarrollo);
            $oSession->save();
        }
    }

    /**
     * Oculta parte de un string
     *
     * @param string  $str   Texto a ocultar
     * @param integer $start Cuantos caracteres dejar sin ocultar al inicio
     * @param integer $end   Cuantos caracteres dejar sin ocultar al final
     * @return string
     */
    public static function ocultarString($str, $start = 1, $end = 1) {
        $len = strlen($str);

        return substr($str, 0, $start) . str_repeat('*', $len - ($start + $end)) . substr($str, $len - $end, $end);
    }

    /**
     * Devuelve la IP del cliente
     *
     * @return type
     */
    public static function getClienteIP() {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Devuelve la IP del servidor
     *
     * @return type
     */
    public static function getServidorIP() {
        return $_SERVER['SERVER_ADDR'];
    }

    /**
     * Devuelve sexo por el contexto Don/Doia , Dra/Dr
     *
     * @param string $sTextoAnalizar  El texto a analizar que debe contener Dr|Dra
     *                                parara en el primer match encontrado
     * @return string [M|V] Femenino o Masculino si devuelve vacio es que no se
     *                                pudo determinar
     */
    public static function getGeneroPersona($sTextoAnalizar) {

        if (preg_match('/dra|doña|dña|doctora/i', $sTextoAnalizar)) {
            //Femenino
            return 'M';
        } elseif (preg_match('/dr|don|d\.|doctor/i', $sTextoAnalizar)) {
            //Masculino
            return 'V';
        }

        return '';
    }

    /**
     * Devuelve el articulo la,el, el/la dependiendo del genero
     *
     * @param string $sGenero
     * @return string
     */
    public static function getArticuloGenero($sGenero) {
        switch ($sGenero) {
            //Femenino
            case 'M':
                return 'la ';
            //Masculino
            case 'V';
                return 'el ';
            //No definido
            default:
                return 'el/la';
        }
    }

    /**
     * Devuelve el tratamiento a usar para
     *
     * @param string  $sTextoAnalizar el texto a analizar debe contener 'Dr/Dra'||'Don/Doña'||'M/V'
     * @param boolean $bIncluirDoct
     * @return string
     */
    public static function getTratamientoPersona($sTextoAnalizar, $bAbreviado = true, $bIncluirDoct = false, $bIncluirArticulo = false) {

        $sRespuesta = '';

        if (strlen($sTextoAnalizar) > 1) {
            $sSexo = self::getGeneroPersona($sTextoAnalizar);
        } else {
            $sSexo = $sTextoAnalizar;
        }

        //Comprueba a partir de la letra si es Mujer o Varon
        switch ($sSexo) {
            //Mujer - Femenino
            case 'M':
                if ($bIncluirArticulo) {
                    $sRespuesta = 'la ';
                }
                if ($bIncluirDoct) {
                    $sRespuesta .= $bAbreviado ? 'Dra. ' : 'Doctora ';
                }
                if ($bAbreviado) {
                    $sRespuesta .= $bAbreviado ? 'Dña.' : 'Doña';
                }

                return $sRespuesta;
            //Varon - Masculino
            case 'V':
                if ($bIncluirArticulo) {
                    $sRespuesta = 'el ';
                }
                if ($bIncluirDoct) {
                    $sRespuesta .= $bAbreviado ? 'Dr. ' : 'Doctor ';
                }
                if ($bAbreviado) {
                    $sRespuesta .= $bAbreviado ? 'D.' : 'Don';
                }

                return $sRespuesta;


            //Sino se puede determinar
            default:

                if ($bAbreviado) {
                    return ($bIncluirArticulo ? 'el/la ' : '') . ($bIncluirDoct ? 'Dr./Dra. ' : '') . 'D./Dña.';
                } else {
                    return ($bIncluirArticulo ? 'el/la ' : '') . ($bIncluirDoct ? 'Doctor/Doctora ' : '') . 'Don/Doña';
                }
        }
    }

    /**
     * Cambia el genero de una palabra dependiendo del sexo que se le pase
     *  ejemplo:
     *      $sTexto=alumna, $sSexo=V, return = alumno
     *      $sTexto=alumnos, $sSexo=M, return = alumnas
     *      $sTexto=Directora, $sSexo=V  return = Director
     *      $sTexto=Director, $sSexo=M  return = Directora
     * 
     * @param string $sTexto Texto a cambiar, ejemeplo alumna [M] <=> alumno [V] 
     * @param string $sSexo [M|V|?] Mujer | Varon |No se encontro el Documento indicado
     * @return string
     */
    public static function textoGenero($sTexto, $sSexo) {

        switch ($sSexo) {

            case 'M':
                //Terminados en or
                if (preg_match('/or$/', $sTexto)) {
                    return preg_replace('/(or)$/', 'ora', $sTexto);
                }
                //Singular
                if (preg_match('/o$/', $sTexto)) {
                    return preg_replace('/(o)$/', 'a', $sTexto);
                }
                //Plurar
                if (preg_match('/os$/', $sTexto)) {
                    return preg_replace('/(os)$/', 'as', $sTexto);
                }


                break;
            case 'V':
                //Terminados en ora
                if (preg_match('/ora$/', $sTexto)) {
                    return preg_replace('/(ora)$/', 'or', $sTexto);
                }
                //Singular
                if (preg_match('/a$/', $sTexto)) {
                    return preg_replace('/(a)$/', 'o', $sTexto);
                }
                //Plurar
                if (preg_match('/as$/', $sTexto)) {
                    return preg_replace('/(as)$/', 'os', $sTexto);
                }

                break;
        }
        //Si no es necesario cambiar el texto se devuelve tal cual
        return $sTexto;
    }

    /**
     * Comprueba la fecha pasada por parametro para devolver un formato de fecha
     *  especifico, para ello comprueba que la fecha sea [DD?MM?YYYY] o [YYYY?MM?DD] y devuelve
     *  YYYYMMDD
     *
     * @param string $sFecha
     * @return string tipo YYYYMMDD o el $sFecha pasado sin modificar
     */
    public static function cambiarFormatoFecha($sFecha) {

        //Si la fecha esta en formato [DD/MM/YYYY|DD-MM-YYYY] se retorna sin los separadores '\|-'
        if (preg_match('/^([3][0-1]|[0-2][0-9]).([0][0-9]|[1][0-2]).(\d{4})$/', $sFecha, $aMatches)) {
            return $aMatches[1] . $aMatches[2] . $aMatches[3];
        }
        if (preg_match('/^(\d{4}).([0][0-9]|[1][0-2]).([0-2][1-9]|[3][0-1])$/', $sFecha, $aMatches)) {
            return $aMatches[3] . $aMatches[2] . $aMatches[1];
        }

        //Se retorna sin modificaciones
        return $sFecha;
    }

    /**
     * Función que formatea una fecha para guardarla o recuperarla de la BBDD.
     * Sus parámetros de entrada son el $this utilizado en los getter y setter, y el parámetro de entrada de los setter.
     *
     * @param type $dThisFecha
     * @param type $dSetFecha
     * @return type
     */
    public static function formatearFecha($dThisFecha, $dSetFecha = null) {

        // Si $dSetFecha es nulo la función es llamada desde un getter.
        if (is_null($dSetFecha) && !is_null($dThisFecha)) {
            return $dThisFecha->format('d/m/Y');
        } else {
            if ($dSetFecha == false) { // En el set si el input se deja vacío, devuelve false.
                $dThisFecha = null;
            } else {
                if (is_string($dSetFecha)) {
                    $dThisFecha = \DateTime::createFromFormat('d/m/Y', $dSetFecha);
                }
            }
        }

        return $dThisFecha;
    }

    /**
     * Obtiene el dni correspondiente. El conectado o el suplantado si es que se está suplantando identidad
     *
     * @param \App\Service\Base\Suplantacion\SuplantadorIdentidadService $suplantadorIdentidadService
     * @return string|null
     */
    public static function obtenerDNI(Request $request, SuplantadorIdentidadService $suplantadorIdentidadService) {
        /**
         * Comprobamos si es una identidad suplantada o no, para obtener el DNI
         */
        if (Utilidades::esIdentidadSuplantada($suplantadorIdentidadService)) {
            $dni = Utilidades::getDNIUsuarioSuplantado($suplantadorIdentidadService);
        } else {
            $dni = Utilidades::getDNIUsuarioConectado($request);
        }

        return $dni;
    }

    /**
     * @param NULL $aDatos
     * @param      $mensajeError
     * @return bool
     * @throws \Exception
     */
    public static function guardarLogPruebasEnvioPortafirmas($aDatos = null, &$mensajeError) {

        $mensajeError = '';
        try {

            //Se comprueba si existe el directorio directorio temporal

            if (defined('DIRECTORIO_TEMPORAL') === true) {

                $file = fopen(DIRECTORIO_TEMPORAL . "/" . "LogPruebasEnvioPortafirmas.txt", "a");

                if ($mensajeError) {
                    fwrite($file, "Ha surgido el siguiente error: " . $mensajeError . PHP_EOL);
                }

                if ($aDatos) {
                    fwrite($file, $aDatos . PHP_EOL);
                }

                fclose($file);

                return DIRECTORIO_TEMPORAL;
            }

            return false;
        } catch (Exception $e) {
            $mensajeError = $e->getMessage();

            return false;
        }
    }

    /**
     * Elimina los acentos de la cadena pasada por parámetros
     *
     * @param $cadena
     * @return mixed
     */
    public static function quitarAcentos($cadena) {
        return self::eliminarTildesTexto($cadena);
    }

    /**
     * Elimina todos los caracteres tilde de un texto latino
     * @param string $sTexto
     * @return string
     */
    public static function eliminarTildesTexto($sTexto) {

        if (!preg_match('/[\x80-\xff]/', $sTexto)) {
            return $sTexto;
        }

        $chars = array(
            // Descomposicion Latin-1
            chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A',
            chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A',
            chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A',
            chr(195) . chr(135) => 'C', chr(195) . chr(136) => 'E',
            chr(195) . chr(137) => 'E', chr(195) . chr(138) => 'E',
            chr(195) . chr(139) => 'E', chr(195) . chr(140) => 'I',
            chr(195) . chr(141) => 'I', chr(195) . chr(142) => 'I',
            chr(195) . chr(143) => 'I', chr(195) . chr(145) => 'N',
            chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O',
            chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O',
            chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U',
            chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U',
            chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y',
            chr(195) . chr(159) => 's', chr(195) . chr(160) => 'a',
            chr(195) . chr(161) => 'a', chr(195) . chr(162) => 'a',
            chr(195) . chr(163) => 'a', chr(195) . chr(164) => 'a',
            chr(195) . chr(165) => 'a', chr(195) . chr(167) => 'c',
            chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e',
            chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e',
            chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i',
            chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i',
            chr(195) . chr(177) => 'n', chr(195) . chr(178) => 'o',
            chr(195) . chr(179) => 'o', chr(195) . chr(180) => 'o',
            chr(195) . chr(181) => 'o', chr(195) . chr(182) => 'o',
            chr(195) . chr(182) => 'o', chr(195) . chr(185) => 'u',
            chr(195) . chr(186) => 'u', chr(195) . chr(187) => 'u',
            chr(195) . chr(188) => 'u', chr(195) . chr(189) => 'y',
            chr(195) . chr(191) => 'y',
            // Descomposicion Latin Extended-A
            chr(196) . chr(128) => 'A', chr(196) . chr(129) => 'a',
            chr(196) . chr(130) => 'A', chr(196) . chr(131) => 'a',
            chr(196) . chr(132) => 'A', chr(196) . chr(133) => 'a',
            chr(196) . chr(134) => 'C', chr(196) . chr(135) => 'c',
            chr(196) . chr(136) => 'C', chr(196) . chr(137) => 'c',
            chr(196) . chr(138) => 'C', chr(196) . chr(139) => 'c',
            chr(196) . chr(140) => 'C', chr(196) . chr(141) => 'c',
            chr(196) . chr(142) => 'D', chr(196) . chr(143) => 'd',
            chr(196) . chr(144) => 'D', chr(196) . chr(145) => 'd',
            chr(196) . chr(146) => 'E', chr(196) . chr(147) => 'e',
            chr(196) . chr(148) => 'E', chr(196) . chr(149) => 'e',
            chr(196) . chr(150) => 'E', chr(196) . chr(151) => 'e',
            chr(196) . chr(152) => 'E', chr(196) . chr(153) => 'e',
            chr(196) . chr(154) => 'E', chr(196) . chr(155) => 'e',
            chr(196) . chr(156) => 'G', chr(196) . chr(157) => 'g',
            chr(196) . chr(158) => 'G', chr(196) . chr(159) => 'g',
            chr(196) . chr(160) => 'G', chr(196) . chr(161) => 'g',
            chr(196) . chr(162) => 'G', chr(196) . chr(163) => 'g',
            chr(196) . chr(164) => 'H', chr(196) . chr(165) => 'h',
            chr(196) . chr(166) => 'H', chr(196) . chr(167) => 'h',
            chr(196) . chr(168) => 'I', chr(196) . chr(169) => 'i',
            chr(196) . chr(170) => 'I', chr(196) . chr(171) => 'i',
            chr(196) . chr(172) => 'I', chr(196) . chr(173) => 'i',
            chr(196) . chr(174) => 'I', chr(196) . chr(175) => 'i',
            chr(196) . chr(176) => 'I', chr(196) . chr(177) => 'i',
            chr(196) . chr(178) => 'IJ', chr(196) . chr(179) => 'ij',
            chr(196) . chr(180) => 'J', chr(196) . chr(181) => 'j',
            chr(196) . chr(182) => 'K', chr(196) . chr(183) => 'k',
            chr(196) . chr(184) => 'k', chr(196) . chr(185) => 'L',
            chr(196) . chr(186) => 'l', chr(196) . chr(187) => 'L',
            chr(196) . chr(188) => 'l', chr(196) . chr(189) => 'L',
            chr(196) . chr(190) => 'l', chr(196) . chr(191) => 'L',
            chr(197) . chr(128) => 'l', chr(197) . chr(129) => 'L',
            chr(197) . chr(130) => 'l', chr(197) . chr(131) => 'N',
            chr(197) . chr(132) => 'n', chr(197) . chr(133) => 'N',
            chr(197) . chr(134) => 'n', chr(197) . chr(135) => 'N',
            chr(197) . chr(136) => 'n', chr(197) . chr(137) => 'N',
            chr(197) . chr(138) => 'n', chr(197) . chr(139) => 'N',
            chr(197) . chr(140) => 'O', chr(197) . chr(141) => 'o',
            chr(197) . chr(142) => 'O', chr(197) . chr(143) => 'o',
            chr(197) . chr(144) => 'O', chr(197) . chr(145) => 'o',
            chr(197) . chr(146) => 'OE', chr(197) . chr(147) => 'oe',
            chr(197) . chr(148) => 'R', chr(197) . chr(149) => 'r',
            chr(197) . chr(150) => 'R', chr(197) . chr(151) => 'r',
            chr(197) . chr(152) => 'R', chr(197) . chr(153) => 'r',
            chr(197) . chr(154) => 'S', chr(197) . chr(155) => 's',
            chr(197) . chr(156) => 'S', chr(197) . chr(157) => 's',
            chr(197) . chr(158) => 'S', chr(197) . chr(159) => 's',
            chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's',
            chr(197) . chr(162) => 'T', chr(197) . chr(163) => 't',
            chr(197) . chr(164) => 'T', chr(197) . chr(165) => 't',
            chr(197) . chr(166) => 'T', chr(197) . chr(167) => 't',
            chr(197) . chr(168) => 'U', chr(197) . chr(169) => 'u',
            chr(197) . chr(170) => 'U', chr(197) . chr(171) => 'u',
            chr(197) . chr(172) => 'U', chr(197) . chr(173) => 'u',
            chr(197) . chr(174) => 'U', chr(197) . chr(175) => 'u',
            chr(197) . chr(176) => 'U', chr(197) . chr(177) => 'u',
            chr(197) . chr(178) => 'U', chr(197) . chr(179) => 'u',
            chr(197) . chr(180) => 'W', chr(197) . chr(181) => 'w',
            chr(197) . chr(182) => 'Y', chr(197) . chr(183) => 'y',
            chr(197) . chr(184) => 'Y', chr(197) . chr(185) => 'Z',
            chr(197) . chr(186) => 'z', chr(197) . chr(187) => 'Z',
            chr(197) . chr(188) => 'z', chr(197) . chr(189) => 'Z',
            chr(197) . chr(190) => 'z', chr(197) . chr(191) => 's'
        );

        $sTextoTratado = strtr($sTexto, $chars);

        return $sTextoTratado;
    }

    /**
     * Convierte un separador de fecha en otro, por defecto '/' => '.'
     * 
     * @param string$sFecha
     * @param string $separadorActual
     * @param string $separadorNuevo
     * @throws \Exception
     */
    public static function convertirSeparadoresFecha($sFecha, $separadorActual = '/', $separadorNuevo = '-') {

        try {
            if ($separadorActual === '/' || $separadorActual === '.') {
                $separadorActual = '\\' . $separadorActual;
            }
            return preg_replace('/' . $separadorActual . '/', $separadorNuevo, $sFecha);
        } catch (Exception $ex) {
            throw Utilidades::controlarError($ex);
        }
    }

    /**
     * Convierte el curso academico corto en largo 202021 => 2020/21, 
     *  el separador se puede elegir como segundo parametro
     * 
     * @param string $sCursoAcademico
     * @param string $separador elegido por defecto '-'
     * @return string
     * @throws \Exception
     */
    public static function convertirCursoAcademicoLargo($sCursoAcademico, $separador = '-') {

        //Comprobamos si mide seis exactamente y si es un conjunto de digitos de principio a fin
        if (strlen($sCursoAcademico) === 6 && preg_match('/^\d+$/', $sCursoAcademico)) {
            //Separamos el conjunto de numeros en {4} y {2} los volvemos a juntar con el separador en medio
            return preg_replace('/^(\d{4})(\d{2})$/', '\1' . $separador . '\2', $sCursoAcademico);
        }
        //En caso de que mida mas o menos de seis o tenga caracteres no digitos se lanza Throw
        elseif (strlen($sCursoAcademico) > 6 || strlen($sCursoAcademico) < 6 || !preg_match('/^\d+$/', $sCursoAcademico)) {
            throw Utilidades::controlarError('Error en la longitud del curso académico, debe ser del estilo "201314", se ha introducido [' . $sCursoAcademico . ']');
        }

        return $sCursoAcademico;
    }

    /**
     * Devuelve si se esta ejecutando el script en un servidor de ULPGC, solo
     *  en desarrollo
     * 
     * @return boolean
     */
    public static function depurarESServidorSI() {
        if (self::esDesarrollo()) {
            if (defined('depuracionIP') && isset(depuracionIP['servidoresSI'])) {
                if (preg_match('/^' . depuracionIP['servidoresSI'] . '/', self::getServidorIP())) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

}
