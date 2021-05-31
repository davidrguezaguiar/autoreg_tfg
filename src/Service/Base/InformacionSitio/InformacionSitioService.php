<?php
/**
 * Created by ULPGC.
 * Servicio que obtiene/establece en sesion los valores de la aplicacion. Por ahora esta el correo de contacto. Se iran poniendo segun se
 * requieran
 */

namespace App\Service\Base\InformacionSitio;

use App\Entity\Base\Aplicacion;
use App\Orm\Base\GenericOrm;
use App\Orm\Base\PermisosUsuarioOrm;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;



class InformacionSitioService {

    private $container;
    private $orm;

public function __construct ( PermisosUsuarioOrm $permisosUsuarioORM, ContainerInterface $container) {
    $this->orm = $permisosUsuarioORM;
    $this->container = $container;
    
}

/**
 * @param GetResponseEvent $event
 */
public function onKernelRequest ( GetResponseEvent $event ) {

    /** @var Request $request */
    $request = $event->getRequest ();

    /**
     * Si no esta el correo en sesion, se obtiene de la BD
     */
    $datosAplicacion = $request->getSession ()->get ('datosAplicacion');

    if (empty($datosAplicacion)){

        $sDenominacionULPGES = $this->getContainer()->getParameter('informacion_sitio');
        /**TODO: Establecer la variable por parametro de services.yml para pasarla por parametro */
        $oAplicacion = $this->getOrm ()->obtenerDatosAplicacion ($sDenominacionULPGES,$mensajeError);

        if ($oAplicacion instanceof Aplicacion){
            $request->getSession ()->set('datosAplicacion',$oAplicacion);
        }
    }
}

    /**
     * @return Container
     */
    public function getContainer () {
        return $this->container;
    }

    /**
     * @param mixed $container
     */
    public function setContainer ( $container ) {
        $this->container = $container;
    }

    /**
     * @return GenericOrm
     */
    public function getOrm () {
        return $this->orm;
    }

    /**
     * @param GenericOrm $orm
     */
    public function setOrm ( GenericOrm $orm ){
        $this->orm = $orm;
    }




}