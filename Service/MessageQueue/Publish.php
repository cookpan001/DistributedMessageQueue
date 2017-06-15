<?php

include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';

function now()
{
    list($m1, ) = explode(' ', microtime());
    return date('Y-m-d H:i:s') . substr($m1, 1);
}

$config = array(
    array(
        'codec' => 'cookpan001\Listener\Codec\MessagePack',
        'name' => 'waitor',
        'role' => 'client',
        'host' => '127.0.0.1',
        'port' => 6380,
        'on' => array(
            
        ),
        'emit' => array(
            
        ),
        'after' => array(
            function($client){
                $i = 1000;
//                $data = array();
//                while($i > 0){
//                    $data[] = $i;
//                    $data[] = 0;
//                    $i--;
//                }
//                $client->push('publish', 'test', ...$data);
                echo now(). "\tsending\n";
                while($i > 0){
                    $client->push('publish', 'test', $i, 0);
                    --$i;
                }
                echo now(). "\tmessage sent\n";
            },
        ),
    ),
);
$app = new cookpan001\Listener\Initializer();
$app->init($config);
$app->start();