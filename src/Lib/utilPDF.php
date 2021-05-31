<?php

/*
 *
 * Metodo de para la creacion de PDFs
 * 
 */

namespace App\Lib;

use App\Lib\PDF\UlpgcPDF;

/**
 * Description of utilPDF
 *
 * @author iojed
 */
class utilPDF extends UlpgcPDF{
    public function __construct() {
        parent::__construct();
    }
}
