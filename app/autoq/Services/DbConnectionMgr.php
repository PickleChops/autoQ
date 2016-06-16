<?php

namespace Autoq\Services;

use Phalcon\Config;
use Phalcon\Db\Adapter;
use Phalcon\Db\Adapter\Pdo;
use Phalcon\Di;
use Phalcon\Logger\Adapter\Stream;

class DbConnectionMgr
{
    const DEFAULT_RETRIES = 3;
    const DEFAULT_WAIT = 10;

    private $log;
    private $config;


    /**
     * @var $connection Adapter
     */
    private $connection = null;


    private $connectionConfigKeys = [

        'host' => '',
        'username' => '',
        'password' => '',
        'dbname' => '',
        'port' => ''

    ];

    /**
     * @param Stream $log
     * @param Config $config
     */
    public function __construct(Stream $log, Config $config)
    {
        $this->log = $log;
        $this->config = $config;
    }

    /**
     * @param $config
     * @return array
     */
    private function normaliseConfig($config)
    {

        $dBconfig = array_merge($this->connectionConfigKeys, (array)$config);

        return $dBconfig;

    }

    /**
     * Regular get connection, no ping, no retries. Will use default database config unless other provided
     * @param $configKey
     * @return mixed
     * @throws \Exception
     */
    public function getConnection($configKey)
    {

        if (($connectionConfig = $this->config->get($configKey)) === null) {
            throw new \Exception("Database connection config $configKey not found");
        }

        
        $connectionConfig = $this->normaliseConfig($connectionConfig);

        return $this->connectWithRetries($connectionConfig, 1, 0);
    }

    /**
     * Try a little harder to get a database connection, also test if existing one has died
     * @param $configKey
     * @param int $retries
     * @param int $wait
     * @return mixed|null
     * @throws \Exception
     */
    public function getManagedConnection($configKey, $retries = self::DEFAULT_RETRIES, $wait = self::DEFAULT_WAIT)
    {

        $connection = null;
        
        if (($connectionConfig = $this->config->get($configKey)) === null) {
            throw new \Exception("Database connection config $configKey not found");
        }
        
        if ($this->pingConnection($this->connection) === false) {
            $connection = $this->connectWithRetries($connectionConfig, $retries, $wait);

            if ($connection instanceof Adapter) {
                $this->connection = $connection;
            }
        }

        return $connection;
    }

    /**
     * @param array $config
     * @param int $retries
     * @param int $wait
     * @return mixed
     */
    public function connectWithRetries(Array $config, $retries = self::DEFAULT_RETRIES, $wait = self::DEFAULT_WAIT)
    {

        $connection = null;
        $attempts = 1;

        $adapter = $config['adapter'];

        while ($attempts <= $retries) {

            try {

                $connection = new $adapter(array(
                    'host' => $config['host'],
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'dbname' => $config['dbname'],
                    'port' => $config['port'],
                ));

            } catch (\PDOException $e) {
                $attempts++;
                $this->log->warning("Connection failure attempt $attempts of $retries to host: {$config['host']} database: {$config['dbname']}");
            }

            if ($connection instanceof $adapter) {
                break;
            } elseif ($attempts < $retries) {
                sleep($wait);
            } elseif ($attempts == $retries) {
                $this->log->error("All $attempts connection attempts to host: {$config['host']} database: {$config['dbname']} failed");
            }
        }

        return $connection;
    }

    /**
     * Try to talk to the DB - this is useful in long running processes that the DB server disconnects
     * @param Adapter $connection
     * @return bool
     */
    public function pingConnection(Adapter $connection)
    {
        $response = false;

        if ($connection instanceof Adapter) {

            try {
                $result = $connection->fetchOne("SELECT 1");

                if ($result[0] == 1) {
                    $response = true;
                }

            } catch (\PDOException $e) {
                $response = false;
            }
        }

        return $response;

    }

}