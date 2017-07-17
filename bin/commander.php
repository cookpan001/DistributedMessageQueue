<?php

class Commander
{
    const STARTER = 'starter.php';
    
    public function getIp()
    {
        $ret = array();
        $ips = array();
        exec("if [ -e /sbin/ip ];then /sbin/ip -4 addr; elif [ ! -z `which ip` ];then `which ip` -4 addr; else ifconfig | grep 'inet: ' ;fi;", $ret);
        foreach($ret as $line){
            $line = trim($line);
            if('inet ' !== substr($line, 0, 5)){
                continue;
            }
            $ip0 = substr($line, 5);
            $ip = substr($ip0, 0, strpos($ip0, '/'));
            if(substr($ip, 0, 3) == '127'){
                //continue;
            }
            $ips[$ip] = $ip;
        }
        return $ips;
    }
    
    public function getPath($jsonName)
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $jsonName;
        if(substr($jsonName, -5) != '.json'){
            $path .= '.json';
        }
        if(!file_exists($path)){
            exit("json path: $path not exists.\n ");
        }
        return $path;
    }
    
    public function start($path)
    {
        $config = json_decode(file_get_contents($this->getPath($path)), true);
        if(!isset($config['service']) || !isset($config['address'])){
            return;
        }
        $ips = $this->getIp();
        foreach($config['service'] as $name => $conf){
            foreach($config['address'][$name] as $i => $address){
                if(isset($address['host']) && !isset($ips[$address['host']])){
                    continue;
                }
                $ret = array();
                $exists = "ps aux | grep '" . self::STARTER . " $path {$name} {$i}' | grep -v 'grep' | wc -l";
                exec($exists, $ret);
                $count = (int)array_pop($ret);
                if($count){
                    echo date('Y-m-d H:i:s')."\t$path {$name} {$i} already started.\n";
                    continue;
                }
                $shell = 'php '.__DIR__ . DIRECTORY_SEPARATOR . self::STARTER . " $path {$name} {$i}";
                echo date('Y-m-d H:i:s')."\t$shell\n";
                exec($shell);
            }
        }
    }
    
    public function stop($path)
    {
        $config = json_decode(file_get_contents($this->getPath($path)), true);
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
        $this->stop($path);
        sleep(1);
        $this->start($path);
    }
}
if($argc < 3){
    exit("Usage php ".basename(__FILE__) . " <jsonName> <cmd>\n");
}
$cmd = $argv[2];
$app = new Commander();
if(!method_exists($app, $cmd)){
    exit("command $cmd not found\n");
}
$app->$cmd($argv[1]);