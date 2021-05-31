<?php

namespace App\Controller;

use App\Lib\ARULiterales;
use App\Lib\Base\Utilidades;
use App\Orm\ARUBaseORM;
use App\Service\ARUServices;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Gregwar\Captcha\CaptchaBuilder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/")
 */
class ARUController extends AbstractController {

    /**
     * @Route("error" , name="_errorPantalla", methods={"GET"})
     * @Template("Base/pantallaErrores.html.twig")
     * @example $this En el catch de function mostrarSolicitudesAction
     */
    public function mostrarErrorPantallaAction(Request $request, ARUServices $ARUServices) {
        $aErrores = $request->getSession()->getFlashBag()->get('error');

        if (count($aErrores) > 0) {
            $ARUServices->getLogger()->error('Error de pantallla', array($aErrores,
                'Esta página es génerica de error. Es redirigida desde la pantalla que generó el error a esta, para ver el error debe ver la petición anterior con HTML STATUS 302(Redirect).',)
            );

            foreach ($aErrores as $sError) {
                $request->getSession()->getFlashBag()->add('error', $sError);
            }
            return array();
        } else {            
            return new RedirectResponse($this->generateUrl('_inicio'));
        }
    }

    /**
     * @Route("lopd" , name="_consentimiento_LOPD")
     * @Template("Base/informacionPID.html.twig")
     */
    public function mostrarLOPDConsentimientoACtion(Request $request, ARUServices $ARUServices) {

        $pdfEjercicioDerecho = '';
        $pdfReglamentoEuropeo = '';

        if ($ARUServices->getContainer()->hasParameter('pdf_app')) {
            $rutaPDFAplicacion = $ARUServices->getContainer()->getParameter('pdf_app');
            $pdfEjercicioDerecho = $ARUServices->guardarPDFSession($rutaPDFAplicacion . '/EjercicioDerechosULPGC.pdf', 'EjercicioDerechosULPGC');
            $pdfReglamentoEuropeo = $ARUServices->guardarPDFSession($rutaPDFAplicacion . '/ReglamentoEuropeo.pdf', 'ReglamentoEuropeo');
        }
        
        return array( 'pdfEjercicioDerecho'  => $pdfEjercicioDerecho,
                      'pdfReglamentoEuropeo' => $pdfReglamentoEuropeo);
    }

    /**
     * 
     * Codigo realizado para guardar las variables de desarrollo que modifican
     * la el comportamiento del programa, se guarda asi para poder modificar
     * los comportamientos de AJAX
     * 
     * @Route("ajax/desarrollo/variables", name="ajax_variables_desarrollo")
     */
    public function ajaxVariablesDesarrollo(Request $request, ARUServices $ARUServices) {
        //Comprobamos si estamos en desarrollo 
        if (Utilidades::esDesarrollo()) {
            Utilidades::pruebasDesarrollo($request, $ARUServices, true);
        }

        return Utilidades::respuestaOK('Incluyendo variables de desarrollo que alteran el funcionamiento');
    }
        
    /**
     * 
     * Funcion que devuelve un pdf para usar en los Iframe, se puede usar con el pdfEmbebido.html.twig y se le pasa el documento
     *  que se quiere mostrar.
     * 
     * Para poder usar esta funcion debe exisitr en la sesion del usuario el arrayCOLLECTION PDF, con la forma (hay un ejemplo de uso en this::certAcademicaPersonalSolicitud ):
     *      $aPDF = array('codigoDocumento' => array('stream' => BINARIO_PDF, 'nombre' => Texto a usar en el titulo del documento (Metadato));
     * 
     * 
     * @Route("solicitud/generica/pdf/{codigoDocumento}", name="_solicitud_generica_pdf", defaults={"solicitudPreparar"=false})
     */
    public function genericoPDFIframe(Request $request, ARUServices $ARUServices) {

        try {

            $bDescargar = $request->get('botonDescargar', FALSE) ? TRUE : FALSE;
            $bFinalizar = $request->get('finalizar', FALSE);

            // Se redirige a Inicio
            if ($bFinalizar) {
                $mensaje = 'Trámite finalizado correctamente.';
                Utilidades::mostrarInformacion($mensaje);

                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action');
            }

            $codigoDocumento = $request->get('codigoDocumento', FALSE);

            //si viene vacio
            if ($codigoDocumento === FALSE) {
                throw Utilidades::controlarError('No existe el documento que se solicita');
            }

            /** @var ArrayCollection $aPDF */
            $aPDF = $ARUServices->obtenerPDFSession($codigoDocumento);
            return Utilidades::descargaFicheroPDF($aPDF['stream'], $aPDF['titulo'], $bDescargar);
            
        } catch (Exception $ex) {
            //Se crea menaje en el log y se muestra al usuario
            Utilidades::mostrarError(Utilidades::controlarError(
                            $ex,
                            $ARUServices->getLogger(),
                            'Error al generar el PDF para IFRAME.'
                    )->getMessage());
            return Utilidades::respuestaError('No se ha encontrado ningun borrador de certificado académico personal.');
        }
    }    
    
    

