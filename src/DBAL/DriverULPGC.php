<?php
namespace App\DBAL;

use App\DBAL\OCI8ConnectionUlpgc;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Doctrine\DBAL\Driver\OCI8\OCI8Exception;
use const OCI_DEFAULT;

/**
 * A Doctrine DBAL driver for the Oracle OCI8 PHP extensions.
 */
class DriverULPGC extends AbstractOracleDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        try {
            return new OCI8ConnectionUlpgc(
                (string) $username,
                (string) $password,
                $this->_constructDsn($params),
                $params['charset'] ?? '',
                $params['sessionMode'] ?? OCI_DEFAULT,
                $params['persistent'] ?? false
            );
        } catch (OCI8Exception $e) {
            throw DBALException::driverException($this, $e);
        }
    }

    /**
     * Constructs the Oracle DSN.
     *
     * @param mixed[] $params
     *
     * @return string The DSN.
     */
    protected function _constructDsn(array $params)
    {
        return $this->getEasyConnectString($params);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     */
    public function getName()
    {
        return 'oci8ULPGC';
    }
}
