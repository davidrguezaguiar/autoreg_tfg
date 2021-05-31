<?php

namespace App\Service\Base;

use App\Lib\Base\Utilidades;
use App\Lib\PDF\UlpgcPDF;
use App\Orm\Base\PermisosUsuarioOrm;
use App\Service\Base\Suplantacion\SuplantadorIdentidadService;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Cache\Adapter\AdapterInterface as cacheAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig_Environment;
use Qipsius\TCPDFBundle\Controller\TCPDFController;

class BASEService {

    /** @var SuplantadorIdentidadService $suplantadorService */
    private $suplantadorService;

    /** @var PermisosUsuarioOrm $oPermisosUsuariosORM */
    private $oPermisosUsuariosORM;
    private $oAuthChecker;
    private $pdfController;
    private $templating;
    private $Logger;
    private $container;

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

    public function __construct(SuplantadorIdentidadService $oSuplantadorIdentidad,
            AuthorizationCheckerInterface $authorizationChecker, TCPDFController $TCPDFController,
            Logger $Logger, ContainerInterface $container,
            PermisosUsuarioOrm $PermisosUsuarioOrm, cacheAdapter $oCache) {


        $this->setContainer($container);

        //Cargamos la ip que se encuetra en parametros para los usuarios seleccionados
        if (Utilidades::esDesarrollo()) {
            if ($this->getContainer()->hasParameter('depuracion')) {
                $aParametrosDepuracion = $this->getContainer()->getParameter('depuracion');
                if (is_array($aParametrosDepuracion) && count($aParametrosDepuracion) > 0) {
                    if (defined('depuracionIP') === FALSE) {
                        define('depuracionIP', $aParametrosDepuracion);
                    }
                }
            }
        }

        //Comprobamos si esta accediendo una ip permitida.
        if (Utilidades::controlAccesoComprobaciones() === FALSE && Utilidades::depurarESServidorTIC() === TRUE) {

            Utilidades::mostrarError('Esta accediendo al entorno de "Desarrollo", revise la URL por si quería acceder al entorno de "PreProduccion".');
            throw Utilidades::controlarError('Esta accediendo al entorno de "Desarrollo", revise la URL por si quería acceder al entorno de "PreProduccion".',
                    $Logger, 'Error de acceso a DESARROLLO');
        }

        //Se coloca la configuracion local, para que las fechas aparezcan en español
        setlocale(LC_TIME, "es_ES.utf8");

        $this->setSuplantadorService($oSuplantadorIdentidad);
        $this->setAuthChecker($authorizationChecker);
        $this->setPdfController($TCPDFController);
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

        $this->setLogger($Logger);
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

    public function onKernelRequest(GetResponseEvent $event) {

        /** @var Request $request */
        $request = $event->getRequest();
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

    public function setContainer($container) {
        $this->container = $container;
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
}
