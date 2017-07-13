<?php
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'base.php';
include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';
if($argc < 4){
    exit("Usage php ".basename(__FILE__) . " <json_path> <service_name> <index>\n");
}
$path = $argv[1];
$service = $argv[2];
$index = $argv[3];
$setting = json_decode(file_get_contents($path), true);
if(!isset($setting['service'][$service]) || !isset($setting['address'][$service])){
    exit("service: $service not configured\n");
}
$app = new cookpan001\Listener\Initializer($service);
$app->daemonize();
$config = array();$setting['service'][$service] + $setting['address'][$service][$index];
foreach($setting['service'][$service] as $item){
    $config[] = $item + $setting['address'][$service][$index][$item['name']];
}
$app->init($config);
$app->start();