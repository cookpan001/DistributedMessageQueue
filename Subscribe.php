<?php

include __DIR__ . DIRECTORY_SEPARATOR . 'base.php';
include __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';
$config = array(
    array(
        'codec' => 'cookpan001\Listener\Codec\Deflate',
        'name' => 'waitor',
        'role' => 'client',
        'host' => '127.0.0.1',
        'port' => 6380,
        'on' => array(
            'message' => function($data){
                list($m1, ) = explode(' ', microtime());
                $date = date('Y-m-d H:i:s') . substr($m1, 1);
                echo $date. "\t" . json_encode($data)."\n";
            },
        ),
        'emit' => array(
            
        ),
        'after' => [
            function($client){
                $client->push('subscribe', 'test');
            },
        ],
    ),
);
$app = new cookpan001\Listener\Initializer();
$app->init($config);
$app->start();