<?php

/**
 * File:    UlpgcPDF.php
 * User:    ULPGC
 * Project: GENERICO
 * pagina A4 210x297mm
 */

namespace App\Lib\PDF;

use App\Lib\Base\Utilidades;

class UlpgcPDF extends \TCPDF {

    private $htmlCabecera;
    private $htmlPie;
    private $numeracionPagina;

    /**
     * UlpgcPDF constructor.
     */
    public function __construct() {



        parent::__construct();
        $this->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $this->numeracionPagina = false;
    }

    public function Header() {
        if (!empty($this->getHtmlCabecera())) {
            $this->setPrintHeader(TRUE);
            $this->writeHTML($this->getHtmlCabecera());
        } else {
            $this->writeHTML('vACOPpoii');
        }
    }

    public function Footer() {
        if (!empty($this->getHtmlPie())) {
            $this->setPrintFooter(TRUE);
            $this->writeHTML($this->getHtmlPie());
            if ($this->getNumeracionPagina()) {
                parent::Footer();
            }
        } else {
            parent::Footer();
        }
    }

    /**
     * @return mixed
     */
    public function getHtmlCabecera() {
        return $this->htmlCabecera;
    }

    /**
     * @param mixed $htmlCabecera
     */
    public function setHtmlCabecera($htmlCabecera) {
        $this->htmlCabecera = $htmlCabecera;
    }

    /**
     * @return mixed
     */
    public function getHtmlPie() {
        return $this->htmlPie;
    }

    /**
     * @param mixed $htmlPie
     */
    public function setHtmlPie($htmlPie) {
        $this->htmlPie = $htmlPie;
    }

    public function getNumeracionPagina() {
        return $this->numeracionPagina;
    }

    public function setNumeracionPagina($numeracionPagina) {
        $this->numeracionPagina = $numeracionPagina;
    }

    /**
     * Poner en regular la oPDF actual
     * @param string $fontFamily Indica la fuenta 
     */
    public function setNormal($fontFamily = FALSE) {
        //Determinamos el tipo de familia fuente actual 
        if ($fontFamily === FALSE) {
            $fontFamily = $this->getFontFamily();
        }

        $this->SetFont($fontFamily, 'R');
    }

    /**
     * Poner negrita en la oPDF actual
     * @param string $fontFamily Indica la fuenta 
     */
    public function setNegrita($fontFamily = FALSE) {
        //Determinamos el tipo de familia fuente actual 
        if ($fontFamily === FALSE) {
            $fontFamily = $this->getFontFamily();
        }

        $this->SetFont($fontFamily, 'B');
    }

    /**
     * Poner subrayado en la oPDF actual
     * @param type $fontFamily
     */
    public function setSubrayado($fontFamily = FALSE) {
        //Determinamos el tipo de familia fuente actual 
        if ($fontFamily === FALSE) {
            $fontFamily = $this->getFontFamily();
        }

        $this->SetFont($fontFamily, 'U');
    }
    
    /**
     * 
     * @param int $w Tamanio del salto linea, por defecto automatico
     */
    public function setSaltoLinea($w = 0) {
        $this->MultiCell($w, 0, '');
    }

    /**
     * 
     * @param string $sTexto
     */
    public function textJustificacion($sTexto) {
        $this->writeHTML('<span style="text-align:justify; line-height: normal;">' . $sTexto . '</span>');
    }

    /**
     * 
     * @param string $sTexto
     */
    public function textIzquierda($sTexto) {
        $this->writeHTML('<span style="text-align:left; line-height: normal;">' . $sTexto . '</span>');
    }

    /**
     * 
     * @param string $sTexto
     */
    public function textDerecha($sTexto, $bSaltoLinea = true) {
        $this->writeHTML('<span style="text-align:right;">' . $sTexto . '</span>', $bSaltoLinea);
    }

    /**
     * 
     * @param string $sTexto
     * @param char $alineacion [L|J] Indica alineacion del texto izq o justificado
     */
    public function texto($sTexto, $alineacion = 'L') {
        switch ($alineacion) {
            case 'L':
                $this->textIzquierda($sTexto);
                break;
            case 'J':
                $this->textJustificacion($sTexto);
                break;
            case 'R':
                $this->textDerecha($sTexto);
                break;
        }
    }

    /**
     * Estos margenes se incluyen poorque no esta implementado el CSS con margin 
     *  ni paddin. 
     *  Por lo que hay que definir los espacios de separacion de esta manera, 
     *  donde la primera posicion del array de cada tag, (0) es antes del taghtml
     *  y la siguiente posicion (1) es despues del tagHTML, dentro de cada uno
     *  un array donde 'h' es la distacia y 'n' las repeticiones por ejemplo:
     *  
     *  'p' => array(0 => array('h' => 2, 'n' => 3), 1 => array('h' => 4, 'n' => 3)
     * 
     *  indica que para <p> antes del tag se pone una distancia de 2x3 y al final del tag (</p>) de 4x3
     * 
     */
    public function incluirMargenesHTML() {
        $this->setHtmlVSpace(
                array('p' => array(0 => array('h' => 2, 'n' => 3), 1 => array('h' => 2, 'n' => 3)),
                    'img' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
                    'ul' => array(0 => array('h' => 1, 'n' => 0), 1 => array('h' => 1, 'n' => 0)),
                    'ol' => array(0 => array('h' => 2, 'n' => 3), 1 => array('h' => 2, 'n' => 3))));
    }

