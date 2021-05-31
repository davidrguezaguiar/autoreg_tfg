<?php

// src/Twig/AppExtension.php

namespace App\Service;

use App\Lib\ARULiterales;
use App\Lib\Base\Utilidades;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;
use Twig\TwigFilter;

class extraTwigExtension extends AbstractExtension {

    public $container;

    public function __construct($container) {
        $this->container = $container;
        //parent::__construct();
    }

    public function getFunctions() {
        return [
            new TwigFunction('depurarIP', [$this, 'depurarIP']),
            new TwigFunction('esIP', [$this, 'esIP']),
            new TwigFunction('esObjeto', [$this, 'esObjeto']),
            new TwigFunction('literal', [$this, 'literal']),
            new TwigFunction('literalGenero', [$this, 'literalGenero']),
            new TwigFunction('literalARU', [$this, 'literalARU']),
            new TwigFunction('fechaPDF', [$this, 'fechaPDF']),
            new TwigFunction('parametro', [$this, 'parametro']),
            new TwigFunction('hora', [$this, 'hora']),
            new TwigFunction('getTratamiento', [$this, 'getTratamiento']),
            new TwigFunction('getTratamientoDoctor', [$this, 'getTratamientoDoctor']),
            new TwigFunction('formatoFecha', [$this, 'formatoFecha']),
            new TwigFunction('quitarPuntos', [$this, 'quitarPuntos']),
            new TwigFunction('incluirComillas', [$this, 'incluirComillas']),
            new TwigFunction('generoTutoria', [$this, 'generoTutoria']),
            new TwigFunction('generoRol', [$this, 'generoRol']),
            new TwigFunction('generoVinculacion', [$this, 'generoVinculacion']),
            new TwigFunction('generoProfesor', [$this, 'generoProfesor']),
            new TwigFunction('generoDirector', [$this, 'generoDirector']),
            new TwigFunction('LiteralesPremios', array($this, 'literalesPremios')),
            new TwigFunction('contienePalabra', array($this, 'contienePalabra')),
            new TwigFunction('preg_reemplazo', array($this, 'preg_reemplazo')),
            new TwigFunction('reemplazoCategoriaTUCU', array($this, 'reemplazoCategoriaTUCU')),
        ];
    }

    public function depurarIP($mensaje, $IP, $bExit = FALSE, $request = NULL, $sTipoDeMensaje = 'warn', $debugBackTrace = FALSE) {
        return Utilidades::depurarIP($mensaje, $IP, $bExit = FALSE, $request = NULL, $sTipoDeMensaje = 'warn', $debugBackTrace = FALSE);
    }

    public function esIP($IP) {
        return Utilidades::depurarEsIP($IP);
    }

    public function fechaPDF() {

        return strtolower(Utilidades::obtenerFechaActual());
    }

    public function formatoFecha($fechaCorta) {

        $oFecha = \DateTime::createFromFormat('d/m/y', $fechaCorta);
        //Se obtiene string del mes en espa
        $sMesSpain = Utilidades::obtenerMes($oFecha->format('m'));

        //Pasamos el string format con el '%s' para poder incorporar el mes espa
        // en el.
        $stringFecha = $oFecha->format('j \d\e \%\s \d\e Y');

        return sprintf($stringFecha, $sMesSpain);
    }

    public function hora() {

        return date("H:i:s");
    }

    public function esObjeto($objeto) {
        $resultado = FALSE;
        if (is_object($objeto)) {
            $resultado = true;
        }
        return $resultado;
    }

    /**
     * 
     * Devuelve el literal de la clase Literales definida 
     * 
     * @param type $literalBuscar
     * @return string
     */
    public function literal($literalBuscar) {

        $literales = new ARULiterales();
        return $literales->getConstante($literalBuscar);
    }

    /**
     * 
     * @param string $constante Indica la constante que contiene el parametro '%s'
     * @param string $constanteGenero Indica el genero a parametrizar, matriculado|director|etc.
     * @param string $generoUsuario Genero del usuario
     * @return string
     */
    public function literalGenero( $constante, $constanteGenero, $generoUsuario ){
        $literales = new ARULiterales();
        $textoConstante = $literales->getConstante($constante);
        $textoGenero = $literales->getConstante($constanteGenero);
        return sprintf($textoConstante, Utilidades::textoGenero($textoGenero, $generoUsuario));
    }
    /**
     * 
     * Devuelve el literal de ARULiterales
     * 
     * @param type $literalBuscar
     * @return string
     */
    public function literalARU($literalBuscar) {

        $literales = new ARULiterales();
        return $literales->getConstante($literalBuscar);
    }

    /**
     * Devuelve un parametro de configuracion de symfony
     * @param string $nombreParametro
     * @return mixed
     */
    public function parametro($nombreParametro) {
        return $this->container->getParameter($nombreParametro);
    }

