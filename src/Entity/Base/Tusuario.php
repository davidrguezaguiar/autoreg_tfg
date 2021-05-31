<?php

namespace App\Entity\Base;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\Types\Boolean;
use Serializable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Tusuario
 *
 * @ORM\Table(name="YUSUARIOS1")
 * @ORM\Entity
 */
class Tusuario implements UserInterface, Serializable {

    /**
     * @var string
     *
     * @ORM\Column(name="ausuaa", type="string", length=10, precision=0, scale=0, nullable=false, unique=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $ausuaa;

    /**
     * @var string
     *
     * @ORM\Column(name="adnipa", type="string", length=14, precision=0, scale=0, nullable=false, unique=false)
     */
    private $adnipa;

    /**
     * @var string
     *
     * @ORM\Column(name="aapenom", type="string", length=50, precision=0, scale=0, nullable=false, unique=false)
     */
    private $aapenom;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="dcaducidad", type="datetime", precision=0, scale=0, nullable=false, unique=false)
     */
    private $dcaducidad;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="dultimaentrada", type="datetime", precision=0, scale=0, nullable=false, unique=false)
     */
    private $dultimaentrada;

    /**
     * @var string
     *
     * @ORM\Column(name="adatosordenador", type="string", length=250, precision=0, scale=0, nullable=false, unique=false)
     */
    private $adatosordenador;

    /**
     * @var string
     *
     * @ORM\Column(name="atipousuario", type="string", length=1, precision=0, scale=0, nullable=false, unique=false)
     */
    private $atipousuario;

    /**
     * Listado de roles de las Pantallas/modulos a los que puede acceder
     *  está relacionad con ( $aAplicaciones )
     *
     * @var array
     */
    private $roles = array();

    /**
     * Listado de aplicaciones que tiene acceso ( relationado con $roles );
     *
     * @var array
     */
    private $aplicaciones = array();

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

    /**
     * Determina si un usuario esta o no autenticado
     *
     * @var Boolean
     */
    private $bAutenticado = false;
    private $dniSuplantado = false;
    private $dniAnterior;

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
    protected $nombreCompleto;

    /**
     *
     * @author <david.rodriguez@ulpgc.es>
     * @since Se incluye Tipo Documento y descripcion proporcionado por la BD en la carga del usuario
     * @see \App\Orm\Base\PermisosUsuarioOrm obtenerNombreApellidoBD
     */
    private $tipoDocumento;
    private $denomTipoDocumento;
    private $IDAplicacion;
    private $letraDocumento;
    private $documentoCompleto;

    public function __construct($aRoles = array()) {
        $this->setRoles($aRoles);
        $this->setAplicaciones(array());
    }

    /**
     *
     * Establece el objeto del usuario como autenticado
     *  setUsuarioAutenticado <=> setUsuarioAnonimo
     */
    public function setUsuarioAutenticado() {
        $this->bAutenticado = true;
    }

    /**
     *
     * Establece el objeto del usuario como anonimo
     *  setUsuarioAnonimo <=> setUsuarioAutenticado
     */
    public function setUsuarioAnonimo() {
        $this->bAutenticado = false;
    }

    /**
     *
     *
     * Devuelve si un usuario esta autenticado
     *
     * @return boolean False Anonimo | True Autenticado
     */
    public function estaAutenticado() {
        return $this->bAutenticado;
    }

    /**
     * Set ausuaa
     *
     * @param string $ausuaa
     *
     * @return Tusuario
     */
    public function setAusuaa($ausuaa) {
        $this->ausuaa = $ausuaa;

        return $this;
    }

    /**
     * Get ausuaa
     *
     * @return string
     */
    public function getAusuaa() {
        return $this->ausuaa;
    }

    /**
     * Set adnipa
     *
     * @param string $adnipa
     *
     * @return Tusuario
     */
    public function setAdnipa($adnipa) {
        $this->adnipa = $adnipa;

        return $this;
    }

    /**
     * Get adnipa
     *
     * @return string
     */
    public function getAdnipa() {
        return $this->adnipa;
    }

    /**
     *
     * @return string
     */
    public function getDni() {
        return $this->getAdnipa();
    }

    /**
     * Set aapenom
     *
     * @param string $aapenom
     *
     * @return Tusuario
     */
    public function setAapenom($aapenom) {
        $this->aapenom = $aapenom;

        return $this;
    }

    /**
     * Get aapenom
     *
     * @return string
     */
    public function getAapenom() {
        return $this->aapenom;
    }

    /**
     * Set dcaducidad
     *
     * @param DateTime $dcaducidad
     *
     * @return Tusuario
     */
    public function setDcaducidad($dcaducidad) {
        $this->dcaducidad = $dcaducidad;

        return $this;
    }

    /**
     * Get dcaducidad
     *
     * @return DateTime
     */
    public function getDcaducidad() {
        return $this->dcaducidad;
    }

    /**
     * Set dultimaentrada
     *
     * @param DateTime $dultimaentrada
     *
     * @return Tusuario
     */
    public function setDultimaentrada($dultimaentrada) {
        $this->dultimaentrada = $dultimaentrada;

        return $this;
    }

    /**
     * Get dultimaentrada
     *
     * @return DateTime
     */
    public function getDultimaentrada() {
        return $this->dultimaentrada;
    }

    /**
     * Set adatosordenador
     *
     * @param string $adatosordenador
     *
     * @return Tusuario
     */
    public function setAdatosordenador($adatosordenador) {
        $this->adatosordenador = $adatosordenador;

        return $this;
    }

    /**
     * Get adatosordenador
     *
     * @return string
     */
    public function getAdatosordenador() {
        return $this->adatosordenador;
    }

    /**
     * Set atipousuario
     *
     * @param string $atipousuario
     *
     * @return Tusuario
     */
    public function setAtipousuario($atipousuario) {
        $this->atipousuario = $atipousuario;

        return $this;
    }

    /**
     * Get atipousuario
     *
     * @return string
     */
    public function getAtipousuario() {
        return $this->atipousuario;
    }

    /**
     * @param mixed $roles
     */
    public function setRoles($roles) {
        $this->roles = $roles;
    }

    /**
     * @return mixed
     */
    public function getRoles() {
        return $this->roles;
    }

    public function getAplicaciones() {
        return $this->aplicaciones;
    }

    public function setAplicaciones($aAplicaciones) {
        $this->aplicaciones = $aAplicaciones;
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

    /**
     * Inyecta los roles especificados al usuario
     *
     * Nota: Se inyectaran solamente aquellos roles que no tenga ya el usuario
     *
     * @param $aRoles
     */
    public function inyectarRoles($aRoles) {
        if (is_array($this->getRoles()) && (count($this->getRoles()))) {
            $aNuevosRoles = array();
            foreach ($aRoles as $current) {
                if (!in_array($current, $this->getRoles())) {
                    $aNuevosRoles[] = $current;
                }
            }

            $this->setRoles(array_merge($this->getRoles(), $aNuevosRoles));
        } else {
            $this->setRoles($aRoles);
        }
    }

    /**
     * Indica si se ha suplantado un dni
     *
     * @param boolean $valor
     */
    public function setDniSuplantado($valor) {

        $this->dniSuplantado = $valor;
    }

    public function getDniSuplantado() {
        return $this->dniSuplantado;
    }

    /**
     * Guarda el dni original que se suplanto.
     *
     * @param type $valor
     */
    public function setDniAnterior($valor) {
        $this->dniAnterior = $valor;
    }

    public function getDniAnterior() {
        return $this->dniAnterior;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string The password
     */
    public function getPassword() {
        return "abc213";
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt() {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername() {
        return $this->getAdnipa();
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials() {
        
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize() {
        return serialize(array(
            $this->ausuaa,
            $this->adnipa,
            $this->aapenom,
            $this->dcaducidad,
            $this->dultimaentrada,
            $this->adatosordenador,
            $this->atipousuario,
            $this->roles,
            $this->aplicaciones,
            $this->NombreUsuario,
            $this->Apellido1Usuario,
            $this->Apellido2Usuario,
            $this->dniAnterior,
            $this->dniSuplantado,
            $this->ipConexion,
            $this->tipoDocumento,
            $this->denomTipoDocumento,
            $this->IDAplicacion,
            $this->letraDocumento,
            $this->documentoCompleto,
        ));
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     *
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     *                           The string representation of the object.
     *                           </p>
     *
     * @return void
     */
    public function unserialize($serialized) {
        list($this->ausuaa, $this->adnipa, $this->aapenom, $this->dcaducidad,
                $this->dultimaentrada, $this->adatosordenador, $this->atipousuario,
                $this->roles, $this->aplicaciones, $this->NombreUsuario,
                $this->Apellido1Usuario, $this->Apellido2Usuario, $this->dniAnterior,
                $this->dniSuplantado,$this->ipConexion,$this->tipoDocumento,
                $this->denomTipoDocumento,$this->IDAplicacion,$this->letraDocumento,
                $this->documentoCompleto) = unserialize($serialized);
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

    /**
     *
     * @return [M|V] Femenino o Masculino
     */
    public function getGenero() {
        return $this->sGenero;
    }

    /**
     *
     * @param string $sGenero [M|V] Femenino o Masculino
     */
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
     * @return mixed
     */
    public function getNombreCompleto() {
        return $this->getNombre() . ' ' . $this->getApellido1() . ' ' . $this->getApellido2();
    }

    /**
     * @param mixed $nombreCompleto
     */
    public function setNombreCompleto($nombreCompleto) {
        $this->nombreCompleto = $nombreCompleto;
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

        
    /*
     * String identificador de app
     */
    public function getIDAplicacion() {
        return $this->IDAplicacion;
    }

    /**
     *  Identificador de aplicacion 
     * @param string $IDAplicacion
     */
    public function setIDAplicacion($IDAplicacion){
        $this->IDAplicacion = $IDAplicacion;
    }
}
