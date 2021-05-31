<?php
/**
 *
 * @author ULPGC
 * Creado el testigo, la escucha y el proveedor,
 * en la factoría es donde se enganchan dichos componentes,
 * indicando el nombre de los componentes de seguridad correspondientes.
 *
 */

namespace App\Service\Base\Security;

use App\Service\Base\Security\Authentication\Provider\WsseProvider;
use App\Service\Base\Security\Firewall\WsseListener;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

class WsseFactory implements SecurityFactoryInterface {

    public function create ( ContainerBuilder $container , $id , $config , $userProvider , $defaultEntryPoint ) {

        /*
         * Determina el proveedor de seguridad (services.yml)
         */
        $providerId = 'security.authentication.provider.wsse.' . $id;
        $container
            ->setDefinition ( $providerId , new ChildDefinition(WsseProvider::class) )
            ->setArgument  ( 0 , new Reference( $userProvider ) );


        /*
         * Determina el listener encargado de gestionar la autenticación (services.yml)
         */

        $listenerId = 'security.authentication.listener.wsse.' . $id;
        $listener   = $container->setDefinition ( $listenerId , new ChildDefinition(WsseListener::class) );

        return array( $providerId , $listenerId , $defaultEntryPoint );
    }

    public function getPosition () {
        return 'pre_auth';
    }

    public function getKey () {
        return 'wsse';
    }

    /**
     * addConfiguration
     *
     * Incluye configuración adicional al token.
     *
     * @param NodeDefinition $node
     */
    public function addConfiguration ( NodeDefinition $node ) {
    }

}
