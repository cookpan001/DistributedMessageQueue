<?php

include __DIR__ . DIRECTORY_SEPARATOR . 'base.php';
include __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';
$config = array(
    array(
        'codec' => 'cookpan001\Listener\Codec\Redis',
        'class' => 'cookpan001\Listener\Bussiness\Mediator',
        'role' => 'server',
        'name' => 'mediator',
        'host' => '127.0.0.1',
        'port' => 6380,
        'worker' => 1,
        'on' => array(
            'connect' => 'onConnect',
            'message' => 'onExchage',
        ),
        'emit' => array(
            'coordinator' => 'onCoordinator',
        ),
        'after' => array(
            'onAfter',
        ),
    ),array(
        'codec' => 'cookpan001\Listener\Codec\Redis',
        'class' => 'cookpan001\Listener\Bussiness\Mediator',
        'role' => 'server',
        'name' => 'mediator',
        'host' => '127.0.0.1',
        'port' => 6381,
        'worker' => 1,
        'on' => array(
            'connect' => 'onConnect',
            'message' => 'onExchage',
        ),
        'emit' => array(
            'coordinator' => 'onCoordinator',
        ),
        'after' => array(
            'onAfter',
        ),
    ),
    array(
        'codec' => 'cookpan001\Listener\Codec\Redis',
        'class' => 'cookpan001\Listener\Bussiness\Coordinator',
        'name' => 'coordinator',
        'role' => 'client',
        'worker' => 1,
        'on' => array(
            'connect' => 'onConnect',
            'message' => 'onMessage',
        ),
        'emit' => array(
            'mediator' => 'onMediator',
            'local' => 'onLocal',
        ),
        'instance' => array(
            array('127.0.0.1', 6379),
        ),
        'after' => array(
            'onAfter',
        ),
    ),
);
$app = new cookpan001\Listener\Initializer();
$app->init($config);
$app->start();