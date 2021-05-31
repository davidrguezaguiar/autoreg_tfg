<?php

/**
 * File:    UsuarioSuplantado.php
 * User:    ULPGC
 * Project: symfony4
 */

namespace App\Lib\Base;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\Types\Boolean;
use Serializable;
use Symfony\Component\Security\Core\User\UserInterface;

class UsuarioSuplantado {

    protected $dni;
    protected $nombreCompleto;
    protected $aRolesSuplantados;
    protected $aRolesImpostor;
    protected $sDniImpostor;
    protected $sAplicacionSuplantada;
    private $adnipa;

    /**
     * Nombre del usuario
     *
     * @var string
     */
    private $NombreUsuario;

    /**
     * Primera apellido del usuario
     *
     * @var string
     */
    private $Apellido1Usuario;

    /**
     * Segundo apellido del usuario
     *
     * @var string
     */
    private $Apellido2Usuario;

    /** IP desde la que se ha conectado el usuario */
    private $ipConexion;

    /**
     *
     * @var string
     */
    private $telefono;

    /**
     *
     * @var string
     */
    private $email;
    private $sTratamiento;
    private $sGenero;
    private $sArticuloGenero;

    /**
     *
     * @author <david.rodriguez@ulpgc.es>
     * @since Se incluye Tipo Documento y descripcion proporcionado por la BD en la carga del usuario
     * @see \App\Orm\Base\PermisosUsuarioOrm obtenerNombreApellidoBD
     */
    private $tipoDocumento;
    private $denomTipoDocumento;
    private $letraDocumento;
    private $documentoCompleto;

    /**
     * UsuarioSuplantado constructor.
     *
     * @param $dni
     * @param $nombreCompleto
     * @param $aRolesSuplantados
     * @param $aRolesImpostor
     * @param $sDniImpostor
     * @param $sAplicacionSuplantada
     */
    public function __construct($dni, $nombreCompleto, $aRolesSuplantados, $aRolesImpostor, $sDniImpostor, $sAplicacionSuplantada) {
        $this->dni = $dni;
        $this->nombreCompleto = $nombreCompleto;
        $this->aRolesSuplantados = $aRolesSuplantados;
        $this->aRolesImpostor = $aRolesImpostor;
        $this->sDniImpostor = $sDniImpostor;
        $this->sAplicacionSuplantada = $sAplicacionSuplantada;
    }

    /**
     * Get adnipa
     *
     * @return string
     */
    public function getAdnipa() {
        return $this->dni;
    }

