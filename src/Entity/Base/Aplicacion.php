<?php
/**
 * Created by ULPGC
 * Define los datos de la aplicacion
 */

namespace App\Entity\Base;


class Aplicacion {


    private $codigoAplicacion;
    private $nombreAplicacion;
    private $telefonoContacto;
    private $mailContacto;

    /**
     * Aplicacion constructor.
     *
     * @param $codigoAplicacion
     * @param $nombreAplicacion
     * @param $telefonoContacto
     * @param $mailContacto
     */
    public function __construct ( $codigoAplicacion , $nombreAplicacion , $telefonoContacto , $mailContacto ) {
        $this->codigoAplicacion = $codigoAplicacion;
        $this->nombreAplicacion = $nombreAplicacion;
        $this->telefonoContacto = $telefonoContacto;
        $this->mailContacto     = $mailContacto;
    }

    /**
     * @return mixed
     */
    public function getCodigoAplicacion () {
        return $this->codigoAplicacion;
    }

    /**
     * @param mixed $codigoAplicacion
     */
    public function setCodigoAplicacion ( $codigoAplicacion ): void {
        $this->codigoAplicacion = $codigoAplicacion;
    }

    /**
     * @return mixed
     */
    public function getNombreAplicacion () {
        return $this->nombreAplicacion;
    }

    /**
     * @param mixed $nombreAplicacion
     */
    public function setNombreAplicacion ( $nombreAplicacion ): void {
        $this->nombreAplicacion = $nombreAplicacion;
    }

    /**
     * @return mixed
     */
    public function getTelefonoContacto () {
        return $this->telefonoContacto;
    }

    /**
     * @param mixed $telefonoContacto
     */
    public function setTelefonoContacto ( $telefonoContacto ): void {
        $this->telefonoContacto = $telefonoContacto;
    }

    /**
     * @return mixed
     */
    public function getMailContacto () {
        return $this->mailContacto;
    }

    /**
     * @param mixed $mailContacto
     */
    public function setMailContacto ( $mailContacto ): void {
        $this->mailContacto = $mailContacto;
    }




}