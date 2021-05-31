<?php
/**
 * File:    SuplantarForm.php
 * User:    ULPGC
 * Project: symfony4
 *
 * Formulario para la suplantacion de la identidad de un usuario
 *
 * SOLO PARA ENTORNOS DE DESARROLLO
 */

namespace App\Form\suplantarIdentidad;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SuplantarForm extends AbstractType {

    /**
     * @Assert\NotBlank()
     * @Assert\Length(
     *      max = 15,
     *      maxMessage = "El DNI no puede exceder de {{ limit }} caracteres"
     * )
     */
    private $dni;

    /**
     * @return string
     */
    public function getDni () {
        return $this->dni;
    }

    /**
     * @param string $dni
     */
    public function setDni ( $dni ) {
        $this->dni = $dni;
    }

    public function buildForm ( FormBuilderInterface $builder , array $options ) {

        $builder->add ( 'dni' ,
                        'Symfony\Component\Form\Extension\Core\Type\TextType' ,
                        array( 'label'    => 'Simular la aplicación en desarrollo con el DNI' ,
                               'required' => 'true' ,
                               'attr'     => array( 'maxlength' => '15' , 'tabindex' => 1, 'autofocus' => 'autofocus' ) ) )
                ->add ( 'save' , 'Symfony\Component\Form\Extension\Core\Type\SubmitType' , array( 'label' => 'Simular',
                        'attr'  => array('class' => 'ulpgcds-btn ulpgcds-btn--primary ulpgcds-btn--small')) );
    }
}