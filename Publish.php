<?php

include __DIR__ . DIRECTORY_SEPARATOR . 'base.php';
include __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';
$config = array(
    array(
        'codec' => 'cookpan001\Listener\Codec\MessagePack',
        'name' => 'waitor',
        'role' => 'client',
        'host' => '127.0.0.1',
        'port' => 6381,
        'on' => array(
            
        ),
        'emit' => array(
            
        ),
        'after' => array(
            function($client){
                $i = 100;
                $data = array();
                while($i > 0){
                    $data[] = $i;
                    $data[] = 0;
                    $i--;
                }
                $client->push('publish', 'test', ...$data);
                list($m1, ) = explode(' ', microtime());
                $date = date('Y-m-d H:i:s') . substr($m1, 1);
                echo $date. "\tmessage sent\n";
            },
        ),
    ),
);
$app = new cookpan001\Listener\Initializer();
$app->init($config);
$app->start();