    /**
     * Pantalla de registro de usuarios. Paso 1.
     * 
     * @Route("inicio", name="_registro_usuarios_paso1")
     * @param ARUBaseORM $ARUBaseORM
     * @param Request    $request
     * @Template("ARU/registroUsuariosPaso1.html.twig")
     */
    public function formularioRegistroPaso1Action(Request $request, ARUBaseORM $ARUBaseORM) {

        try {

            // Se controla si el usuario ya ha pasado y validado el Paso 1. En caso contrario se limpian los datos de sesión $aDatosSesionRegistro.
            $bValidadoPaso1 = $request->getSession()->get('ValidadoPaso1', FALSE);

            if ($bValidadoPaso1) {
                $aDatosSesionRegistro = $ARUBaseORM->generarDatosSesionRegistroUsuario($request, FALSE);
            } else {
                $aDatosSesionRegistro = $ARUBaseORM->generarDatosSesionRegistroUsuario($request, TRUE);
            }

            //Comprobamos si vuelve de la segunda pantalla del registro
            $sAccionBT = $request->get('accion', FALSE);

            if ($sAccionBT === 'cancelar') {
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action');
            }

            if ($sAccionBT == 'enviarPaso1') {

                /**  Si todo ha ido bien se redirige al segundo paso del formulario. */
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso2Action', ['request' => $request,
                            'ARUBaseORM' => $ARUBaseORM]);
            }//If boton enviar

