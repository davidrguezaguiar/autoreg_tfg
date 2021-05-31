<?php

/*
 * Conjunto de literales para ARU, Aplicacion de registro de usuarios
 */

namespace App\Lib;

/**
 * Description of RELiterales
 *
 * @author iojed
 */
class ARULiterales {
    const RUE001 = "El documento identificativo ya se encuetra registrado en la base de datos.";
    const RUE002 = "Su código de usuario ya existe, no se puede volver a registrar. Para autenticarse en MiULPGC debe entrar con el código de usuario %s y la  contraseña correspondiente";
    const RUE003 = "El documento identificativo con longitud err&oacute;nea.";
    const RUE004 = "Ha habido un error al enviar el correo electr&oacute;nico, intentelo de nuevo m&acute;s tarde.";
    const RUE005 = "Debe introducir el c&oacute;digo de verificaci&oacute;n y el documento de identificaci&oacute;n.";
    const RUE006 = "El c&oacute;digo '%s' no es v&aacute;lido para el documento '%s'.";
    const RUE007 = "No se ha podido procesar la solicitud, intentelo de nuevo m&aacute;s tarde.";
    const RUE008 = "El parametro '%s' no es válido.";
    const RUE009 = "El código captcha introducido es incorrecto.";
    const RUE010 = "Debe seleccionar el tipo de validación del documento identificativo.";
    const RUE011 = "No se puede validar el documento con los datos introducidos, compruébelos y vuelva a intentarlo.";
    const RUE012 = "Si no da su consentimiento para la consulta de sus datos de identidad, no es posible realizar el alta de usuario por esta vía. Deberá ponerse en contacto con XXXXXXXXX y aportar el documento de acreditación de identidad que se le requiera.";    
    const RUE013 = "El correo electrónico introducido no es válido, compruébelo y vuelva a intentarlo.";    
    const RUE014 = "El correo electrónico introducido y el correo de confirmación no coinciden, compruébelo y vuelva a intentarlo.";       
    const RUE015 = "El número de teléfono introducido no es válido, compruébelo y vuelva a intentarlo.";    
    const RUE016 = "No se ha podido validar el código captcha introducido por el usuario.";    
    const RUE017 = "No se ha podido validar el código captcha en la sesión de usuario.";  
    const RUE018 = "Todos los campos del formulario son obligatorios.";    
    const RUE019 = "La contraseña de verificación no coincide con la contraseña introducida.";
    const RUE020 = "En este momento no se puede realizar la validación del documento. Por favor, inténtelo más tarde.";
    
    
    const mensajeUsuarioYaRegistrado  = "ustedyatieneregistroparaaccederamiulpgcsinorecuerdalaclavehagarecuperacindecontrasea";
    const mensajeUsuarioYaRegistrado2 = "ustedyaestregistrado";  
    
    
    const TITULO_PANTALLA_LOPD    = 'Información sobre protección de datos';    
    const tituloPantallaInicial   = "Registro de usuarios";
    const correoDesarrollo        = "david.rodriguez@ulpgc.es"; 
    const registroUsuarioCorrecto = "El registro se ha realizado correctamente."; 
    
    //Texto para el registro de nuevo usuario en la tabla.
    const TEXTO_COMENTARIO_CREACION_USUARIO = "Inserción procedente del Portal Intermedio de la Sede Electrónica";
    
    //Texto informativo de la plataforma intermediacion de datos
    const TEXT_INFORMACION_PID = 'La Universidad de Las Palmas de Gran Canaria, conforme al artículo 28 de la Ley 39/2015, de 1 de octubre, del Procedimiento Administrativo Común, modificada por la disposición final duodécima de la Ley Orgánica 3/2018, de 5 de diciembre, de Protección de Datos Personales y garantía de los derechos digitales, procederá a verificar los datos de identidad necesarios para la identificación electrónica salvo que usted se oponga expresamente a dicha verificación. En este caso, deberá aportar la documentación acreditativa.';    
    const TEXT_PID_DOCUMENTO_IDENTIDAD = 'Manifiesto mi oposición a que la Universidad consulte los datos acreditativos del documento de identidad.';    
    const ABREVIATURA_SI = 'S';
    const ABREVIATURA_NO = 'N';
    
    /**
     * Devuelve todas las constantes definidas en la clase
     * @return array
     */
    private function getAllConstants() {
        $reflectionClass = new \ReflectionClass($this);
        return $reflectionClass->getConstants();
    }

    /**
     * 
     * @param string $constante
     * @return string
     */
    public function getConstante($constante) {
        if (key_exists(strtoupper($constante), $this->getAllConstants())) {
            return $this->getAllConstants()[strtoupper($constante)];
        } else {
            return '%%% NO EXISTE LA CONSTANTE [' . $constante . '] %%%%';
        }
    }
    

}
