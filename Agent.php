<?php

namespace cookpan001\Listener;

class Agent
{
    const SIZE = 1500;
    const END = "\r\n";
    
    private $watcher = null;
    private $periodTimer = null;
    public $codec = null;
    public $logger = null;
    public $callback = array();
    public $id = 0;
    public $instances = array();
    public $connected = array();
    public $handler = null;
    
    public function __construct($config)
    {
        foreach($config as $line){
            list($host, $port) = $line;
            $this->instances[$host.':'.$port] = $line;
        }
    }
    
    public function __call($name, $arguments)
    {
        var_export('unknown method: '.$name . ', args: '. json_encode($arguments)."\n");
    }
    
    public function setCodec($codec)
    {
        $this->codec = $codec;
    }
    
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }
    
    public function setId($id)
    {
        $this->id = $id;
    }
    
    public function setHandler($obj)
    {
        $this->handler = $obj;
    }
    
    public function connect()
    {
        foreach($this->instances as $from => $line){
            if(isset($this->connected[$from])){
                continue;
            }
            list($host, $port) = $line;
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === FALSE) {
                continue;
            }
            if(!socket_connect($socket, $host, $port)){
                continue;
            }
            $this->log("connected to {$host}:{$port}");
            socket_set_nonblock($socket);
            $this->connected[$from] = $socket;
        }
    }
    
    public function log($message)
    {
        if(!is_null($this->logger)){
            $this->logger->log($message);
            return;
        }
        list($m1, ) = explode(' ', microtime());
        $date = date('Y-m-d H:i:s') . substr($m1, 1);
        echo $date."\t".$message."\n";
    }
    
    public function on($condition, callable $func)
    {
        $this->callback[$condition][] = $func;
        return $this;
    }
    
    public function emit($condition, ...$param)
    {
        if(!isset($this->callback[$condition])){
            return false;
        }
        foreach($this->callback[$condition] as $callback){
            call_user_func_array($callback, $param);
        }
        return true;
    }
    
    public function receive($from)
    {
        $tmp = '';
        $str = '';
        $i = 0;
        $socket = $this->connected[$from];
        while(true){
            ++$i;
            $num = socket_recv($socket, $tmp, self::SIZE, MSG_DONTWAIT);
            if(is_int($num) && $num > 0){
                $str .= $tmp;
            }
            $errorCode = socket_last_error($socket);
            socket_clear_error($socket);
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                break;
            }
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                $this->watcher[$from]->stop();
                unset($this->watcher[$from]);
                socket_close($socket);
                unset($this->connected[$from]);
                $this->connect();
                $this->process();
                return false;
            }
            if(0 === $num){
                break;
            }
        }
        return $str;
    }
    
    public function push($to, ...$param)
    {
        if(count($param) > 1){
            $str = $this->codec->serialize($param);
        }else{
            $str = $this->codec->serialize($param[0]);
        }
        $this->write($to, $str);
    }
    
    public function broadcast(...$param)
    {
        if(count($param) > 1){
            $str = $this->codec->serialize($param);
        }else{
            $str = $this->codec->serialize($param[0]);
        }
        foreach($this->connected as $to => $__){
            $this->write($to, $str);
        }
    }
    
    public function write($to, $str)
    {
        $socket = $this->connected[$to];
        $num = socket_write($socket, $str, strlen($str));
        $errorCode = socket_last_error($socket);
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $this->watcher[$to]->stop();
            unset($this->watcher[$to]);
            socket_close($socket);
            unset($this->connected[$to]);
            $this->connect();
            $this->process();
            return false;
        }
        //$this->log("socket write len: ". json_encode($num) .", ". json_encode($str) );
        return $num;
    }
    
    public function handle($from)
    {
        $str = $this->receive($from);
        if(false === $str){
            return false;
        }
        if('' === $str){
            return true;
        }
        if($this->codec){
            $data = $this->codec->unserialize($str);
            $this->emit('message', $from, $data);
        }else{
            $this->log('no codec found');
        }
        return true;
    }
    
    public function process()
    {
        $that = $this;
        foreach ($this->connected as $from => $socket){
            if(isset($this->watcher[$from])){
                continue;
            }
            $this->watcher[$from] = new \EvIo($socket, \Ev::WRITE, function ($w)use ($that, $socket, $from){
                $w->stop();
                $that->emit('connect', $from);
                $that->watcher[$from] = new \EvIo($socket, \Ev::READ, function() use ($that, $from){
                    $that->handle($from);
                });
            });
        }
    }
    
    public function start()
    {
        $this->periodTimer = new \EvPeriodic(0, 5, null, function(){
            //$this->logger->log('connect periodic');
            $this->connect();
            $this->process();
        });
        $this->connect();
        $this->process();
    }
}