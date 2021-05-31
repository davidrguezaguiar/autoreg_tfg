<?php

namespace App\Service;

define('K_TCPDF_CALLS_IN_HTML', true);

use App\Entity\Base\Tusuario;
use App\Lib\Base\Utilidades;
use App\Orm\ARUBaseORM;
use App\Orm\Base\PermisosUsuarioOrm;
use App\Service\Base\Suplantacion\SuplantadorIdentidadService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Cache\Adapter\AdapterInterface as cacheAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig_Environment;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * File:    ARUServices.php
 * User:    ULPGC
 * Project: Aplicación de registro de usuarios
 */
class ARUServices {

    /** @var SuplantadorIdentidadService $suplantadorService */
    private $suplantadorService;

    /** @var PermisosUsuarioOrm $oPermisosUsuariosORM */
    private $oPermisosUsuariosORM;
    private $oAuthChecker;
    private $pdfController;
    private $templating;
    private $Logger;
    private $ARUBaseORM;
    private $container;
    private $router;

    /**
     *
     * @var cacheAdapter 
     */
    private $cache;

    //Determina si se usa o no la cache
    const ACTIVARCACHETIC = false;

    /** @var constant PDF_DESCARGAR fuerza la descarga del documento PDF */
    const PDF_DESCARGAR = 0;

    /** @var constant PDF_ABRIR_NAVEGADOR fuerza a que se abra el documento en el navegador */
    const PDF_ABRIR_NAVEGADOR = 1;

    /** @var constant PDF_GUARDAR_SERVIDOR guarda el pdf en el servidor */
    const PDF_GUARDAR_SERVIDOR = 2;

    public function __construct(ARUBaseORM $ARUBaseORM, SuplantadorIdentidadService $oSuplantadorIdentidad,
            AuthorizationCheckerInterface $authorizationChecker, TCPDFController $TCPDFController,
            LoggerInterface $ulpgcLogger, ContainerInterface $container,
            PermisosUsuarioOrm $PermisosUsuarioOrm, cacheAdapter $oCache,
            UrlGeneratorInterface $router) {

        $aParametrosDepuracion = '';

        try {

            $this->setContainer($container);
            $this->router = $router;
            //Cargamos la ip que se encuetra en parametros para los usuarios seleccionados
            if (Utilidades::esDesarrollo()) {
                if ($this->getContainer()->hasParameter('depuracion')) {
                    
                    $aParametrosDepuracion = $this->getContainer()->getParameter('depuracion');
                    if (defined('depuracionIP') === FALSE) {
                        define('depuracionIP', $aParametrosDepuracion);
                    }
                }
            }
            

            
            //Comprobamos si esta accediendo una ip permitida.
            if (Utilidades::controlAccesoComprobaciones() === FALSE) {
                Utilidades::mostrarError('Esta accediendo al entorno de "Desarrollo", revise la URL por si quería acceder al entorno de "PreProduccion".');
                throw Utilidades::controlarError('Esta accediendo al entorno de "Desarrollo", revise la URL por si quería acceder al entorno de "PreProduccion".',
                        $ulpgcLogger, 'Error de acceso a DESARROLLO',
                        array('depuracionIP' => $aParametrosDepuracion,
                              'RemoteAddres' => Utilidades::getClienteIP(),
                              'ServerAddres' => Utilidades::getServidorIP()));
            }

            //Se coloca la configuracion local, para que las fechas aparezcan en español
            setlocale(LC_TIME, "es_ES.utf8");

            $this->setSuplantadorService($oSuplantadorIdentidad);
            $this->setAuthChecker($authorizationChecker);
            $this->setPdfController($TCPDFController);
            $this->setARUBaseORM($ARUBaseORM);
            $this->setPermisosUsuariosORM($PermisosUsuarioOrm);

            /** Preguntamos por el servicio que sera encargado de renderizar las vistas */
            if ($this->getContainer()->has('templating')) {
                $this->setTemplating($this->getContainer()->get('templating'));
            }

            if ($this->getContainer()->has('twig')) {
                $this->setTemplating($this->getContainer()->get('twig'));
            }

            if (defined('DIRECTORIO_TEMPORAL') === FALSE) {
                define('DIRECTORIO_TEMPORAL', $this->getContainer()->getParameter('ruta_temporal'));
            }

            $this->setCache($oCache);

            $this->setLogger($ulpgcLogger);

        } catch (Exception $ex) {
            throw Utilidades::controlarError($ex, $this->getLogger(),
                    __METHOD__ . __LINE__ . '> Error en el servicio');
        }
    }

    public function getParameters() {
        return $this->getContainer()->getParameter('depuracion');
    }

    /**
     * Obtiene un parametro del contenedor de Symfony
     * @param type $sParametro
     * @return boolean
     */
    public function getParametro($sParametro) {
        if ($this->getContainer()->hasParameter($sParametro)) {
            return $this->getContainer()->getParameter($sParametro);
        } else {
            return false;
        }
    }

    /**
     * 
     * @return cacheAdapter
     */
    public function getCache() {

        return $this->cache;
    }

    /**
     * 
     * @param cacheAdapter $cache
     */
    private function setCache($cache) {
        $this->cache = $cache;
    }

    /**
     * Limpia completamente la cache
     */
    public function cacheLimpiarCompleta() {
        $this->getCache()->clear();
    }

    /**
     * Elimina la cache del usuario 
     * 
     * @param string $sDni
     */
    public function cacheLimpiarUsuario($sDni) {

        $this->getCache()->deleteItem(sha1($sDni));
    }

