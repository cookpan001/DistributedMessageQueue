<?php

class Commander
{
    const STARTER = 'starter.php';
    
    public function start($path)
    {
        $config = json_decode(file_get_contents($path), true);
        if(!isset($config['service']) || !isset($config['address'])){
            return;
        }
        foreach($config['service'] as $name => $conf){
            foreach($config['address'][$name] as $i => $address){
                $shell = 'php '.__DIR__ . DIRECTORY_SEPARATOR . self::STARTER . " $path {$name} {$i}";
                echo date('Y-m-d H:i:s')."\t$shell\n";
                exec($shell);
            }
        }
    }
    
    public function stop($path)
    {
        $config = json_decode(file_get_contents($path), true);
        if(!isset($config['service']) || !isset($config['address'])){
            return;
        }
        foreach($config['service'] as $name => $conf){
            $shell = "ps aux | grep '" . self::STARTER . " $path {$name}' | grep -v 'grep' | awk '{print $2}' | xargs kill -s SIGTERM";
            echo date('Y-m-d H:i:s')."\t$shell\n";
            exec($shell);
        }
    }
    
    public function restart($path)
    {
        $config = json_decode(file_get_contents($path), true);
        if(!isset($config['service']) || !isset($config['address'])){
            return;
        }
        foreach($config['address'] as $name => $conf){
            foreach($conf as $i => $address){
                $shell = "ps aux | grep '" . self::STARTER . " {$path} {$name} {$i}' | grep -v 'grep' | awk '{print $2}' | xargs kill -s SIGUSR2";
                echo date('Y-m-d H:i:s')."\t$shell\n";
                exec($shell);
            }
        }
    }
}
if($argc < 3){
    exit("Usage php ".basename(__FILE__) . " <json_path> <cmd>\n");
}
$cmd = $argv[2];
$app = new Commander();
if(!method_exists($app, $cmd)){
    exit("command $cmd not found\n");
}
$app->$cmd($argv[1]);