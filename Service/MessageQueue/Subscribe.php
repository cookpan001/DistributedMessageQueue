<?php

include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';
$config = array(
    array(
        'codec' => 'cookpan001\Listener\Codec\MessagePack',
        'name' => 'waitor',
        'role' => 'client',
        'host' => '127.0.0.1',
        'port' => 6380,
        'on' => array(
            'message' => function($data){
//                list($m1, ) = explode(' ', microtime());
//                $date = date('Y-m-d H:i:s') . substr($m1, 1);
//                echo $date. "\treceived\n";
            },
            'connect' => function($client){
                $client->push('subscribe', 'test');
            },
        ),
        'emit' => array(
            
        ),
    ),
);
$app = new cookpan001\Listener\Initializer();
$app->init($config);
$app->start();