    public function onKernelRequest(\Symfony\Component\HttpKernel\Event\RequestEvent $event) {

        /** @var Request $request */
        $request = $event->getRequest();
    }

    public function onKernelController(\Symfony\Component\HttpKernel\Event\ControllerEvent $event) {

        null;
    }

    public function rutaImagenes() {
        return realpath($this->getContainer()->getParameter('kernel.project_dir') . '/../images');
    }

    /**
     * 
     * @return PermisosUsuarioOrm
     */
    public function getPermisosUsuariosORM() {
        return $this->oPermisosUsuariosORM;
    }

    public function setPermisosUsuariosORM($oPermisosUsuariosORM) {
        $this->oPermisosUsuariosORM = $oPermisosUsuariosORM;
    }

    /**
     * 
     * @return SuplantadorIdentidadService
     */
    public function getSuplantadorService() {
        return $this->suplantadorService;
    }

    /**
     * 
     * @param SuplantadorIdentidadService $suplantadorService
     */
    public function setSuplantadorService($suplantadorService) {
        $this->suplantadorService = $suplantadorService;
    }

    /**
     * 
     * @return AuthorizationCheckerInterface
     */
    public function getAuthChecker() {
        return $this->oAuthChecker;
    }

    /**
     * 
     * @return TCPDFController
     */
    public function getPdfController() {
        return $this->pdfController;
    }

    /**
     * 
     * @return Twig_Environment
     */
    public function getTemplating() {
        return $this->templating;
    }

    /**
     * 
     * @return Logger
     */
    public function getLogger() {
        return $this->Logger;
    }

    /**
     * 
     * @return ARUBaseORM
     */
    public function getARUBaseORM() {
        return $this->ARUBaseORM;
    }

    /**
     * 
     * @return ContainerInterface
     */
    public function getContainer() {
        return $this->container;
    }

    public function setAuthChecker($oAuthChecker) {
        $this->oAuthChecker = $oAuthChecker;
    }

    public function setPdfController($pdfController) {
        $this->pdfController = $pdfController;
    }

    public function setTemplating($templating) {
        $this->templating = $templating;
    }

    public function setLogger($Logger) {
        $this->Logger = $Logger;
    }

    public function setARUBaseORM($ARUBaseORM) {
        $this->ARUBaseORM = $ARUBaseORM;
    }

    public function setContainer($container) {
        $this->container = $container;
    }

    /**
     * 
     * @return Tusuario
     */
    public function getUsuario() {
        if ($this->getSuplantadorService()->esIdentidadSuplantada()) {
            return $this->getSuplantadorService()->obtenerInformacionSuplantacion();
        } else {
            return $this->getSession()->get('userObject', NULL);
        }
    }

    public function setUsuario($oUsuario) {
        try {
            $this->getSession()->set('userObject', $oUsuario);
            $this->getSession()->save();
            return true;
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Error al guardar la session de usuario.');
        }
    }

    /**
     * @return Session
     */
    public function getSession() {
        return $this->getContainer()->get('session');
    }

    /**
     * Guarda un PDF en la sesion para despues recuperarlo nuevamente.
     * 
     * @param string $rutaFicheroServidor
     * @return string Nombre del index para su recuperacion
     * @throws \Exception
     */
    public function guardarPDFSession($rutaFicheroServidor, $sTituloDocumento = 'Solicitud de certificado académico personal') {

        try {

            //Se poner para un codigo mas sencillo.
            $oSession = $this->getSession();

            if ($oSession->has('PDF')) {
                $aPDF = $oSession->get('PDF');
            } else {
                $aPDF = new ArrayCollection();
            }

            $nombreFichero = basename($rutaFicheroServidor, '.pdf');

            //Variable que guarda el stream de los PDFs
            $aPDF->set($nombreFichero, array('stream' => file_get_contents($rutaFicheroServidor), 'titulo' => preg_replace('/\.$/', '', $sTituloDocumento)));

            $oSession->set('PDF', $aPDF);
            $oSession->save();
                        
            unlink($rutaFicheroServidor);
            return $nombreFichero;
            
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger(),
                    'Ha habido un error al introducir el PDF en la session.'
            );
        }
    }

    /**
     * 
     * Recoge un PDF de la sesion guardado con la funcion 'guardarPDFSession' 
     * 
     * @param type $indexArrayPDF
     * @return mixed
     * @throws \Exception
     */
    public function obtenerPDFSession($indexArrayPDF) {
        try {
            //Se poner para un codigo mas sencillo.
            $oSession = $this->getSession();

            //Se comprueba si existe el Arraycollection PDF en session 
            if ($oSession->has('PDF')) {
                /** @var ArrayCollection $aPDF */
                $aPDF = $oSession->get('PDF');
            } else {
                throw Utilidades::controlarError(
                        'No existe la session PDF',
                        $this->getLogger()
                );
            }

            //Comprobamos si existe el fichero que se busca
            if ($aPDF->containsKey($indexArrayPDF)) {

                //Se retorno Stream del fichero
                return $aPDF->get($indexArrayPDF);
            } else {
                throw Utilidades::controlarError(
                        'No existe el fichero que se solicita',
                        $this->getLogger()
                );
            }
        } catch (Exception $ex) {
            throw Utilidades::controlarError(
                    $ex,
                    $this->getLogger()
            );
        }
    }
    
    
    
}