    /**
     * 
     * @param string $sTextoAnalizar
     * @param boolean $bAbreviado Indica si se quiere abreviado D.|Dña.
     * @param boolean $bIncluirDoct
     * @return type
     */
    public function getTratamiento($sTextoAnalizar, $bAbreviado = true, $bIncluirDoct = FALSE) {
        return Utilidades::getTratamientoPersona($sTextoAnalizar, $bAbreviado, $bIncluirDoct);
    }

    /**
     * Devuelve el tratamiento para doctoras y doctores , Dr. D. y Dra. Dña.
     * @param string $sTextoAnalizar
     * @return string
     */
    public function getTratamientoDoctor($sTextoAnalizar) {
        return Utilidades::getTratamientoPersona($sTextoAnalizar, true, true);
    }

    
    /**
     * 
     * Se supone que siempre viene en masculino y se hace el tratamiento
     *  para pasarlo a femenino
     * 
     * @param string $rol [Presidente|Vocal|Secretario]
     * @param string $genero [M|V] Mujer|Varon
     */
    public function generoRol($rol, $genero) {


        switch ($genero) {

            case 'M':

                switch (trim($rol)) {
                    case 'Presidente':
                        return 'Presidenta';
                    case 'Secretario':
                        return 'Secretaria';
                    case 'Vocal':
                        return 'Vocal';
                }

                break;

            //Suponemos que siempre viene en masculino
            case 'V':
                return $rol;
        }
    }

    /**
     * 
     * Se eliminan los puntos "extra" que llegan  del campo tesis en XML
     * 
     */
    public function quitarPuntos($tesis) {

        $sTesis = \Preg_replace('/\h*\.+\h*(?!.*\.)/', '', $tesis);


        return sprintf($sTesis);
    }

    /**
     * 
     * Se incluyen las comillas al texto que llegan  del campo tesis en XML
     * 
     */
    public function incluirComillas($sTesis) {

        //Se incluye las comillas iniciales
        if (!(preg_match('/^"/', $sTesis))) {
            $sTesis = '"' . $sTesis;
        }

        //Se incluye las comillas finales
        if (!(preg_match('/"$/', $sTesis))) {
            $sTesis = $sTesis . '"';
        }


        return $sTesis;
    }

    /**
     * Se aplica el genero tutor/tutora segun valor de la BBDD  [M|V]
     * 
     * @param string $genero [M|V] Mujer|Varon
     */
    public function generoTutoria($genero) {

        switch ($genero) {

            case 'M':
                return 'tutora';
            case 'V':
                return 'tutor';
        }
    }

    /**
     * 
     * Se aplica el genero de vinculado/vinculada según tratamiento del xml - [Don|Doña]
     * 
     * @param string $genero [Vinculado|Vinculada] 
     */
    public function generoVinculacion($genero) {

        switch ($genero) {

            case 'V':
                return 'Vinculado';
            case 'M':
                return 'Vinculada';
        }
    }

    /**
     *  
     * Se aplica el genero de profesor/profesora según tratamiento del xml - [V|M]
     * 
     * @param string $genero [profesor|profesora] 
     */
    public function generoProfesor($genero) {

        switch ($genero) {

            case 'V':
                return 'el profesor';
            case 'M':
                return 'la profesora';
        }
    }

    /**
     *  
     * Se aplica el genero de director/directora según tratamiento del xml - [V|M]
     * 
     * @param string $genero [director|directora] 
     */
    public function generoDirector($genero) {

        switch ($genero) {

            case 'V':
                return 'Director';
            case 'M':
                return 'Directora';
        }
    }

    //comprueba si un texto esta dentro de otro, case-INsensitive
    public function contienePalabra($sTexto, $sTextoBuscar){
        return preg_match('/'.$sTextoBuscar.'/i', $sTexto);
    }
    
    public function preg_reemplazo($pattern, $replacement, $subject, $limit=-1, $count=null){
            
        return preg_replace($pattern, $replacement, $subject, $limit, $count);
        
    }

    public function reemplazoCategoriaTUCU($sTexto){
        if ( $sTexto == 'TU' ){
            return ' (TITULAR DE UNIVERSIDAD)';
        }
        
        if( $sTexto ==  'CU'){
            return ' (CATEDRÁTICO DE UNIVERSIDAD)';
        }
        
        return '';
    }
    
    
    //ampliar test para Twig
    public function getTests() {
        return array(
            new TwigTest('instanceof', array($this, 'isInstanceOf')),
            new TwigTest('object', array($this, 'isObject')),
            
        );
    }

    //Comprueba si la variable es una instancia de un objecto
    public function isInstanceOf($var, $instance) {
        $reflexionClass = new \ReflectionClass($instance);
        return $reflexionClass->isInstance($var);
    }

    //comprueba si es un objecto
    public function isObject($var) {
        return is_object($var);
    }
    
    


}
