<?php
namespace App\Controller;

use App\Lib\Base\Utilidades;
use App\Service\ARUServices;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 * File:    ComprobacionesController.php
 * User:    ULPGC
 * Project: NombreProyecto
 */

/**
 * @Route("/comprobaciones")
 */
class ComprobacionesController extends AbstractController
{
    /**
     * Pruebas de procedimientos en el ORM.
     * @Route("/procedimientosbd", name="procedimientos_bd")
     * @Template("/Comprobaciones/procedimientosBD.html.twig")
     */
    public function procedimientosAction(Request $request, ARUServices $ARUServices)
    {
        //Control de acceso 
        if (Utilidades::controlAccesoComprobaciones() === false) {
            $ARUServices->getLogger()
                    ->err('Se ha intentado acceder a "' . __FUNCTION__ . '" por la IP: "' . $request->getClientIp() . '"');

            return Utilidades::respuestaError404();
        }

        $datosRespuesta = '';
        $metodoBD       = $request->get('metodoBD', false);
        $parametrosBD   = '';

        //Carga los metodos disponibles del ORM
        $aMetodosDisponibles = get_class_methods($ARUServices);

        try {

            if ($request->get('Enviar', false) && $metodoBD) {
                //No existe el metodo indicado 
                if (array_search($metodoBD, $aMetodosDisponibles) === false) {
                    throw Utilidades::controlarError('No se encuentra el método [' . $metodoBD . '] en el ORM');
                }

                //Realiza una llamada a los procedimientos
                $datosRespuesta = call_user_func_array(array($ARUServices, $metodoBD), explode(';', $request->get('parametrosBD', '')));
            }


            return array(
                    'actionForm'         => 'procedimientos_bd',
                    'metodosDisponibles' => $aMetodosDisponibles,
                    'metodoBD'           => $metodoBD,
                    'parametrosBD'       => $parametrosBD,
                    'respuesta'          => $datosRespuesta,
            );
        } catch (Exception $ex) {
            return array(
                    'actionForm'         => 'procedimientos_bd',
                    'metodosDisponibles' => $aMetodosDisponibles,
                    'error'              => Utilidades::controlarError($ex)->getMessage(),
                    'metodoBD'           => $metodoBD,
                    'parametrosBD'       => $parametrosBD,
                    'respuesta'          => $datosRespuesta,
            );
        }
    }

    /**
     * @Route("borrarCacheUsuario", name="_borrarCacheUsuario")
     * @Template("/GastosMenores/test.html.twig")
     */
    public function borrarCacheUsuarioAction(ARUServices $ARUServices) {

        if (Utilidades::controlAccesoComprobaciones() === FALSE) {
            return self::respuestaError404();
        }

        $ARUServices->cacheLimpiarCompleta();

        Utilidades::mostrarAdvertencia('Se ha limpiado la cache completamente');

        return $this->redirectToRoute("_registro_usuarios_paso1");
    }


}
