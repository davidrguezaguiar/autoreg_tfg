<?php

/**
 * Clase necesaria que implementa funcionalidades que por defecto Doctrine no proporciona.
 * (Gestionar cursores..)
 *
 * TODO: Parametrizar en obtener argumentos cuando no se obtienen, es decir, diferenciar que no tiene porque es
 * un procedimiento sin parámetros o bien que no existen para el esquema/pack/procedimiento-funcion pasados.
 * TODO: Intentar que la llamada al método desde el controlador pueda ser tanto en mayúscula como en minúscula
 */

namespace App\DBAL;

use \Exception;
use App\Lib\Base\Utilidades;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\OCI8\OCI8Statement;
use Doctrine\DBAL\Driver\OCI8\OCI8Arguments;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class OCI8StatementUlpgc extends OCI8Statement{

    /** @static string FUNCION valor que devuelve oracle para un procedimiento almacenado de tipo funcion
     * @see esFuncion()
     */
    const FUNCION = '8';
    const PROC = '7';
    const _FUNCION = '9';
    const DECIMALES_METRICAS = 3;
    const PARAMETRO_ENTRADA = 'IN';
    const PARAMETRO_SALIDA = 'OUT';
    const PARAMETRO_ENTRADA_SALIDA = 'IN_OUT';
    const TAMANIO_MAXIMO_PARAM = 4000;
    const TAMANIO_MAXIMO_PARAM_CADENAS = 4000;
    const MODO_AUTO_COMMIT = OCI_COMMIT_ON_SUCCESS;
    const MODO_NO_COMMIT = OCI_DEFAULT;
    const FICHERO_LOG_ORACLE = 'oracle_errors.log';
    //const SERVIDOR_ULPGC = '';

    /** Constantes para identificar las metricas */
    const OBTENER_ARGUMENTOS = 'Tiempo invertido en obtener los argumentos del procedimiento almacenado';
    const COMPROBAR_ES_FUNCION = 'Tiempo invertido en comprobar si el procedimiento almacenado es o no una funcion';
    const EJECUTAR_CURSOR = 'Tiempo invertido en ejecutar el cursor';
    const EJECUTAR_PROCEDIMIENTO = 'Tiempo invertido en ejecutar el procedimiento almacenado';
    const TIEMPO_TOTAL = 'Tiempo total invertido (Incluye todos los tiempos, no tiene por que ser la suma de las demas metricas)';

    /** @var string nombre del procedimiento almacenado (o funcion) */
    public $procAlmacenado;

    /** @var string esquema o usuario de la base de datos */
    protected $esquema;

    /** @var string paquete donde reside el procedimiento almacenado */
    protected $paquete;

    /** @var array args argumentos que recibe el procedimiento almacenado */
    protected $args;

    /** @var resource conexion hacia la base de datos */
    public $conexion;

    /** @var array variables de salida de un procedimiento o funcion */
    protected $aSalida;

    /** @var boolean prepared indica si el procedimiento esta listo para ser ejecutado o no */
    protected $prepared;

    /** @var int indica si la primitiva oci_execute debe comitear la sentencia o no */
    protected $modoCommit;

    /** @var string informa la conexion hacia la bbdd */
    protected $cadenaConexion;

    /** @var oci_statement sentencia que va a ser ejecutada */
    protected $statement;

    /** @var string usuario para conectar a la bbdd */
    protected $usuario;

    /** @var string clave para conectar a la bbdd */
    protected $clave;

    /** @var array aErrores errores producidos durante la ejecucion de sentencias */
    protected $aErrores;

    /** @var array aMetricas metricas temporales producidas durante la ejecucion de sentencias */
    protected $aMetricas;

    /** @var int Indica la marca temporal desde la cual empieza a prepararse la ejecucion
     *  del procedimiento almacenado */
    protected $marcaTemporal1;

    /** @var int Indica la marca temporal donde acaba la ejecucion
     *  del procedimiento almacenado */
    protected $marcaTemporal2;
    protected $aParametrosSesionOracle;

    /** Alberga el sql ejecutado funcion/procedimiento almacenado */
    protected $sqlProcedimientoAlmacenado;

    /** Argumentos recibidos por la funcion/procedimiento almacenado */
    protected $aArgumentosReales;
    protected $oLoggerOracle;

    /** Permite registrar los errores producidos en oracle */
    protected $sUltimoErrorProducido;

    /** Ultimo error producido */
    protected $Logger;

    /**
     * Creates a new OCI8Statement that uses the given connection handle and SQL statement.
     *
     * @param resource                                  $dbh       The connection handle.
     * @param string                                    $statement The SQL statement.
     * @param \Doctrine\DBAL\Driver\OCI8\OCI8ConnectionUlpgc $conn
     */
    public function __construct($dbh = null, $statement = null, OCI8ConnectionUlpgc $conn = null) {

        /** Se establece la conexion */
        parent::__construct($dbh, $statement, $conn);


        $this->setConexion($dbh);

        $this->aParametrosSesionOracle = array();
        if (is_null($conn->getLogger()) === FALSE) {
            $this->setLogger($conn->getLogger());
        }else{
            $this->setLogger(new \Monolog\Logger('ulpgc')); 
        }

        /** Statement llega con esquema->pack->proc Se separa */
        if (strpos($statement, '->')) {
            $objs = explode('->', $statement);
            $this->setEsquema(strtoupper($objs[0]));
            $this->setPaquete(strtoupper($objs[1]));
            $this->setProcedimientoAlmacenado(strtoupper($objs[2]));

            $this->preparar();
        }



        $this->oLoggerOracle = new Logger('oracleLogger');
        $errorHandle = new StreamHandler($this->obtenerRutaLogOracle(), 100);
        $this->oLoggerOracle->pushHandler($errorHandle);

        return $this;
    }

    /*     * *************************************************************************** */

    public function setProcedimientoAlmacenado($proc) {
        $this->procAlmacenado = $proc;
    }

/** end __setEsquema */
    /*     * *************************************************************************** */

    /**
     * preparar
     *
     * @param string procedimiento
     * */
    public function preparar() {

        try{
            
        
        $aArgumentos = $this->obtenerArgumentos();

        $this->setArgumentosFormales($aArgumentos[$this->procAlmacenado]['ARGUMENTOS']);


        $this->aSalida = array();
        $this->aMetricas = array();
        $this->aErrores = array();

        $this->setPrepared(true);

        /** Se crea dinamicamente el procedimiento almacenado dentro de la clase para que pueda ser invocado como un metodo */
        $func = $this->getFunction();
        $procedimiento = $this->procAlmacenado;
        $this->$procedimiento = $func;
        } catch (\Exception $ex) {
            $this->getLogger()->addError(Utilidades::controlarError($ex)->getMessage());
        }
    }

/** end __getEsquema */
    /*     * *************************************************************************** */

    protected function obtenerArgumentos() {
        $stid = oci_parse(
                $this->getConexion(), 'SELECT * FROM ALL_ARGUMENTS WHERE OWNER = :SCHEMA AND PACKAGE_NAME = :PACK
    			AND (((OBJECT_NAME = :PROC) AND (:PROC IS NOT NULL)) OR (:PROC IS NULL) ) ORDER BY POSITION');
        oci_bind_by_name($stid, ':SCHEMA', $this->esquema);
        oci_bind_by_name($stid, ':PACK', $this->paquete);
        oci_bind_by_name($stid, ':PROC', $this->procAlmacenado);

        oci_execute($stid);

        $aArgumentos = array();

        while (($row = oci_fetch_array($stid, OCI_ASSOC)) != false) {
            $aArgumentos[$row['OBJECT_NAME']]['ARGUMENTOS'][] = $row;
        }
        
        if ( count($aArgumentos) < 1 ){
            $this->getLogger()->addDebug( Utilidades::controlarError('Compruebe que el usuario que se conecta a la BD tiene permisos para acceder a ['.$this->esquema.'->'.$this->paquete.'->'.$this->procAlmacenado.']')->getMessage(),
                    array('lastSQL' => $this->getSqlUltimoProcedimiento()));
        }
        
        return $aArgumentos;
    }

/** end __setPaquete */
    /*     * *************************************************************************** */

    public function obtenerConexionResource() {
        return $this->conexion;
    }

    protected function getConexion() {
        return $this->conexion;
    }

/** end __getPaquete */
    /*     * *************************************************************************** */

    protected function setConexion($conn) {
        $this->conexion = $conn;
    }

/** end __setProcedimientoAlmacenado */
    /*     * *************************************************************************** */

    protected function setArgumentosFormales($args) {
        $this->args = array();
        if ($args) {
            foreach ($args as $argumento) {
                $oArgumento = new OCI8Arguments($this->arrayToObject($argumento));
                if ($oArgumento->getNombre()) {
                    $this->args[$oArgumento->getNombre()] = $oArgumento;
                }
            }
        }
    }

/** end __getProcedimientoAlmacenado */
    /*     * *************************************************************************** */

    /**
     * arrayToObject Convierte un array en un stdClass
     *
     * @param mixed var
     *
     * @return stdClass
     * */
    static function arrayToObject($var) {
        if (is_array($var)) {
            /*
             * Return array converted to object
             * Using __FUNCTION__ (Magic constant)
             * for recursive call
             */
            return json_decode(json_encode($var));
        } else {
            // Return object
            return $var;
        }
    }

/** end __setConexion */
    /*     * *****************************************************************************
     *
     *                              METODOS PRIVADOS
     *
     * **************************************************************************** */

    protected function getFunction() {
        return function () {

            try {
                if (!$this->getPrepared()) {
                    throw new \Symfony\Component\Config\Definition\Exception\Exception('Debe invocar primero al metodo prepare($nombreProcedimientoAlmacenado)');
                }
            } catch (Exception $exc) {
                echo $exc->getTraceAsString();
            }

            $aArgumentosReales = func_get_args();

            $this->setArgumentosReales($aArgumentosReales);
            $esFuncion = '';
            $sql = $this->obtenerSQL($esFuncion);
            $this->setSqlProcedimientoAlmacenado($sql);

            try {
                $nMarca1 = $this->getMarcaTemporal();

                $this->setStatement(oci_parse($this->getConexion(), $sql));

                $contadorArgumentosReales = 0;

                foreach ($this->getArgumentosFormales() as $argumento) {

                    $argumento->setConexion($this->getConexion());

                    /** Se invoca con el fin de crear el cursor, blob o clob
                     *  el objeto internamente comprueba que lo sean, caso contrario
                     *  no se hace nada y la instruccion es inocua para el resto de tipos */
                    $argumento->obtenerDescriptor();

                    if (($aArgumentosReales[$contadorArgumentosReales]) || ($aArgumentosReales[$contadorArgumentosReales] == 0)) {
                        $argumento->setValor($aArgumentosReales[$contadorArgumentosReales]);

                        if (($argumento->esLob()) && ($argumento->esSalida())) {
                            $argumento->setSiGrabarLob(true);
                        }
                    }


                    $argumento->bind($this->statement);

                    /** Si la funcion ha sido llamada con un valor para este argumento
                     *  lo habitual es que ese argumento haya que escribirlo hacia oracle
                     *  caso de que el argumento sea un clob/blob.
                     *  El objeto internamente se encarga de comprobar el tipo y de
                     *  manejar las primitivas php necesarias */
                    if ($aArgumentosReales[$contadorArgumentosReales]) {
                        $argumento->setEscribirDescriptor(true);
                        $argumento->setLeerDescriptor(false);
                    } else {
                        $argumento->setEscribirDescriptor(false);
                        $argumento->setLeerDescriptor(true);
                    }


                    /** Volvemos a grabar el argumento en el array para conservar
                     *  los cambios, por si el foreach trabaja con valores
                     *  en lugar de referencias */
                    $this->args[$argumento->getNombre()] = $argumento;

                    $contadorArgumentosReales++;
                }
                /** end __foreach($aArgumentos) */
                $resultado = null;

                if ($esFuncion) {
                    oci_bind_by_name($this->getStatement(), ':res', $resultado, self::TAMANIO_MAXIMO_PARAM);
                }
                /** Formato de fecha DD/MM/YYYY */
                $this->setDateFormat();

                /** Se ejecuta el procedimiento o funcion */
                $this->ejecutarSentencia($this->statement, false);

                /** Se graban o escriben los lobs de los argumentos (si los hubiere) */
                $this->manejarLobs();

                /** Formato de fecha YYYY-MM-DD (necesario para Doctrine) */
                $this->unsetDateFormat();

                /** Se graban las metricas */
                $nMarca2 = $this->getMarcaTemporal();
                $this->addMetrica(self::EJECUTAR_PROCEDIMIENTO, $nMarca2 - $nMarca1);

                /** Se establece una variable de salida por cada argumento formal
                 *  que sea de salida o bien de entrada salida */
                $this->setVariablesSalida();

                /** Si es una funcion devolvemos el resultado */
                if (!$esFuncion) {

                    /** Comprobamos si habia algun cursor de entre los argumentos formales
                     *  del procedimiento almacenado */
                    $oArgumentoFormalCursor = $this->obtenerCursor();

                    if ($oArgumentoFormalCursor) {
                        if (is_resource($oArgumentoFormalCursor->getDescriptor())) {
                            $nMarca1 = $this->getMarcaTemporal();
                            $this->ejecutarSentencia($oArgumentoFormalCursor->getDescriptor());
                            $nMarca2 = $this->getMarcaTemporal();

                            $this->addMetrica(self::EJECUTAR_CURSOR, $nMarca2 - $nMarca1);

                            /** Si habia algun cursor como parametro lo devolvemos como resultado
                             *  de invocar el procedimiento almacenado */
                            $resultado = $oArgumentoFormalCursor->getDescriptor();
                        }
                    }
                }

                $this->marcaTemporal2 = $this->getMarcaTemporal();

                return $resultado;
            } /** end __try */ catch (\Exception $ex) {
                throw new \Exception($ex);
            }
        };
        /** end __return function()  */
    }

/** end __getConexion() */
    /*     * *************************************************************************** */

    public function getPrepared() {
        return $this->prepared;
    }

/** end __setArgumentosFormales */
    /*     * *************************************************************************** */

    public function setPrepared($prepared) {
        $this->prepared = $prepared;
    }

/** end __getArgumentosFormales */
    /*     * *************************************************************************** */

    /**
     * obtenerSQL
     *
     * @param boolean esFuncion Indica si la sql devuelta ejecuta una funcion (TRUE) o un procedimiento
     *                almacenado (FALSE)
     *
     * @return string SQL del procedimiento almacenado
     * */
    protected function obtenerSQL(&$esFuncion) {
        $nMarca1 = $this->getMarcaTemporal();
        $esFuncion = $this->esFuncion();
        $nMarca2 = $this->getMarcaTemporal();
        $this->addMetrica(self::COMPROBAR_ES_FUNCION, $nMarca2 - $nMarca1);

        $alterSession = $this->prepararAlterSession();

        $sComienzoSentencia = $esFuncion ? 'BEGIN ' . $alterSession . ' :res:=' : 'BEGIN ' . $alterSession . ' ';
        $sql = $sComienzoSentencia . $this->esquema.'.'.$this->paquete . '.' . $this->procAlmacenado . '(';

        $this->getLogger()->addDebug(__FUNCTION__.':'.__LINE__.'>Preparando SQL' );
        
        if ($this->getArgumentosFormales()) {
            $this->getLogger()->addDebug(__FUNCTION__.':'.__LINE__.'>Preparando SQL' );
            foreach ($this->getArgumentosFormales() as $argumento) {
                if ($argumento->getNombre()) {
                    $sql .= $argumento->getNombreBind() . ',';
                }
            }
        }

        $sql = trim($sql, ',');
        $sql .= '); END;';

        $this->getLogger()->addDebug(__FUNCTION__.':'.__LINE__.'>Completado SQL', array('sql' => $sql) );
        return $sql;
    }

/** end __getVariablesSalida */
    /*     * *************************************************************************** */

    /**
     * getMarcaTemporal
     *
     * @return float
     */
    protected function getMarcaTemporal() {
        list($usec, $sec) = explode(" ", microtime());
        $nMarca = ((float) $usec + (float) $sec);

        return round($nMarca, self::DECIMALES_METRICAS, PHP_ROUND_HALF_UP);
    }

/** end __setVariablesSalida */
    /*     * *************************************************************************** */

    /**
     * esFuncion Indica si el procedimiento almacenado es una funcion o un procedimiento
     * */
    protected function esFuncion() {
        $esFuncion = false;

        $part2 = '';
        $tipoLlamada = $this->obtenerTipoLlamada($part2);
        if ($tipoLlamada == self::FUNCION) {
            $esFuncion = true;
        } elseif (($tipoLlamada == self::_FUNCION) and ($part2 != null)) {
            try {
                $stid = oci_parse($this->getConexion(),
                        "SELECT COUNT(*) C FROM ALL_ARGUMENTS WHERE OWNER = :SCHEMA AND PACKAGE_NAME = :PACK AND OBJECT_NAME = :PROC AND POSITION = 0");
                oci_bind_by_name($stid, ':SCHEMA', $this->esquema, -1, SQLT_CHR);
                oci_bind_by_name($stid, ':PACK', $this->paquete, -1, SQLT_CHR);
                oci_bind_by_name($stid, ':PROC', $this->procAlmacenado, -1, SQLT_CHR);
                oci_execute($stid);
                if (($row = oci_fetch_array($stid, OCI_BOTH)) != false) {
                    $esFuncion = $row['C'] > 0;
                }
            } catch (\Exception $exception) {
                $this->getLogger()->addError(Utilidades::controlarError($exception)->getMessage(), array( 'SQL' => "SELECT COUNT(*) C FROM ALL_ARGUMENTS WHERE OWNER = :SCHEMA AND PACKAGE_NAME = :PACK AND OBJECT_NAME = :PROC AND POSITION = 0",
                                                                                                             'Parametros' => ':SCHEMA =>'.$this->esquema . ' :PACK =>'. $this->paquete . ':PROC =>'. $this->procAlmacenado,
                                                                                                             'Ultimo SQL: ' => $this->getSqlUltimoProcedimiento()));
                throw Utilidades::controlarError($exception);
            }
        }

        return $esFuncion;
    }

/** end __addVariableSalida */
    /*     * *************************************************************************** */

    /**
     * obtenerTipoLlamada Oracle indica con un self::FUNCION si el procedimiento almacenado es una funcion
     *
     * @param mixed part2 Este parametro a null junto con un resultado self::_FUNCION devuelto por Oracle
     *              tambien indican que el procedimiento almacenado es una funcion
     * */
    protected function obtenerTipoLlamada(&$part2) {
        $nombreObjeto = $this->getEsquema() . '.' . $this->getPaquete() . '.' . $this->getProcedimientoAlmacenado();
        $stid = oci_parse($this->getConexion(),
                "BEGIN DBMS_UTILITY.name_resolve(:name,1,:schema,:part1,:part2,:dblink,:part1_type,:object_number); END;");
        $tipoLlamada = '';
        $esquema = '';
        $part1 = '';
        $part2 = '';
        $dblink = '';
        $object_number = '';

        oci_bind_by_name($stid, ':name', $nombreObjeto, -1, SQLT_CHR);
        oci_bind_by_name($stid, ':schema', $esquema, 1000, SQLT_CHR);
        oci_bind_by_name($stid, ':part1', $part1, 1000, SQLT_CHR);
        oci_bind_by_name($stid, ':part2', $part2, 1000, SQLT_CHR);
        oci_bind_by_name($stid, ':dblink', $dblink, 1000, SQLT_CHR);
        oci_bind_by_name($stid, ':part1_type', $tipoLlamada, -1, SQLT_INT);
        oci_bind_by_name($stid, ':object_number', $object_number, -1, SQLT_INT);
        oci_execute($stid);

        return $tipoLlamada;
    }

/** end __prepared */
    /*     * *************************************************************************** */

    /**
     * @return mixed
     */
    public function getParametrosSesionOracle() {
        return $this->aParametrosSesionOracle;
    }

    /**
     * @param mixed $aParametrosSesion
     */
    public function setParametrosSesionOracle($aParametrosSesion) {
        $this->aParametrosSesionOracle = $aParametrosSesion;
    }

    public function getEsquema() {
        return $this->esquema;
    }

/** end __prepared */
    /*     * *****************************************************************************
     *
     *                              GETTERS Y SETTERS
     *
     * **************************************************************************** */

    public function setEsquema($esquema) {
        $this->esquema = $esquema;
    }

/** end __setModoCommit */
    /*     * *************************************************************************** */

    public function getPaquete() {
        return $this->paquete;
    }

/** end __getModoCommit */
    /*     * *************************************************************************** */

    public function setPaquete($pack) {
        $this->paquete = $pack;
    }

/** end __setCadenaConexion */
    /*     * *************************************************************************** */

    public function getProcedimientoAlmacenado() {
        return $this->procAlmacenado;
    }

/** end __getCadenaConexion */
    /*     * *************************************************************************** */

    protected function depurar($var) {
        echo "<pre>";
        print_r($var);
        echo "<pre>";
    }

/** end __setUsuario */
    /*     * *************************************************************************** */

    /**
     * addMetrica
     *
     * @param string descripcion
     * @param float  metrica
     * */
    protected function addMetrica($descripcion, $metrica) {
        $metrica = round($metrica, self::DECIMALES_METRICAS, PHP_ROUND_HALF_UP);
        $this->aMetricas[] = array('DESCRIPCION' => $descripcion, 'METRICA' => $metrica);
    }

/** end __getUsuario */
    /*     * *************************************************************************** */

    protected function getArgumentosFormales() {
        $this->getLogger()->addDebug(__FUNCTION__.':'.__LINE__.'>Argumentos SQL', $this->args );
        return $this->args;
    }

/** end __setClave */
    /*     * *************************************************************************** */

    public function getStatement() {
        return $this->statement;
    }

/** end __getClave */
    /*     * *************************************************************************** */

    public function setStatement($stmt) {
        $this->statement = $stmt;
    }

/** end __setStatement */
    /*     * *************************************************************************** */

    public function prepararAlterSession() {

        $sqlExecuteImmediate = '';
        //Formatos por defecto
        $aFormatos = array(
            'NLS_TIME_FORMAT' => "HH24:MI:SS",
            'NLS_DATE_FORMAT' => "DD/MM/YYYY HH24:MI:SS",
            'NLS_TIMESTAMP_FORMAT' => "DD/MM/YYYY HH24:MI:SS",
            'NLS_TIMESTAMP_TZ_FORMAT' => "DD/MM/YYYY HH24:MI:SS TZH:TZM",
            'NLS_NUMERIC_CHARACTERS' => ".,",
        );

        //Si tiene formatos a poner se cambian los que estan por defecto por los que vienen en parametros session oracle  
        foreach ($this->aParametrosSesionOracle as $key => $value) {
            $aFormatos[$key] = $value;
        }


        $sqlExecuteImmediate = "";
        if (count($aFormatos)) {
            array_change_key_case($aFormatos, \CASE_UPPER);
            foreach ($aFormatos as $option => $value) {

                $sqlExecuteImmediate .= "EXECUTE IMMEDIATE 'ALTER SESSION SET " . $option . " = ''" . $value . "'''; ";
            }
        }

        return $sqlExecuteImmediate;
    }

    /**
     * Establece variables de sesion oracle
     *
     */
    public function setDateFormat() {

        $aFormatos = array(
            'NLS_TIME_FORMAT' => "HH24:MI:SS",
            'NLS_DATE_FORMAT' => "DD/MM/YYYY HH24:MI:SS",
            'NLS_TIMESTAMP_FORMAT' => "DD/MM/YYYY HH24:MI:SS",
            'NLS_TIMESTAMP_TZ_FORMAT' => "DD/MM/YYYY HH24:MI:SS TZH:TZM",
            'NLS_NUMERIC_CHARACTERS' => ".,",
        );


        /**
         * Se inyectan los parametros de sesion oracle que pudieran haber
         */
        // if (!empty($this->aParametrosSesionOracle)) {
        foreach ($this->aParametrosSesionOracle as $key => $value) {
            $aFormatos[$key] = $value;
        }
        //}

        $sql = "";
        if (count($aFormatos)) {
            array_change_key_case($aFormatos, \CASE_UPPER);
            $vars = array();
            foreach ($aFormatos as $option => $value) {
                $vars[] = $option . " = '" . $value . "'";
            }
            $sql = "ALTER SESSION SET " . implode(" ", $vars);
        }
        $stmt = (oci_parse($this->getConexion(), $sql));
        $this->ejecutarSentencia($stmt);
    }

/** end __getStatement */
    /*     * *****************************************************************************
     *
     *                              METODOS PUBLICOS
     *
     * **************************************************************************** */

    protected function ejecutarSentencia($statement, $bAutoCommit = true) {
        $this->sUltimoErrorProducido = null;

        /** Compone una cadena de error producida  al ejecutar un procedimiento/funcion almacenada */
        $fnComposeErrorMessage = function($sError) {
            $aArgumentosReales = $this->getArgumentosReales();
            if (!empty($aArgumentosReales)) {
                $sListaArgumentos = implode(", ", $this->getArgumentosReales());
                $sListaArgumentos = "(" . $this->composeLogParameterList($sListaArgumentos) . ")";
            } else {
                $sListaArgumentos = "()";
            }

            $sMensajeError = 'Error al ejecutar ' . $this->getPaquete() . '.' . $this->getProcedimientoAlmacenado() . $this->composeLogParameterList($sListaArgumentos) . ' : ' . $sError;

            return $sMensajeError;
        };

        try {

            if (!is_resource($statement)) {
                throw new \Exception('No es una sentencia valida ');
            } else {
                if ($bAutoCommit) {
                    $bResultado = oci_execute($statement);
                } else {
                    $bResultado = oci_execute($statement, OCI_NO_AUTO_COMMIT);
                }
                if ($bResultado === false) {
                    $aError = oci_error($statement);
                    $this->escribirErrorOracle($aError['message']);
                    $this->addError(oci_error($statement));
                    throw new \Exception($aError['message']);
                }
            }
        } catch (DBALException $exc) {
            $sError = Utilidades::comprobarErrorOracle($exc);
            $this->getLogger()->addError($sError, array('UltimoSQL' => $this->getSqlUltimoProcedimiento(),
                'ProcedimientoSQL' => $this->getSqlProcedimientoAlmacenado()));
            $this->escribirErrorOracle($exc->getMessage());
            throw new \Exception($sError);
        } catch (\Exception $exc) {

            $sError = Utilidades::comprobarErrorOracle($exc);
            $this->getLogger()->addError($sError, array('UltimoSQL' => $this->getSqlUltimoProcedimiento(),
                'ProcedimientoSQL' => $this->getSqlProcedimientoAlmacenado()));
            $this->escribirErrorOracle($exc->getMessage());
            throw new \Exception($sError);
        }
    }

/** end __commit */

    /**
     * addError
     *
     * @param string error
     * */
    protected function addError($error) {
        $this->aErrores[] = $error;
    }

/** end __prepare */
    /*     * *************************************************************************** */

    /**
     * Establece el formato fecha a YYYY-MM-DD
     */
    public function unsetDateFormat() {
        $aFormatos = array(
            'NLS_TIME_FORMAT' => "HH24:MI:SS",
            'NLS_DATE_FORMAT' => "YYYY-MM-DD HH24:MI:SS",
            'NLS_TIMESTAMP_FORMAT' => "YYYY-MM-DD HH24:MI:SS",
            'NLS_TIMESTAMP_TZ_FORMAT' => "YYYY-MM-DD HH24:MI:SS TZH:TZM",
            'NLS_NUMERIC_CHARACTERS' => ".,",
        );

        $sql = "";
        if (count($aFormatos)) {
            array_change_key_case($aFormatos, \CASE_UPPER);
            $vars = array();
            foreach ($aFormatos as $option => $value) {
                $vars[] = $option . " = '" . $value . "'";
            }
            $sql = "ALTER SESSION SET " . implode(" ", $vars);
        }
        $stmt = (oci_parse($this->getConexion(), $sql));
        $this->ejecutarSentencia($stmt);
    }

/** end __Fetch */
    /*     * *************************************************************************** */

    /**
     * manejarLobs
     * Para cada argumento de tipo lob, escribe o lee de cada lob su contenido
     * hacia o desde la bbdd. La clase bdArgumento contiene la logica necesaria
     * para saber cuando hacer una cosa u otra.
     * Este metodo simplemente recorre cada argumento del procedimiento almacenado
     * y busca los lobs y lanza los metodos escribir y leer.
     */
    protected function manejarLobs() {
        if ($this->getArgumentosFormales()) {

            foreach ($this->getArgumentosFormales() as $argumento) {

                if ($argumento->esLob()) {

                    try {
                        $argumento->escribirDescriptor();

                        $argumento->leerDescriptor();
                    } catch (Exception $ex) {

                        throw new \Exception($ex->getMessage());
                    }
                }
            }
        }
    }

    /*     * *************************************************************************** */

    /**
     * setVariablesSalida
     * Establece una variable de salida por cada argumento formal que sea bien
     * de salida, bien de entrada/salida
     */
    protected function setVariablesSalida() {
        $this->aSalida = array();

        if ($this->getArgumentosFormales()) {
            foreach ($this->getArgumentosFormales() as $argumento) {
                if (($argumento->esSalida()) && (!$argumento->esCursor()) && ($argumento->getNombre())) {
                    $this->addVariableSalida($argumento->getNombre(), $argumento->getValor());
                }
            }
        }
    }

/** end __rollback */
    /*     * *************************************************************************** */

    /**
     * addVariableSalida
     *
     * @param string nombre Nombre que identifica a la variable
     *               Debe casar con el nombre del parametro formal que la produce
     * @param mixed  valor Valor que produce el procedimiento almacenado para esta
     *               variable
     */
    protected function addVariableSalida($nombre, $valor) {
        $this->aSalida[$nombre] = $valor;
    }

/** end __getErrores */
    /*     * *************************************************************************** */

    /**
     * obtenerCursor
     * Comprueba de entre todos los argumentos formales del procedimiento almacenado
     * si alguno de ellos es un cursor, en cuyo caso lo devolveria como objeto bdArgumento
     *
     * @return bdArgumento
     */
    protected function obtenerCursor() {
        $resultado = null;
        foreach ($this->getArgumentosFormales() as $argumento) {
            if ($argumento->getTipo() == OCI8Arguments::TIPO_CURSOR) {
                $resultado = $argumento;
                break;
            }
        }

        return $resultado;
    }

/** end __getMetricas */
    /*     * *************************************************************************** */

    public function getVariablesSalida() {
        return $this->aSalida;
    }

/** end __esFuncion */
    /*     * *************************************************************************** */

    public function getModoCommit() {
        return $this->modoCommit;
    }

/** end __obtenerTipoLlamada */
    /*     * *************************************************************************** */

    public function setModoCommit($modo) {
        $this->modoCommit = $modo;
    }

/** end __obtenerCursor */
    /*     * *************************************************************************** */

    public function getCadenaConexion() {
        return $this->cadenaConexion;
    }

/** end __obtenerSQL */
    /*     * *************************************************************************** */

    public function setCadenaConexion($cadena) {
        $this->cadenaConexion = $cadena;
    }

/** end __obtenerArgumentos */
    /*     * *************************************************************************** */

    public function getUsuario() {
        return $this->usuario;
    }

/** end __arrayToObject */
    /*     * *************************************************************************** */

    public function setUsuario($user) {
        $this->usuario = $user;
    }

/** end __ejecutarSentencia */
    /*     * *************************************************************************** */

    public function getClave() {
        return $this->clave;
    }

/** end __manejarLobs */
    /*     * *************************************************************************** */

    public function setClave($clave) {
        $this->clave = $clave;
    }

/** end __addError */
    /*     * *************************************************************************** */

    /**
     * Fetch Devuelve una fila del cursor cada vez que se invoca
     *
     * @param resource cursor
     *
     * @return array
     * @throws \Exception
     * */
    public function fetchCursor($cursor) {
        try {
            $row = null;
            if (is_resource($cursor)) {
                if (($row = oci_fetch_array($cursor, OCI_ASSOC + OCI_RETURN_NULLS)) != false) {
                    foreach ($row as $key => $value) {
                        oci_field_type($cursor, $key);
                        if (is_object($value)) {
                            $row[$key] = $value->load();
                        }
                    }
                }
            }

            return $row;
        } catch (\Exception $e) {
            $sError = Utilidades::comprobarErrorOracle($e);
            $sError = $this->getPaquete() . '.' . $this->getProcedimientoAlmacenado() . ': ' . $sError;
            $this->escribirErrorOracle($sError);
            throw new \Exception($sError);
        }
    }

/** end __addMetrica */
    /*     * *************************************************************************** */

    public function commit() {
        $bResultado = oci_commit($this->getConexion());

        return $bResultado;
    }

/** end __getMarcaTemporal */
    /*     * *************************************************************************** */

    public function rollback() {
        $bResultado = oci_rollback($this->getConexion());

        return $bResultado;
    }

    /*     * ************************************************************************ */

    /**
     * getErrores
     *
     * @return array
     * */
    public function getErrores() {
        return $this->aErrores;
    }

    /*     * ************************************************************************ */

    /**
     * getMetricas
     *
     * @return array
     * */
    public function getMetricas() {
        if ($this->aMetricas) {
            $this->addMetrica(self::TIEMPO_TOTAL, $this->marcaTemporal2 - $this->marcaTemporal1);
        }

        return $this->aMetricas;
    }

    /*     * ************************************************************************ */

    /**
     * Realiza la llamada a un método que se ha creado como anónimo.
     * Serán los nombres de procedimientos y funciones que se creen como métodos dinámicamente.
     *
     * @param string $method . Nombre del método
     * @param array  $args   . Parámetros del método
     *
     * @throws \Exception
     */
    public function __call($method, $args) {
        
        if (isset($this->$method)) {
            $func = $this->$method;

            return call_user_func_array($func, $args);
        } else {
            
            throw new \Exception('No se ha encontrado o no existe el método ' . $method);
        }
    }

    /**
     * @return mixed
     */
    public function getSqlProcedimientoAlmacenado() {
        return $this->sqlProcedimientoAlmacenado;
    }

    /**
     * @param mixed $sqlProcedimientoAlmacenado
     */
    public function setSqlProcedimientoAlmacenado($sqlProcedimientoAlmacenado) {
        $this->sqlProcedimientoAlmacenado = $sqlProcedimientoAlmacenado;
    }

    /**
     * @return mixed
     */
    public function getArgumentosReales() {
        return $this->aArgumentosReales;
    }

    /**
     * @param mixed $aArgumentosReales
     */
    public function setArgumentosReales($aArgumentosReales) {
        $this->aArgumentosReales = $aArgumentosReales;
    }

    protected function getOracleLogger() {
        return $this->oLoggerOracle;
    }

    /**
     * @param $sListaArgumentos
     *
     * @return string
     */
    protected function composeLogParameterList($sListaArgumentos) {
        if (!$sListaArgumentos) {
            $sListaArgumentos = 'null';
        } else {
            $sListaArgumentos = preg_replace('/,\s,/', ', null,', $sListaArgumentos);
            $sListaArgumentos = preg_replace('/,\s$/', ', null', $sListaArgumentos);
        }

        return $sListaArgumentos;
    }

    protected function escribirErrorOracle($sError) {
        /** Unicamente se registran errores en la maquina ULPGC */
        if ($_SERVER['SERVER_ADDR'] == self::SERVIDOR_ULPGC) {
            $aArgumentosReales = $this->getArgumentosReales();
            if (!empty($aArgumentosReales)) {
                $sListaArgumentos = implode(", ", $this->getArgumentosReales());
                $sListaArgumentos = "(" . $this->composeLogParameterList($sListaArgumentos) . ")";
            } else {
                $sListaArgumentos = "()";
            }

            $sMensajeError = $_SERVER['REMOTE_ADDR'] . ' Error al ejecutar ' . $this->getPaquete() . '.' . $this->getProcedimientoAlmacenado() . $this->composeLogParameterList($sListaArgumentos) . ' error devuelto: ' . $sError;
            $this->getOracleLogger()->error($sMensajeError);

            /** Enviar por correo los fallos producidos en oracle a las tuplas ip/email especificadas */
            $aMailer = [];
            //$aMailer[] = array('ip' => '', 'email' => 'david.rodriguez@ulpgc.es');
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: <sistemas@ulpgc.es>' . "\r\n";
            if (!empty($aMailer) and is_array($aMailer)) {
                foreach ($aMailer as $mailLogger) {
                    if (array_key_exists('ip', $mailLogger) && array_key_exists('email', $mailLogger)) {
                        if ($_SERVER['REMOTE_ADDR'] == $mailLogger['ip']) {
                            mail($mailLogger['email'], '[Error Oracle] - ' . $this->getPaquete() . '.' . $this->getProcedimientoAlmacenado(), $sMensajeError, $headers);
                            break;
                        }
                    }
                }
            }
        }
    }

    protected function obtenerRutaLogOracle() {
        preg_match_all('/(.*)(\/vendor)?/', dirname(__FILE__), $aMatches);
        $aResultado = $aMatches[1];
        $sRoot = $aResultado[0];
        $sRutaLog = $sRoot . '/var/log/' . OCI8StatementUlpgc::FICHERO_LOG_ORACLE;

        return $sRutaLog;
    }

    public function getSqlUltimoProcedimiento() {
        if (is_array($this->getArgumentosReales())) {
            $sListaArgumentos = implode(", ", $this->getArgumentosReales());
        } else {
            $sListaArgumentos = '';
        }
        $sListaArgumentos = "(" . $this->composeLogParameterList($sListaArgumentos) . ")";

        return $this->getPaquete() . '.' . $this->getProcedimientoAlmacenado() . $this->composeLogParameterList($sListaArgumentos);
    }

    public function cerrarConexion() {
        try {
            oci_close($this->conexion);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @return \Symfony\Bridge\Monolog\Logger
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

    public function getStmt()
    {
        return $this->getStatement();
    }
    
}
