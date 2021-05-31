<?php

namespace App\DBAL;


use Doctrine\DBAL\Driver\OCI8\OCI8Connection;
use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use App\DBAL\OCI8StatementUlpgc;
use Symfony\Bridge\Monolog\Logger;
use const OCI_DEFAULT;

/**
 * OCI8 implementation of the Connection interface.
 */
class OCI8ConnectionUlpgc extends OCI8Connection 
{
    private $Logger;
    
    /**
     * Creates a Connection to an Oracle Database using oci8 extension.
     *
     * @param string $username
     * @param string $password
     * @param string $db
     * @param string $charset
     * @param int    $sessionMode
     * @param bool   $persistent
     *
     * @throws OCI8Exception
     */
    public function __construct(
        $username,
        $password,
        $db,
        $charset = '',
        $sessionMode = OCI_DEFAULT,
        $persistent = false
    ) {
        parent::__construct($username, $password, $db, $charset, $sessionMode, $persistent);
        
        
    }

     /**
     * {@inheritdoc}
     */
    public function prepare($prepareString) {
        return new OCI8StatementUlpgc($this->dbh, $prepareString, $this);
        
    }
    /**
     * 
     * @return Logger
     */
    public function getLogger() {
        return $this->Logger;
    }

    /**
     * 
     * @param Logger $Logger
     */
    public function setLogger($Logger) {
        $this->Logger = $Logger;
    }

    
}