    /**
     * Esto siempre se esta poniendo al crear varios pdfs asi que lo ponemos ya
     *  aqui para ser llamado directamente cuando se quiera
     */
    public function prepararPDF() {

        $this->SetAutoPageBreak(FALSE);
        $this->resetHeaderTemplate();
        $this->setPrintFooter(TRUE);
        $this->setPrintHeader(FALSE);
        $this->setHeaderMargin(10);
        $this->setFooterMargin(20);
        $this->setNumeracionPagina(TRUE);

        //Ss cargan los margenes HTML predefinidos
        $this->incluirMargenesHTML();
    }

    function escribirFila3Cabecera($columna1, $columna2, $columna3, $opciones = []) {
        
        $opciones = ['borde' => array('TB' => array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)))];
        $opciones['alineacion'] =  'C';
        $opciones['alto'] =  5;
        
        $this->escribirFila3($columna1, $columna2, $columna3,  $opciones);
        
    }
    
    
    /**
     * Escribe una fila en tres columnas se pueden poner opciones para determinar
     *  las diferentes opciones que hay en multicell.
     * @param string $columna1
     * @param string $columna2
     * @param srting $columna3
     * @param array $opciones Array con las siguientes opciones:
     *                      'anchoColumnas' => array('columna1' => int, 'columna2' => int , 'columna3' => int),
     *                      'borde' => [1|0] default(0),
     *                      'alineacion' => [C|L|R] default(L),
     *                      'relleno' => [0|1] default(0),
     *                      'alto' => int default(10), //Alto de la celda por defecto
     *                      'reseth' => [true|false] default(true), //Resetea el alto de la celda anterior
     *                      'ishtml' => [true|false] default(false), //De uso interno.
     *                      'autopadding' => [true|false] default(true), 
     *                      'maxh' => int default(0), //Tiene que ser mayor o igual al 'alto' y menor al margen del pie de pagina
     *                      'valign' => [T|M|B] default(T), //Requiere poner 'maxh'
     *                      '$fitcell' => [true|false] default(false), //Ajusta el texto a la celda
     * 
     */
    function escribirFila3($columna1, $columna2, $columna3, $opciones = []) {

        $aDimensiones = $this->getPageDimensions();
        
        //Calculamos el tamnio equitativo para las columnas
        $anchoPaginaUtil = $aDimensiones['wk'] - $aDimensiones['lm'] - $aDimensiones['rm'];
        
        //Cargamos todas las variables con el mismo dato
        $tamanioColumna1 = $tamanioColumna2 = $tamanioColumna3 = round($anchoPaginaUtil/3, 0);
        
        //Comprobamos si se ha definido en las opciones el ancho de las columnas
        if ( key_exists('anchoColumnas', $opciones ) ){
            //Incluimos los anchos definidos, sino esta se cogen por defecto
            $tamanioColumna1 = key_exists('columna1', $opciones['anchoColumnas']) ? $opciones['anchoColumnas']['columna1'] : $tamanioColumna1;
            $tamanioColumna2 = key_exists('columna2', $opciones['anchoColumnas']) ? $opciones['anchoColumnas']['columna2'] : $tamanioColumna2;
            $tamanioColumna3 = key_exists('columna3', $opciones['anchoColumnas']) ? $opciones['anchoColumnas']['columna3'] : $tamanioColumna3;
        }
        
        $nBorde = key_exists('borde', $opciones) ? $opciones['borde']  :  0;
        $sAlineacion = key_exists('alineacion', $opciones) ? $opciones['alineacion']  : 'L';
        $nColorFondo = key_exists('relleno', $opciones) ? $opciones['relleno']  : 0;
        $nAlto= key_exists('alto', $opciones) ? $opciones['alto']  : 10;
        $reseth = key_exists('reseth', $opciones) ? $opciones['reseth']  : true;
        $stretch = key_exists('stretch', $opciones) ? $opciones['stretch']  : 0;
        $ishtml= key_exists('ishtml', $opciones) ? $opciones['ishtml']  : false;
        $autopadding = key_exists('autopadding', $opciones) ? $opciones['autopadding']  : true;
        $maxh= key_exists('maxh', $opciones) ? $opciones['maxh']  : 0;
        $valign= key_exists('valign', $opciones) ? $opciones['valign']  : 'T';
        $fitcell= key_exists('fitcell', $opciones) ? $opciones['fitcell']  : false;
        
        $negritasColumnas= key_exists('negritas', $opciones) ? $opciones['negritas']  : array();
        
        
        
        $yAux = $yInicial = $this->GetY();
        $xAux = $xInicial = $this->GetX();

        //Se debe comprobar con !== debido a que si esta en la primera posicion 
        // del arrray seria cero y eso es falso hermano
        if ( array_search('1',$negritasColumnas ) !== FALSE){
            $this->setNegrita();
        }
        $this->MultiCell($tamanioColumna1 , $nAlto, $columna1, $nBorde, $sAlineacion , $nColorFondo, 1, $xAux, $yInicial,$reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell);
        
        $this->setNormal();
        $xAux = $xAux + $tamanioColumna1 ;
        if ($yAux < $this->getY()) {
            $yAux = $this->GetY();
        }
        
        //Se debe comprobar con !== debido a que si esta en la primera posicion 
        // del arrray seria cero y eso es falso hermano
        if ( array_search('2',$negritasColumnas ) !== FALSE){
            $this->setNegrita();
        }
        
        $this->MultiCell($tamanioColumna2 , $nAlto, $columna2, $nBorde, $sAlineacion , $nColorFondo, 1, $xAux, $yInicial,$reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell);
        $this->setNormal();
        
        $xAux = $xAux + $tamanioColumna2 ;
        if ($yAux < $this->getY()) {
            $yAux = $this->GetY();
        }
    
        //Se debe comprobar con !== debido a que si esta en la primera posicion 
        // del arrray seria cero y eso es falso hermano
        if ( array_search('3',$negritasColumnas ) !== FALSE){
            $this->setNegrita();
        }
        $this->MultiCell($tamanioColumna3 , $nAlto, $columna3, $nBorde, $sAlineacion , $nColorFondo, 1, $xAux, $yInicial,$reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell);
        $this->setNormal();

        if ($yAux < $this->getY()) {
            $yAux = $this->GetY();
        }
        
        $this->setY($yAux);
        
    }

    /**
     * Escribe el nombre  de departamento para el PDF del procedimiento 
     *  14. Certificado de Docencia en Doctorado
     * @param string $texto
     */
    public function escribirDepartamentoDoceciaDoctorado($texto){
        
        $opcionesColumnas = array('anchoColumnas' => array('columna1' => 30, 'columna2' => 100, 'columna3'=> 0) );

        $this->escribirFila3('Departamento:' , $texto, null, $opcionesColumnas );
        
    }
    /**
     * Escribe el nombre  del bienio para el PDF del procedimiento 
     *  14. Certificado de Docencia en Doctorado
     * @param string $texto
     */
    public function escribirBienioDoceciaDoctorado($texto){
        
        $opcionesColumnas = array('anchoColumnas' => array('columna1' => 15, 'columna2' => 100, 'columna3'=> 0) );

        $this->escribirFila3('Bienio:' , $texto, null, $opcionesColumnas );
        
    }
    /**
     * Escribe el nombre  del programa para el PDF del procedimiento 
     *  14. Certificado de Docencia en Doctorado
     * @param string $texto
     */
    public function escribirProgramaDoceciaDoctorado($texto){
        
        $opcionesColumnas = array('anchoColumnas' => array('columna1' => 24, 'columna2' => 100, 'columna3'=> 0) );
        $opcionesColumnas['negritas'] = array('2');
        $this->escribirFila3('Programa:' , $texto, null, $opcionesColumnas );
        
    }
    /**
     * Escribe el nombre de las cabeceras de la tabla de cursos para el PDF del procedimiento 
     *  14. Certificado de Docencia en Doctorado
     * @param string $texto
     */
    public function escribirCabeceraTablaDoceciaDoctorado(){
        
        $opcionesColumnas= array('anchoColumnas' => array('columna1' => 125, 'columna2' => 35, 'columna3'=> 18) );
        $this->setSubrayado();
        $this->escribirFila3('Cursos' , 'Tipo', 'Créditos', $opcionesColumnas );
        $this->setNormal();
    }
    
    /**
     * Escribe los cursos para el PDF del procedimiento 
     *  14. Certificado de Docencia en Doctorado
     * @param string $texto
     */
    public function escribirCursoDoceciaDoctorado($sCurso, $sTipo, $sCreditos){
        
        $opcionesColumnas= array('anchoColumnas' => array('columna1' => 125, 'columna2' => 35, 'columna3'=> 18) );
        $this->escribirFila3($sCurso, $sTipo, $sCreditos, $opcionesColumnas );
        
    }
    
    /**
     * 
     * Escribe el pie de pagina con la fecha actual para los PDF, se realiza tambien 
     * un ln de 10.
     * 
     * @param string $textoPIE Debe ser compatible con sprintf con un parametro el cual sera la fecha, 
     *                          
     */
    public function escribirPieFecha($textoPIE){
        $this->ln(10);
        $this->SetFontSize('12px');
        //Calculamos el tamnio equitativo para las columnas
        $this->MultiCell(0, 0, sprintf($textoPIE, strtolower(Utilidades::obtenerFechaActual())), 0,'L');
    }
}
