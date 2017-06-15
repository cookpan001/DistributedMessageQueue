<?php

include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';
$config = array(
    array(
        'codec' => 'cookpan001\Listener\Codec\MessagePack',
        'class' => 'cookpan001\Listener\Bussiness\MessageQueue\Mediator',
        'role' => 'server',
        'name' => 'mediator',
        'host' => '127.0.0.1',
        'port' => 5381,
        'on' => array(
            'connect' => 'onConnect',
            'message' => 'onExchage',
        ),
        'emit' => array(
            
        ),
        'after' => array(
            
        ),
    ),array(
        'codec' => 'cookpan001\Listener\Codec\MessagePack',
        'class' => 'cookpan001\Listener\Bussiness\MessageQueue\Exchanger',
        'role' => 'server',
        'name' => 'exchanger',
        'host' => '127.0.0.1',
        'port' => 7381,
        'on' => array(
            'connect' => 'onConnect',
            'message' => 'onExchage',
        ),
        'emit' => array(
            
        ),
        'after' => array(
            
        ),
    ),
    array(
        'codec' => 'cookpan001\Listener\Codec\MessagePack',
        'class' => 'cookpan001\Listener\Bussiness\Coordinator',
        'name' => 'coordinator',
        'role' => 'agent',
        'on' => array(
            'connect' => 'onConnect',
            'message' => 'onMessage',
        ),
        'emit' => array(
            
        ),
        'instance' => array(
            array('127.0.0.1', 7380),
        ),
        'after' => array(
            
        ),
    ),
);
$app = new cookpan001\Listener\Initializer();
$app->init($config);
$app->start();