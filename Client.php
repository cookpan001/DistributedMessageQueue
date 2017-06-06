<?php

namespace cookpan001\Listener;

class Client
{
    const SIZE = 1500;
    const END = "\r\n";
    
    private $host;
    public $socket = null;
    private $watcher = null;
    public $codec = null;
    public $logger = null;
    public $callback = array();
    public $id = 0;
    public $handler = 0;
    
    public function __construct($host = '127.0.0.1', $port = 6379)
    {
        $this->port = $port;
        $this->host = $host;
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
        while(true){
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($this->socket === FALSE) {
                //echo "socket_create() failed: reason: ".socket_strerror(socket_last_error()) . "\n";
                sleep(1);
                continue;
            }
            if(!socket_connect($this->socket, $this->host, $this->port)){
                //echo "socket_connect() failed: reason: ".socket_strerror(socket_last_error()) . "\n";
                sleep(1);
                continue;
            }
            $this->log("connected to {$this->host}:{$this->port}");
            break;
        }
        socket_set_nonblock($this->socket);
        return $this;
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
    
    public function receive()
    {
        $tmp = '';
        $str = '';
        $i = 0;
        while(true){
            ++$i;
            $num = socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
            if(is_int($num) && $num > 0){
                $str .= $tmp;
            }
            $errorCode = socket_last_error($this->socket);
            socket_clear_error($this->socket);
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                break;
            }
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                $this->watcher->stop();
                socket_close($this->socket);
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
    
    public function push(...$param)
    {
        if(count($param) > 1){
            $str = $this->codec->serialize($param);
        }else{
            $str = $this->codec->serialize($param[0]);
        }
        $this->write($str);
    }
    
    public function write($str)
    {
        $num = socket_write($this->socket, $str, strlen($str));
        $errorCode = socket_last_error($this->socket);
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $this->watcher->stop();
            socket_close($this->socket);
            $this->connect();
            $this->process();
            return false;
        }
        //$this->log("socket write len: ". json_encode($num) .", ". json_encode($str) );
        return $num;
    }
    
    public function handle()
    {
        $str = $this->receive();
        if(false === $str){
            return false;
        }
        $this->log('received message');
        if('' === $str){
            return true;
        }
        if($this->codec){
            $data = $this->codec->unserialize($str);
            $this->emit('message', $data);
        }else{
            $this->log('no codec found');
        }
        return true;
    }
    
    public function process()
    {
        $that = $this;
        $this->watcher = new \EvIo($this->socket, \Ev::WRITE, function ($w)use ($that){
            $w->stop();
            $that->emit('connect');
            $that->watcher = new \EvIo($that->socket, \Ev::READ, function() use ($that){
                $that->handle();
            });
        });
    }
    
    public function start()
    {
        $this->connect();
        $this->process();
    }
}