    public function setAdnipa($adnipa) {
        $this->adnipa = $adnipa;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDni() {
        return $this->dni;
    }

    /**
     * @param mixed $dni
     */
    public function setDni($dni) {
        $this->dni = $dni;
    }

    /**
     * @return mixed
     */
    public function getNombreCompleto() {
        return $this->nombreCompleto;
    }

    /**
     * @param mixed $nombreCompleto
     */
    public function setNombreCompleto($nombreCompleto) {
        $this->nombreCompleto = $nombreCompleto;
    }

    /**
     * @return mixed
     */
    public function getRolesSuplantados() {
        return $this->aRolesSuplantados;
    }

    /**
     * @param mixed $aRolesSuplantados
     */
    public function setRolesSuplantados($aRolesSuplantados) {
        $this->aRolesSuplantados = $aRolesSuplantados;
    }

    /**
     * @return mixed
     */
    public function getRolesImpostor() {
        return $this->aRolesImpostor;
    }

    /**
     * @param mixed $aRolesImpostor
     */
    public function setRolesImpostor($aRolesImpostor) {
        $this->aRolesImpostor = $aRolesImpostor;
    }

    /**
     * @return mixed
     */
    public function getDniImpostor() {
        return $this->sDniImpostor;
    }

    /**
     * @param mixed $sDniImpostor
     */
    public function setDniImpostor($sDniImpostor) {
        $this->sDniImpostor = $sDniImpostor;
    }

    /**
     * @return mixed
     */
    public function getAplicacionSuplantada() {
        return $this->sAplicacionSuplantada;
    }

    /**
     * @param mixed $sAplicacionSuplantada
     */
    public function setAplicacionSuplantada($sAplicacionSuplantada) {
        $this->sAplicacionSuplantada = $sAplicacionSuplantada;
    }

    /**
     * @return mixed
     */
    public function getIpConexion() {
        return $this->ipConexion;
    }

    /**
     * @param mixed $ipConexion
     */
    public function setIpConexion($ipConexion) {
        $this->ipConexion = $ipConexion;
    }

    /**
     * Devuelve el nombre completo ordenado por nombre apellido sin coma
     *
     * @return string Nombre, Apellido1 Apellido2
     */
    public function nombreApellido() {
        return $this->getNombre() . ' ' . $this->getApellido1() . ' ' . $this->getApellido2();
    }

    /**
     * Devuelve el nombre completo ordenado por apellido1 apellido2, nombre (con coma )
     *
     * @return string Apellido1 Apellido2, Nombre
     */
    public function apellidoNombre() {
        return $this->getApellido1() . ' ' . $this->getApellido2() . ', ' . $this->getNombre();
    }

    /**
     *
     * @return string
     */
    public function getTelefono() {
        return $this->telefono;
    }

    /**
     *
     * @return string
     */
    public function getEmail() {
        return $this->email;
    }

    public function setTelefono($telefono) {
        $this->telefono = $telefono;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    /**
     * Intenta determinar el tipo de documento identificativo
     *
     * @return string
     */
    public function getTipoDocumentoIdentificativo() {
        switch (\App\Lib\Base\Utilidades::calcularTipoDocumentoIdentificativo($this->getDni())) {
            case 1:
                return 'NIE';
            case 2:
                return 'DNI';
            case 0:
            default:
                return '';
        }
    }

    /**
     * Devuelve el documento identificativo con el tipo, ejemplo, DNI 12345678K o NIE X1234567B
     *
     * @return string
     */
    public function getDocumentoIdentificativo() {
        if (empty($this->getTipoDocumentoIdentificativo())) {
            return $this->getAdnipa();
        } else {
            return $this->getTipoDocumentoIdentificativo() . ' ' . \App\Lib\Base\Utilidades::comprobarDocumentoIdentificativo($this->getAdnipa(), true);
        }
    }

    /**
     * Devuelve el documento del usuario con letra
     *
     * @return string
     */
    public function documentoIdentificativoConLetra() {
        return \App\Lib\Base\Utilidades::comprobarDocumentoIdentificativo($this->getAdnipa(), true);
    }

    public function setNombre($sNombre) {
        $this->NombreUsuario = $sNombre;
    }

    public function getNombre() {
        return ucwords($this->NombreUsuario);
    }

    public function setApellido1($sApellido1) {
        $this->Apellido1Usuario = $sApellido1;
    }

    public function getApellido1() {
        return $this->Apellido1Usuario;
    }

    public function setApellido2($sApellido1) {
        $this->Apellido2Usuario = $sApellido1;
    }

    public function getApellido2() {
        return $this->Apellido2Usuario;
    }

    public function getTratamiento() {
        if (strlen($this->sTratamiento) < 2) {
            //Forzamos que se devuelva el DoñaDon abreviado
            return \App\Lib\Base\Utilidades::getTratamientoPersona('Tratamiento', true);
        }

        return $this->sTratamiento;
    }

    public function setTratamiento($sTratamiento) {
        $this->sTratamiento = $sTratamiento;
    }

    public function getGenero() {
        return $this->sGenero;
    }

    public function setGenero($sGenero) {
        $this->sGenero = $sGenero;
    }

    public function getArticuloGenero() {
        if (strlen($this->sArticuloGenero < 2)) {
            return \App\Lib\Base\Utilidades::getArticuloGenero($this->getGenero());
        }

        return $this->sArticuloGenero;
    }

    public function setArticuloGenero($sArticuloGenero) {
        $this->sArticuloGenero = $sArticuloGenero;
    }

    public function generoProfesor() {

        switch ($this->getGenero()) {

            case 'V':
                return 'el profesor';
            case 'M':
                return 'la profesora';
        }
    }

    /**
     * Tipo de documento de un usuario segun la BD
     * 
     * D = DNI
     * P = PASAPORTE
     * N = NIE
     * R = NIE
     * O = Documento de identidad ( NO ES DNI ) 
     * @return type
     */
    public function getTipoDocumento() {
        return $this->tipoDocumento;
    }

    public function setTipoDocumento($tipoDocumento) {
        $this->tipoDocumento = $tipoDocumento;
    }

    /**
     * Proporciona la denominacion del tipo de documento segun la BD
     * @return string
     */
    public function getDenomTipoDocumento() {
        return $this->denomTipoDocumento;
    }

    public function setDenomTipoDocumento($denomTipoDocumento) {
        $this->denomTipoDocumento = $denomTipoDocumento;
    }

    /**
     * Devuelve la letra proporcionada por la Base de datos, si es '?' puede
     *  que sea un Pasaporte o Documento de residencia que no dispone de 
     *  letra de control.
     *
     *  GA.PACKDATOSPERSONALES.FuncLetraNIF
     * 
     * @return string
     */
    public function getLetraDocumento() {
        return $this->letraDocumento;
}

    public function setLetraDocumento($letraDocumento){
        $this->letraDocumento = $letraDocumento;
    }


    /**
     * Devuelve el documento completo segun la BD,
     *  GA.PACKDATOSPERSONALES.FuncNIF
     * @return string
     */
    public function getDocumentoCompleto() {
        return $this->documentoCompleto;
    }

    public function setDocumentoCompleto($documentoCompleto){
        $this->documentoCompleto = $documentoCompleto;
    }

        
}
