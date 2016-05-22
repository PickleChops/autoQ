<?php

/***
 * @var $di Phalcon\Di
 */
$di = new \Phalcon\Di\FactoryDefault();

/**
 * Add app config
 */
$di->set(
    'config',
    function () {
        return require __DIR__ . '/../config/config.php';
    }
);

/**
 * Add logger
 */
$di->set(
    'log',
    function () {
        return new \Phalcon\Logger\Adapter\Stream("php://stdout");
    }
);

/**
 * Bind in our api helper
 */
$di->set('dBConnectionService', function () use ($di) {
    return new \Autoq\Services\DatabaseConnections($di);
});

/**
 * Add router for web requests
 */
$di->set(
    'router',
    function () {
        return require __DIR__ . '/../bootstrap/routes.php';
    }
);

/**
 * Bind in our job validator
 */
$di->set('jobValidator', function () {
    return new \Autoq\Services\ValidateJobDefintion();
});

/**
 * Bind in our api helper
 */
$di->set('apiHelper', function () {
    return new \Autoq\Services\ApiHelper();
});

return $di;
