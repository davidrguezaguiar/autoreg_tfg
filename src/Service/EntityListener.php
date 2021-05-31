<?php


namespace App\Service;

//use App\Orm\DelegacionesORM;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EntityListener
{
    /*private $delegacionesOrm;

    public function __construct(DelegacionesORM $delegacionesORM)
    {
        $this->delegacionesOrm = $delegacionesORM;
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if(method_exists($entity,'setODelegacionesORM')){
            $entity->setODelegacionesORM($this->delegacionesOrm);
        }
    }*/
}