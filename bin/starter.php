<?php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';
if($argc < 4){
    exit("Usage php ".basename(__FILE__) . " <jsonName> <service_name> <index>\n");
}
$jsonName = $argv[1];
$service = $argv[2];
$index = $argv[3];
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $jsonName;
if(substr($jsonName, -5) != '.json'){
    $path .= '.json';
}
if(!file_exists($path)){
    exit("json path: $path not exists.\n ");
}
$setting = json_decode(file_get_contents($path), true);
if(!isset($setting['service'][$service]) || !isset($setting['address'][$service])){
    exit("service: $service not configured\n");
}
$app = new cookpan001\Listener\Initializer($service, $index);
$app->daemonize();
$config = array();
foreach($setting['service'][$service] as $item){
    $newItem = $item + $setting['address'][$service][$index][$item['name']];
    if(isset($setting['address'][$service][$index]['host'])){
        $newItem['host'] = $setting['address'][$service][$index]['host'];
    }
    $config[] = $newItem;
}
$app->init($config);
$app->start();