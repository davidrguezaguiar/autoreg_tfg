<?php
/**
 * Clase generica ORM que heredaran todos los ORM para la conexion
 */

namespace App\Orm\Base;

use App\Lib\Base\Utilidades;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use \Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use \Exception;
use \Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Monolog\Logger;
use App\Entity\Base\Aplicacion;
use Doctrine\ORM\ORMInvalidArgumentException;

/**
 * Class GenericOrm
 *
 */
class GenericOrm
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */

    private $container;

    /**
     * @var \Symfony\Bridge\Doctrine\RegistryInterface
     */
    private $doctrine;

    /** @var \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface */
    private $authorizationChecker;

    private $dbalConnection;

    private $ormConnection;

    private $esquema;

    private $paquete;

    /**
     * @var \Symfony\Bridge\Monolog\Logger
     */
    private $Logger;


    /**
     * DocentiaOrm constructor.
     *
     * @param $doctrine  ManagerRegistry
     * @param $container ContainerInterface
     */
    public function __construct(
            ManagerRegistry $doctrine,
            ContainerInterface $container,
            AuthorizationCheckerInterface $authorizationChecker,
            $esquema,
            $paquete,
            $dbalConnection,
            $ormConnection,
            Logger $Logger
    ) {
        $this->container            = $container;
        $this->doctrine             = $doctrine;
        $this->esquema              = $esquema;
        $this->paquete              = $paquete;
        $this->dbalConnection       = $dbalConnection;
        $this->ormConnection        = $ormConnection;
        $this->authorizationChecker = $authorizationChecker;
        $this->Logger            = $Logger;

    }

    /**
     * Genera un orm a partir de una clase dada usando Reflection class
     *
     * @param $class
     * @return bool|object
     */
    public function generarNuevoORM($class)
    {
        try {
            $oReflectionClass = new \ReflectionClass($class);

            $orm = $oReflectionClass->newInstanceArgs(array(
                    $this->getDoctrine(),
                    $this->getContainer(),
                    $this->getAuthorizationChecker(),
            ));

            return $orm;
        } catch (Exception $e) {
            throw new Exception (Utilidades::controlarError($e)->getMessage());
        }
    }


    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param mixed $container
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

    /**
     * @return \Doctrine\Common\Persistence\ManagerRegistry
     */
    public function getDoctrine()
    {
        return $this->doctrine;
    }

    /**
     * @param mixed $doctrine
     */
    public function setDoctrine($doctrine)
    {
        $this->doctrine = $doctrine;
    }


    /**
     * @param $orm_connection
     * @param $entidad . Clase que hace referencia a la tabla
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getManager($entidad, $orm_connection = null)
    {
        $orm_connection = $orm_connection ? $orm_connection : $this->getOrmConnection();

        return $this->getDoctrine()->getManager($orm_connection)->getRepository($entidad);
    }

    /**
     * Vuelve a abrir el entityManager caso de que estuviera cerrado
     *
     * @param $em
     * @return mixed
     */
    public function reopenEntityManager($em)
    {

        if (!$em->isOpen()) {
            $manager = $this->getDoctrine()->getManager();
            $em      = $manager->create($em->getConnection(), $em->getConfiguration());
        }

        return $em;
    }

    /**
     * @param $orm_connection
     *
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager($orm_connection = null)
    {
        if (!$orm_connection) {
            $orm_connection = $this->getOrmConnection();
        }

        return $this->getDoctrine()->getManager($orm_connection);
    }

    /**
     *
     * @param type $object
     * @param type $sError
     * @param type $cargarEntidadBD
     * @return boolean
     */
    public function grabarObjetoBD($object, &$sError, $cargarEntidadBD = false)
    {
        $sError = '';
        if (!is_object($object) or ($object == null)) {
            $sError = 'Imposible grabar, datos vacíos';

            return false;
        }

        try {
            //En caso de que se tenga que recoger la entidad de la BD se hace un merge
            if ($cargarEntidadBD) {
                //Es un objeto que proviene de la cachepor lo que hay que hacer esto para que
                // doctrine la maneje correctamente
                $objectMerge = $this->getEntityManager()->merge($object);

                /** 2.- Se elimina la solicitud */
                $this->getEntityManager()->persist($objectMerge);
            } else {
                $this->getEntityManager()->persist($object);
            }

            $this->getEntityManager()->flush();

            return true;
        } catch (ORMInvalidArgumentException $e) {
            //Vemos si la excepcion es pq esta tratando una entidad en cache, si es asi volvemos a lanzar el metodo cogiendo el de la BD
            if (preg_match('/Detached entity/', $e->getMessage())) {

                $this->getLogger()->addDebug(Utilidades::controlarError('Se ha generado Excepcion por error con '
                                . '"Detached Entity", se vuelve a llamar al metodo "' . __FUNCTION__)->getMessage() . '"');

                $this->grabarObjetoBD($object, $sError, true);
            } else {
                $this->getLogger()->addError(Utilidades::controlarError($e)->getMessage(), array($object));
                $sError = Utilidades::controlarError($e)->getMessage();

                return false;
            }
        } catch (Exception $e) {
            $this->getLogger()->addDebug(Utilidades::controlarError($e)->getMessage(), $e->getTrace());
            $sError = Utilidades::controlarError($e)->getMessage();

            return false;
        }
    }



    /**
     * @param $object
     * @return bool
     * @throws Exception
     */

    public function borrarObjetoBD($object, &$sError, $cargarEntidadBD = false)
    {

        $sError = '';
        if (!is_object($object) or ($object == null)) {
            $sError = 'Imposible grabar, datos vacíos';

            return false;
        }

        try {
            //En caso de que se tenga que recoger la entidad de la BD se hace un merge
            if ($cargarEntidadBD) {
                //Es un objeto que proviene de la cachepor lo que hay que hacer esto para que
                // doctrine la maneje correctamente
                $objectMerge = $this->getEntityManager()->merge($object);

                /** 2.- Se elimina la solicitud */
                $this->getEntityManager()->remove($objectMerge);
            } else {
                $this->getEntityManager()->remove($object);
            }

            $this->getEntityManager()->flush();

            return true;
        } //En caso de excepcion con ORM
        catch (ORMInvalidArgumentException $e) {
            //Vemos si la excepcion es pq esta tratando una entidad en cache, si es asi volvemos a lanzar el metodo cogiendo el de la BD
            if (preg_match('/Detached entity/', $e->getMessage())) {

                $this->getLogger()->addDebug(Utilidades::controlarError('Se ha generado Excepcion por error con '
                                . '"Detached Entity", se vuelve a llamar al metodo "' . __FUNCTION__)->getMessage() . '"');
                $this->borrarObjetoBD($object, $sError, true);
            } else {
                $this->getLogger()->addError(Utilidades::controlarError($e)->getMessage(), array($object));
                $sError = Utilidades::controlarError($e)->getMessage();

                return false;
            }
        } catch (Exception $e) {
            $this->getLogger()->addError(Utilidades::controlarError($e)->getMessage(), array($object));
            $sError = Utilidades::controlarError($e)->getMessage();

            return false;
        }
    }

    /**
     * @param      $class
     * @param null $aCriterios
     * @return bool|null|object
     * @example obtenerUnObjetoDeUnaEntidadBD ( Tinv0011::class , array('ncodgasto' => 123)  )
     */
    public function obtenerUnObjetoDeUnaEntidadBD($class, $aCriterios = null)
    {
        try {
            $aCriterios = $aCriterios ? $aCriterios : [];
            $oEntidad   = $this->getManager($class)->findOneBy($aCriterios);

            return $oEntidad;
        } catch (Exception $e) {
            throw new Exception (Utilidades::controlarError($e)->getMessage());

        }
    }

    /**
     * @param      $class
     * @param null $aCriterios
     * @param null $aOrdenResultado
     * @return array|bool|object[]|ArrayCollection
     * @throws Exception
     * @example obtenerTodosLosObjetoDeUnaEntidadBD ( Tinv0011::class , array('ncodgasto' => 123), array('dfmoda' => 'ASC') )
     */
    public function obtenerTodosLosObjetoDeUnaEntidadBD($class, $aCriterios = null, $aOrdenResultado = null)
    {
        try {
            $aCriterios      = $aCriterios ? $aCriterios : [];
            $aOrdenResultado = $aOrdenResultado ? $aOrdenResultado : [];
            $oEntidad        = $this->getManager($class)->findBy($aCriterios, $aOrdenResultado);

            return $oEntidad;
        } catch (Exception $e) {
            throw new Exception (Utilidades::controlarError($e)->getMessage());

        }
    }

    /**
     * @param      $class
     * @param null $aCriterios
     * @param null $aOrdenResultado
     * @return array|bool|null|object|object[]|ArrayCollection
     * @throws Exception
     */
    public function obtenerEntidad($class, $aCriterios = null, $aOrdenResultado = null)
    {
        $aCriterios      = $aCriterios ? $aCriterios : [];
        $aOrdenResultado = $aOrdenResultado ? $aOrdenResultado : [];
        if (!empty($aCriterios)) {
            try {
                return $this->obtenerUnObjetoDeUnaEntidadBD($class, $aCriterios);
            } catch (Exception $e) {
                throw new Exception (Utilidades::controlarError($e)->getMessage());
            }

        } else {
            try {
                return $this->obtenerTodosLosObjetoDeUnaEntidadBD($class, [], $aOrdenResultado);
            } catch (Exception $e) {
                throw new Exception (Utilidades::controlarError($e)->getMessage());
            }

        }
    }

    /**
     * Envia un fichero usando COMUN.PACKCORREO
     *
     * @param      $sDestinatario
     * @param      $sAsunto
     * @param      $sTexto
     * @param null $sRemitente
     * @param null $sNombreFicheroAdjunto
     * @param null $sRutaFicheroAdjunto
     * @return string
     * @throws Exception
     */
    public function enviarCorreo(
            $sDestinatario,
            $sAsunto,
            $sTexto,
            $sRemitente = null,
            $sNombreFicheroAdjunto = null,
            $sRutaFicheroAdjunto = null
    ) {
        try {
            /** Enviar correo con fichero adjunto adjunto */
            if (!empty($sRutaFicheroAdjunto)) {
                if (empty($sNombreFicheroAdjunto)) {
                    throw new Exception('Debe especificar el nombre del fichero adjunto');
                }

                /**
                 * TODO: Mejorar para enviar por PACKCORREO los correos con adjuntos
                 * 1.- Lo que llega en $sContenidoFicheroAdjunto es un stream/blob
                 * Lo que espera Utilidades::enviarMail es la ruta del fichero a enviar
                 * Asi que escribimos en el temporal del sistema el fichero
                 */

                if (empty($sRemitente)) {
                    throw new Exception('Debe informar el remitente para enviar un correo con adjuntos');
                }

                Utilidades::enviarMail($sRemitente,
                        $sDestinatario,
                        $sAsunto,
                        $sTexto,
                        $sRutaFicheroAdjunto,
                        $sNombreFicheroAdjunto);

                return true;
            } else {
                $conexion = $this->getConnection('PROCENVIARTEXTO', 'COMUN', 'PACKCORREO', 'ulpges');


                /** Enviar correo sin fichero adjunto */
                if (!empty($sRemitente)) {
                    $conexion->getStmt()->PROCENVIARTEXTO($sRemitente, $sDestinatario, $sAsunto, $sTexto, null);
                } else {
                    $conexion->getStmt()->PROCENVIARTEXTO(null, $sDestinatario, $sAsunto, $sTexto, null);
                }

                return true;
            }
        } catch (Exception $e) {
            throw new Exception (Utilidades::controlarError($e)->getMessage());
        }
    }


    /**
     * @param $dbalConnection
     * @param $esquema
     * @param $pack
     * @param $proc
     *
     * @return \Doctrine\DBAL\Statement
     */
    protected function getConnection($proc, $esquema = null, $pack = null, $dbalConnection = null)
    {
        $esquema        = $esquema ? $esquema : $this->getEsquema();
        $dbalConnection = $dbalConnection ? $dbalConnection : $this->getDbalConnection();
        $pack           = $pack ? $pack : $this->getPaquete();

        if (Utilidades::esDesarrollo()) {
            $this->getLogger()
                    ->addDebug(basename(__FILE__) . ' - ' . __FUNCTION__ . ':' . __LINE__ . '> Preparando : ' . $dbalConnection . '[' . $esquema . '->' . $pack . '->' . $proc . ']');
        }
        /** @var \App\DBAL\OCI8ConnectionUlpgc $auxConexion */
        $auxConexion = $this->getDoctrine()->getConnection($dbalConnection);


        $auxConexion->getWrappedConnection()->setLogger($this->getLogger());
        $auxConexion2 = $auxConexion->prepare($esquema . '->' . $pack . '->' . $proc);

        //$auxConexion2->getStmt ()->setLogger ( $this->getLogger () );

        return $auxConexion2;

    }


    /**
     * Comprueba si el usuario conectado tiene el rol $sRol
     *
     * @param $sRol
     *
     * @return mixed
     */
    public function siUsuarioTieneRol($sRol)
    {
        return $this->getAuthorizationChecker()->isGranted($sRol);
    }

    /** TODO: pendiente de ver cómo se hace en esta versión de Symfony
     * Comprueba si se esta ejecutando el entorno de desarrollo
     *
     * @return bool
     *
     * public function siDesarrollo()
     * {
     * return $this->getEnvironment() == 'dev';
     * }
     */


    /**TODO:Comprobar que funciona en esta versión de Symfony
     * Get a user from the Security Context.
     *
     * @return mixed
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see TokenInterface::getUser()
     */
    public function getUser()
    {
        if (!$this->getContainer()->has('security.context')) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        /** @var \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token */
        $token = $this->getContainer()->get('security.context')->getToken();

        if (null === $token) {
            return null;
        }

        if (!is_object($user = $token->getUser())) {
            return null;
        }

        return $user;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Session\Session
     */
    public function getSession()
    {
        return $this->getContainer()->get('session');
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getDbalConnection()
    {
        return $this->dbalConnection;
    }

    /**
     * @param mixed $dbalConnection
     */
    public function setDbalConnection($dbalConnection)
    {
        $this->dbalConnection = $dbalConnection;
    }

    /**
     * @return mixed
     */
    public function getEsquema()
    {
        return $this->esquema;
    }

    /**
     * @param mixed $esquema
     */
    public function setEsquema($esquema)
    {
        $this->esquema = $esquema;
    }

    /**
     * @return mixed
     */
    public function getPaquete()
    {
        return $this->paquete;
    }

    /**
     * @param mixed $paquete
     */
    public function setPaquete($paquete)
    {
        $this->paquete = $paquete;
    }

    /**
     * @return mixed
     */
    public function getOrmConnection()
    {
        return $this->ormConnection;
    }

    /**
     * @param mixed $ormConnection
     */
    public function setOrmConnection($ormConnection)
    {
        $this->ormConnection = $ormConnection;
    }

    /**
     * @return \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface
     */
    public function getAuthorizationChecker(): \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface
    {
        return $this->authorizationChecker;
    }

    /**
     * @param \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $authorizationChecker
     */
    public function setAuthorizationChecker(
            \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Cierra una conexion con la base de datos.
     *
     * Esto se debe forzar a veces cuando se usa Doctrine y [Procedimientos|Funciones], para evitar errores de conversion de fechas y
     * numeros con decimales. Se llama al [procedimiento|funcion] que se quiera y posteriormente se cierra la conexion y se llama a los
     * metodos de doctrine, doctrine automaticamente abrira una nueva conexion.
     *
     *
     * @return boolean
     */
    public function cerrarConexion()
    {
        return $this->getDoctrine()->getConnection($this->dbalConnection)->close();
    }


    /**
     * @param $nombreAplicacion
     * @param $mensajeError
     * @return \App\Entity\Base\Aplicacion
     *  Obtiene un cursor con los datos de la aplicación a la plataforma de aplicaciones Symfony. IMPORTANTE: Tener en cuenta la cadena
     *  a pasarle para casar con el nombre
     */
    public function obtenerDatosAplicacion($nombreAplicacion, &$mensajeError)
    {


        try {
            $conexion = $this->getConnection('PROCOBTENERDATOSAPLICACION', 'ULPGES', 'PACKSYMFONY', 'ulpges');

            $cursor = $conexion->getStmt()->PROCOBTENERDATOSAPLICACION($nombreAplicacion, null);


            if ($cursor) {

                if ($row = $conexion->fetchCursor($cursor)) {

                    $oAplicacion = new Aplicacion($row[ 'NCOD_MODULO' ], $row[ 'ADENOM_MODULO' ], $row[ 'ATELEFONO_CONTACTO' ], $row[ 'ACORREO_CONTACTO' ]);
                }

                $conexion->closeCursor($cursor);

                return $oAplicacion;
            }

        } catch (Exception $e) {
            $mensajeError = Utilidades::comprobarErrorOracle($e);

            return false;
        }
    }

    /**
     *
     * @return Symfony\Bridge\Monolog\Logger
     */
    public function getLogger()
    {
        return $this->Logger;
    }

    /**
     *
     * @param \Symfony\Bridge\Monolog\Logger $Logger
     */
    public function setLogger($Logger)
    {
        $this->Logger = $Logger;
    }


}