            return array( 'mensajeBienvenida' => $ARUBaseORM->getMensajeBienvenida(),
                          'mensajeVerificacion' => '$ARUBaseORM->getMensajeCodigoVerificacion()',
                          'datosSesionRegistro' => $aDatosSesionRegistro);
            
        } catch (Exception $ex) {

            Utilidades::mostrarError(Utilidades::controlarError($ex)->getMessage());
            return $this->redirectToRoute("_errorPantalla");
        }
    }

    /**
     * Pantalla de registro de usuarios. Paso 2.
     * 
     * @Route("verificacion", name="_registro_usuarios_paso2")
     * @param ARUBaseORM $ARUBaseORM
     * @param Request    $request
     * @return array|Response|RedirectResponse
     * @Template("ARU/registroUsuariosPaso2.html.twig")
     */
    public function formularioRegistroPaso2Action(Request $request, ARUBaseORM $ARUBaseORM) {

        try {

            $aDatosSesionRegistro = $ARUBaseORM->generarDatosSesionRegistroUsuario($request, FALSE);

            //Comprobamos si vuelve de la tercera pantalla del registro
            $sAccionBT = $request->get('accion', FALSE);

            // Se controlan los pasos del formulario por los que se ha pasado durante el registro.
            $bValidadoPaso1 = $request->getSession()->get('ValidadoPaso1', FALSE);

            if (!$bValidadoPaso1) {
                //  Si no se ha pasado por la primera pantalla del registro, se redirecciona al Paso 1 
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action', ['request' => $request,
                            'ARUBaseORM' => $ARUBaseORM]);
            }

            if ($sAccionBT === 'cancelar') {
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action');
            }

            if ($sAccionBT === 'volverPaso2') {
                //  Si se presiona el botón Volver se redirecciona al Paso 1
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action', ['request' => $request,
                            'ARUBaseORM' => $ARUBaseORM]);
            }

            if ($sAccionBT == 'enviarPaso2') {

                /**  Si todo ha ido bien se redirige al tercer paso del formulario. */
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso3Action', ['request' => $request,
                            'ARUBaseORM' => $ARUBaseORM]);
            }//If boton enviar
            
            return array('mensajeBienvenida' => $ARUBaseORM->getMensajeBienvenida(),
                         'mensajeVerificacion' => $ARUBaseORM->getMensajeCodigoVerificacion(),
                         'datosSesionRegistro' => $aDatosSesionRegistro);
            
        } catch (Exception $ex) {

            Utilidades::mostrarError(Utilidades::controlarError($ex)->getMessage());
            return $this->redirectToRoute("_errorPantalla");
        }
    }

    /**
     * Pantalla de registro de usuarios. Paso 3.
     * 
     * @Route("credenciales", name="_registro_usuarios_paso3")
     * @param ARUBaseORM $ARUBaseORM
     * @param Request    $request
     * @return array|Response|RedirectResponse
     * @Template("ARU/registroUsuariosPaso3.html.twig")
     */
    public function formularioRegistroPaso3Action(Request $request, ARUBaseORM $ARUBaseORM) {

        try {

            $aDatosSesionRegistro = $ARUBaseORM->generarDatosSesionRegistroUsuario($request, FALSE);

            //Comprobamos si vuelve de la tercera pantalla del registro
            $sAccionBT = $request->get('accion', FALSE);

            // Se controlan los pasos del formulario por los que se ha pasado durante el registro.
            $bValidadoPaso2 = $request->getSession()->get('ValidadoPaso2', FALSE);

            if (!$bValidadoPaso2) {
                //  Si no se ha pasado por la segunda pantalla del registro, se redirecciona al Paso 1 
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action', ['request' => $request,
                            'ARUBaseORM' => $ARUBaseORM]);
            }

            if ($sAccionBT === 'cancelar') {
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action');
            }

            if ($sAccionBT === 'volverPaso3') {
                //  Si se presiona el botón Volver se redirecciona al Paso 2
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso2Action', ['request' => $request,
                            'ARUBaseORM' => $ARUBaseORM]);
            }

            if ($sAccionBT == 'enviarPaso3') {

                //Si todo ha ido bien se limpian los datos de sesión del usuario.
                $ARUBaseORM->generarDatosSesionRegistroUsuario($request, TRUE);

                Utilidades::mostrarInformacion(ARULiterales::registroUsuarioCorrecto);
                return $this->forward('\App\Controller\ARUController::formularioRegistroPaso1Action');
            }//If boton enviar

            if (Utilidades::esDesarrollo()) {
                Utilidades::mostrarAdvertencia('En desarrollo el correo se redirige a [' . ARULiterales::correoDesarrollo . '] por defecto, '
                        . 'el código a introducir para "' . $aDatosSesionRegistro['identidad']
                        . '" es "' . $ARUBaseORM->obtenerCodigoVerificacion($aDatosSesionRegistro['identidad']) . '"');
            }

            return array( 'mensajeBienvenida' => $ARUBaseORM->getMensajeBienvenida(),
                          'mensajeVerificacion' => $ARUBaseORM->getMensajeCodigoVerificacion(),
                          'datosSesionRegistro' => $aDatosSesionRegistro);
            
        } catch (Exception $ex) {

            Utilidades::mostrarError(Utilidades::controlarError($ex)->getMessage());
            return $this->redirectToRoute("_errorPantalla");
        }
    }

    /**
     * comprobarPaso1

     * Comprueba los datos del Paso 1 del registro de usuarios
     *
     * @Route("api/comprobarRegistroPaso1", name="_registro_usuarios_comprobar_Paso1")
     */
    public function AJAXComprobarRegistroPaso1(Request $request, ARUBaseORM $ARUBaseORM) {

        try {

            //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF.          
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), 'El token del formulario no es valido');
            }
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        $sNumeroSoporteID = $oFechaCaducidad = $sError = '';
        $sManifiestoOposicion = $request->get('manifiestoOposicion', FALSE);

        //Se eliminan los espacios en el Documento Identificativo
        $sDocumentoIdentificativo = $request->get('identidad', FALSE);

        if (!$sDocumentoIdentificativo) {
            $sDocumentoIdentificativo = $request->getSession()->get('identidad', FALSE);
        }

        if ($sDocumentoIdentificativo) {
            $sDocumentoIdentificativo = str_replace(' ', '', $sDocumentoIdentificativo);
        }

        if ($sDocumentoIdentificativo === FALSE) {
            $sMensajeError = sprintf($ARUBaseORM->getError('RUE008'), 'documento identificativo');

            Utilidades::controlarError($sMensajeError, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($sMensajeError);
        }

        //Se eliminan los espacios en el Nº. de soporte del Documento Identificativo
        $sNumeroSoporteID = $request->get('soporteID', FALSE);
        if ($sNumeroSoporteID) {
            $sNumeroSoporteID = str_replace(' ', '', $sNumeroSoporteID);
        }

        //Se eliminan los espacios en la fecha de caducidad del Documento Identificativo        
        $sFechaCaducidadID = $request->get('fechaID', FALSE);
        if ($sFechaCaducidadID) {
            $sFechaCaducidadID = str_replace(' ', '', $sFechaCaducidadID);
        }

        //Si existe $sManifiestoOposicion, han marcado la oposición a la comprobación de datos, por lo que se impide que continue el registro de usuarios.
        if ($sManifiestoOposicion) {
            return Utilidades::respuestaError($ARUBaseORM->getError('RUE012'));
        }

        //Validacion contra el PID FUNCPID_CONSULTADATOSIDENTIDAD
        try {

            $oFechaCaducidad = \DateTime::createFromFormat('dmY H:i:s', Utilidades::cambiarFormatoFecha($sFechaCaducidadID) . ' 00:00:00');
            $aRespuestaDatosIdentidad = $ARUBaseORM->comprobarIdentidadPID($sDocumentoIdentificativo, '', $sNumeroSoporteID, $oFechaCaducidad);

            if ($aRespuestaDatosIdentidad[0] === 'N') {
                Utilidades::controlarError($ARUBaseORM->getError('RUE011'), $ARUBaseORM->getLogger(), 'No se ha validado el documento de identidad', array('respuesta' => $aRespuestaDatosIdentidad));
                return Utilidades::respuestaError($ARUBaseORM->getError('RUE011'));
            }
            
        } catch (Exception $ex) {
            $aResultado = array( 'correoEnviado' => FALSE,
                                 'captcha' => '',
                                 'error' => $ex->getMessage());
            $aResultado['length'] = count($aResultado);

            return Utilidades::respuestaError($ARUBaseORM->getError('RUE020'));
        }

        //Se comprueba el código Captcha introducido por el usuario y el almacenado en sesión para ver si coinciden.
        $sComprobarCaptcha = $ARUBaseORM->comprobarCaptchaRegistroUsuario($request);

        if ($sComprobarCaptcha !== TRUE) {
            Utilidades::controlarError($ARUBaseORM->getError($sComprobarCaptcha), $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ARUBaseORM->getError($sComprobarCaptcha));
        }

        //Se obtienen los correos asociados a una identidad en el caso de que exista en la BD
        $aListadoCorreos = $ARUBaseORM->comprobarCorreosExistentes($sDocumentoIdentificativo, FALSE);

        if (!is_array($aListadoCorreos)) {
            Utilidades::controlarError($aListadoCorreos, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($aListadoCorreos);
        }

        $aResultado = array('listadoCorreos' => $aListadoCorreos,
                            'length' => count($aListadoCorreos),
                            'comprobacionCaptcha' => 'OK'
        );

        //Se establecen los datos del Paso 1 del registro en sesión.
        $request->getSession()->set('identidad', $sDocumentoIdentificativo);
        $request->getSession()->set('soporteID', $sNumeroSoporteID);
        $request->getSession()->set('fechaID', $sFechaCaducidadID);
        $request->getSession()->set('nombre', $aRespuestaDatosIdentidad[0]);
        $request->getSession()->set('apellido1', $aRespuestaDatosIdentidad[1]);
        $request->getSession()->set('apellido2', $aRespuestaDatosIdentidad[2]);
        $request->getSession()->set('fechaNacimiento', $aRespuestaDatosIdentidad[3]);
        $request->getSession()->set('sexo', $aRespuestaDatosIdentidad[8]);

        //Una vez que se ha validado el formulario del Paso 1 se deja constancia en sesión del paso por el mismo.
        $request->getSession()->set('ValidadoPaso1', TRUE);

        return Utilidades::respuestaOK(json_encode($aResultado));
    }

    /**
     * comprobarPaso2
     *
     * Comprueba los datos del Paso 2 del registro de usuarios
     *
     * @Route("api/comprobarRegistroPaso2", name="_registro_usuarios_comprobar_Paso2")
     */
    public function AJAXComprobarRegistroPaso2(Request $request, ARUBaseORM $ARUBaseORM) {
        //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF
        try {
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), 'El token del formulario no es valido');
            }
            
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
            
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        $sTelefonoMovil = $request->get('telefonoMovil', FALSE);
        $sCorreoElectronico = preg_replace('/"/', '', $request->get('correoElectronico', FALSE));
        $sDocumentoIdentificativo = $request->getSession()->get('identidad', FALSE);
        $aResultado = array('length' => 0);

        if (!$sDocumentoIdentificativo) {
            return Utilidades::respuestaError(Utilidades::controlarError($ARUBaseORM->getError('RUE007'), $ARUBaseORM->getLogger())->getMessage());
        }

        //Si es numerico es que ya esta registrado pero no ha terminado 
        //En la validacion se recogen los correos de la BD y se envia al seleccionado
        try {
            $sCorreoElectronico = $ARUBaseORM->calcularCorreoElectronicoValido($sCorreoElectronico, $sDocumentoIdentificativo);
            
        } catch (Exception $ex) {
            $aResultado = array('correoEnviado' => FALSE,
                                'captcha' => '',
                                'error' => $ex->getMessage()
            );

            $ARUBaseORM->getLogger()->addDebug('Error en [' . __METHOD__ . ':' . __LINE__ . ']', $aResultado);

            $aResultado['length'] = count($aResultado);

            return Utilidades::respuestaError($ex->getMessage());
        }

        //Se realiza la validación de los datos del registro
        $sValidarDatosRegistro = $ARUBaseORM->validarDatosRegistroUsuarioPaso2($request, $sCorreoElectronico);

        if ($sValidarDatosRegistro !== TRUE) {
            Utilidades::controlarError($ARUBaseORM->getError($sValidarDatosRegistro), $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ARUBaseORM->getError($sValidarDatosRegistro));
        }


        $aResultado = array('Validar Datos Registro' => $sValidarDatosRegistro,
            'Correo Electronico' => $sCorreoElectronico
        );

        //Se establecen los datos del Paso 2 del registro en sesión.
        $request->getSession()->set('correoElectronico', $sCorreoElectronico);
        $request->getSession()->set('telefonoMovil', $sTelefonoMovil);

        //Una vez que se ha validado el formulario del Paso 2 se deja constancia en sesión del paso por el mismo.
        $request->getSession()->set('ValidadoPaso2', TRUE);

        return Utilidades::respuestaOK(json_encode($aResultado));
    }

    /**
     * comprobarPaso3

     * Comprueba los datos del Paso 3 del registro de usuarios
     *
     * @Route("api/comprobarRegistroPaso3", name="_registro_usuarios_comprobar_Paso3")
     */
    public function AJAXComprobarRegistroPaso3(Request $request, ARUBaseORM $ARUBaseORM) {
        //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF
        try {
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), 'El token del formulario no es valido');
            }
            
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
            
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        //Se realiza la validación de los datos del registro Paso 3
        $sValidarDatosRegistro = $ARUBaseORM->validarDatosRegistroUsuarioPaso3($request);

        if ($sValidarDatosRegistro !== TRUE) {
            Utilidades::controlarError($ARUBaseORM->getError($sValidarDatosRegistro), $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ARUBaseORM->getError($sValidarDatosRegistro));
        }

        //Se establecen los datos del Paso 3 del registro en sesión.
        $sCodigoVerificacion = $request->get('codigoVerificacion', FALSE);
        $sPassword = $request->get('password1', FALSE);
        $request->getSession()->set('codigoVerificacion', $sCodigoVerificacion);
        $request->getSession()->set('password', $sPassword);

        $aResultado = array('Validar Datos Registro 3' => 'OK');
        return Utilidades::respuestaOK(json_encode($aResultado));
    }

    /**
     * obtenerImagenCaptcha
     *
     * Obtiene una imagen captcha y la registra en la sesion del usuario
     *
     * @Route("api/imagen", name="_registro_usuarios_captcha")
     */
    public function AJAXObtenerImagenCaptcha(Request $request, ARUBaseORM $ARUBaseORM, $bDevolverImagen = false) {

        /**
         * Audios Catpcha en espaniol por si se pudieran usar.
         * https://www.phpcaptcha.org/download/
         * 
         * Usando el $captcha->getPhrase() se puede dividir en letras, asignar
         *  a cada letra un sonido, montar un audio completo con las letras y 
         *  posteriormente poner ese audio para que fuera reproducido.
         */
        //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF
        try {
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), '');
            }
            
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
            
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        $captcha = new CaptchaBuilder;
        $captcha->build();
        $request->getSession()->set('verificacionCaptcha', $captcha->getPhrase());

        //Si solo se quiere la imagen de devuelve sola, sino se devuelve una respuesta HTTP
        if ($bDevolverImagen) {
            return $captcha->inline();
        } else {
            $aResultado = array('imagenGenerada' => true,
                                'captcha' => $captcha->inline(),
                                'error' => '',
            );
            $aResultado['length'] = count($aResultado);

            return Utilidades::respuestaOK(json_encode($aResultado));
        }
    }

    /**
     * comprobarIdentificacion
     *
     * comprueba si el usuario existe actualmente y si es asi se recuperan sus correos electronicos para que se registre con esos datos
     *
     * @Route("api/comprobarIdentificacion", name="_registro_usuarios_identificacion")
     */
    public function AJAXComprobarIdentificacion(Request $request, ARUBaseORM $ARUBaseORM) {

        //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF
        try {
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), 'El token del formulario no es valido');
            }
            
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
            
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        $sDocumentoIdentificativo = $request->get('identidad', FALSE);

        //Si la identidad no viene desde el JS se intenta capturar desde la sesión
        if ($sDocumentoIdentificativo === FALSE) {
            $sDocumentoIdentificativo = $request->getSession()->get('identidad', FALSE);
        }
        //Si no se ha podido indetificar la identidad se lanza un mensaje de error controlado
        if ($sDocumentoIdentificativo === FALSE) {
            $sMensajeError = sprintf($ARUBaseORM->getError('RUE008'), 'documento identificativo');

            Utilidades::controlarError($sMensajeError, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($sMensajeError);
        }

        //Se obtienen los correos asociados a una identidad 
        $aListadoCorreos = $ARUBaseORM->comprobarCorreosExistentes($sDocumentoIdentificativo, FALSE);

        if (!is_array($aListadoCorreos)) {
            Utilidades::controlarError($aListadoCorreos, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($aListadoCorreos);
        }

        $aResultado = array('listadoCorreos' => $aListadoCorreos,
                            'length' => count($aListadoCorreos),
        );

        return Utilidades::respuestaOK(json_encode($aResultado));
    }

    /**
     * pedirCodigoVerificacion
     *
     * Solicita el codigo de verificacion a la BBDD a partir de un correo y un documento de identificacion
     *
     * @Route("api/pedirCodigoVerificacion", name="_registro_usuarios_codigo_verificacion")
     */
    public function AJAXPedirCodigoVerificacion(Request $request, ARUBaseORM $ARUBaseORM) {

        //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF
        try {
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), 'El token del formulario no es valido');
            }
            
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
            
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        //Carga de variables
        $sError = '';
        $sIdentidad = $request->getSession()->get('identidad', FALSE);
        $sCorreoElectronico = preg_replace('/"/', '', $request->get('correoElectronico', FALSE));

        $aResultado = array('length' => 0);

        if ($sIdentidad === FALSE || $sCorreoElectronico === FALSE) {
            return Utilidades::respuestaOK(json_encode($aResultado));
        }

        // Si es numerico es que ya esta registrado pero no ha terminado 
        // la validacion se recogen los correos de la BD y se envia al seleccionado
        try {
            $sCorreoElectronico = $ARUBaseORM->calcularCorreoElectronicoValido($sCorreoElectronico, $sIdentidad);
            
        } catch (Exception $ex) {
            $aResultado = array(
                'correoEnviado' => FALSE,
                'error' => $ex->getMessage()
            );

            $ARUBaseORM->getLogger()->addDebug('Error en [' . __METHOD__ . ':' . __LINE__ . ']', $aResultado);

            $aResultado['length'] = count($aResultado);

            return Utilidades::respuestaError($ex->getMessage());
        }

        //Se realiza la validación de los datos del registro
        $sValidarDatosRegistro = $ARUBaseORM->validarDatosRegistroUsuarioPaso2($request, $sCorreoElectronico);

        if ($sValidarDatosRegistro !== TRUE) {
            Utilidades::controlarError($ARUBaseORM->getError($sValidarDatosRegistro), $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ARUBaseORM->getError($sValidarDatosRegistro));
        }

        try {

            //Se obtienen los tipos de permiso
            $bEnviadoCorreo = $ARUBaseORM->pedirCodigoVerificacion($sIdentidad, $sCorreoElectronico, $sError);

            if ($bEnviadoCorreo) {

                $aResultado = array(
                    'correoEnviado' => $bEnviadoCorreo,
                    'error' => $sError,
                );

                return Utilidades::respuestaOK(json_encode($aResultado));
            } else {
                $aResultado = array(
                    'correoEnviado' => $bEnviadoCorreo,
                    'error' => $sError,
                );

                return Utilidades::respuestaError($sError);
            }
            
        } catch (\Exception $ex) {
            $aResultado = array(
                'correoEnviado' => FALSE,
                'error' => $ex->getMessage(),
            );

            $aResultado['length'] = count($aResultado);

            return Utilidades::respuestaError($ex->getMessage());
        }
    }

    /**
     * comprobarCodigoVerificacion
     *
     * Comprueba un codigo de verificacion solicitado y enviado al correo electronico
     *
     * @Route("api/comprobarCodigo", name="_registro_usuarios_comprobar_codigo_verificacion")
     */
    public function AJAXComprobarCodigoVerificacion(Request $request, $bRegistrarUsuario = true, ARUBaseORM $ARUBaseORM) {

        //Comprobar el token CSRF para evitar ataques tipo XSRF/CSRF
        try {
            $submittedToken = $request->request->get('token');
            if ($this->isCsrfTokenValid('aru-form', $submittedToken) === FALSE) {
                throw Utilidades::controlarError('No se ha podido procesar la solicitud.', $ARUBaseORM->getLogger(), 'El token del formulario no es valido');
            }
            
        } catch (\LogicException $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
            
        } catch (\Exception $ex) {
            Utilidades::controlarError($ex, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($ex->getMessage());
        }

        //Inicializacion.
        $sIdentidad = $request->getSession()->get('identidad', FALSE);
        $sCodigoVerificacion = $request->get('codigoVerificacion', FALSE);

        $ARUBaseORM->getLogger()->addDebug('Comprobando codigo de verificacion', array('identidad' => $sIdentidad, 'codigoVerificacion' => $sCodigoVerificacion));

        $sError = '';

        $aResultado = array('length' => 0);

        if ($sIdentidad === FALSE || $sCodigoVerificacion === FALSE) {

            $sError = 'RUE005: ' . $ARUBaseORM->getError('RUE005');
            return Utilidades::respuestaError(Utilidades::controlarError($sError, $ARUBaseORM->getLogger())->getMessage());
        }

        //Se obtienen los tipos de permiso
        $bCodigoCorrecto = $ARUBaseORM->comprobarCodigoVerificacion($sIdentidad, $sCodigoVerificacion, $sError);

        if ($bCodigoCorrecto) {
            if ($bRegistrarUsuario) {

                $aDatosSesionRegistro = $ARUBaseORM->generarDatosSesionRegistroUsuario($request, FALSE);

                try {
                    $bResultadoRegistrarUsuario = $this->registraUsuario($aDatosSesionRegistro, $sError, $ARUBaseORM);

                    if ($bResultadoRegistrarUsuario) {

                        $aResultado['resultado'] = $bResultadoRegistrarUsuario;
                        $aResultado['mensaje'] = html_entity_decode('Se ha completado el registro y se le ha enviado la contrase&ntilde;a por correo electr&oacute;nico.');
                        $aResultado['urlVolver'] = base64_decode($request->getSession()->get('urlRetorno', ''));
                        if (strlen($aResultado['urlVolver']) > 10) {
                            $aResultado['mensaje'] = $aResultado['mensaje'] . html_entity_decode(' En 3 segundos se le enviará automáticamente a la pantalla de preinscripción');
                        }

                        $aResultado['length'] = count($aResultado);
                        
                    } elseif (strlen($sError) > 0) {
                        return Utilidades::respuestaError(Utilidades::controlarError($sError, $ARUBaseORM->getLogger())->getMessage());
                        
                    } else {
                        return Utilidades::respuestaError(Utilidades::controlarError($ARUBaseORM->getError('RUE007'), $ARUBaseORM->getLogger())->getMessage());
                    }
                    
                } catch (Exception $ex) {
                    return Utilidades::respuestaError(Utilidades::controlarError($ex, $ARUBaseORM->getLogger())->getMessage());
                }
            } else {
                $aResultado = array('codigoCorrecto' => $bCodigoCorrecto,
                    'error' => $sError);
            }

            return Utilidades::respuestaOK(json_encode($aResultado));
        } else {
            $aResultado = array('codigoCorrecto' => $bCodigoCorrecto,
                                'error' => $sError);

            Utilidades::controlarError($sError, $ARUBaseORM->getLogger());
            return Utilidades::respuestaError($sError);
        }
    }

    /**
     * comprobarCodigoVerificacion
     *
     * Comprueba un codigo de verificacion solicitado y enviado al correo electronico
     *
     * @Route("/api/registrarUsuario", name="_registro_usuarios_alta")
     */
    public function registraUsuario($aDatosUsuario, &$sError, ARUBaseORM $ARUBaseORM) {

        $sError = '';
        $sClaveUsuario = $aDatosUsuario['password'];

        foreach ($aDatosUsuario as $valor) {
            if ($valor === FALSE) {
                return Utilidades::respuestaError(Utilidades::controlarError($ARUBaseORM->getError('RUE007'),
                                        $ARUBaseORM->getLogger(), 'Existen valores FALSE', $aDatosUsuario)->getMessage());
            }
        }

        try {
            /**
             * La clave del usuario viene por el PLSQL que se llama para dar de alta al usuario
             */
            $bResultado = $ARUBaseORM->guardarUsuarioNuevo($aDatosUsuario, $sClaveUsuario, $sError);
            return $bResultado;
            
        } catch (\Exception $ex) {
            return Utilidades::respuestaError(Utilidades::controlarError($sError, $ARUBaseORM->getLogger())->getMessage());
            return false;
        }
    }

    /**
     * pagina de informacion del numero de soporte
     *
     * @Route("ayuda/numeroSoporte" , name="_ayudaNumeroSoporte", methods={"GET"})
     * @Template("ARU/ayudaNumeroSoporte.html.twig")
     */
    public function paginaInformacionNumeroSoporte() {
        return array();
    }

}