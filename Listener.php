<?php

namespace cookpan001\Loop;

class Listener
{
    public $host = '0.0.0.0';
    public $port;
    public $socket;
    public $allConnections = 0;

    public function __construct($port)
    {
        $this->port = $port;
    }
    
    public function create()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(!$socket){
            $this->log("Unable to create socket");
            exit(1);
        }
        if(!socket_bind($socket, $this->host, $this->port)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        if(!socket_listen($socket)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        socket_set_nonblock($socket);
        $this->socket = $socket;
    }
    
    public function start()
    {
        $socket = $this->socket;
        $that = $this;
        $this->serverWatcher = new \EvIo($this->socket, \Ev::READ, function () use ($that, $socket){
            $clientSocket = socket_accept($socket);
            $that->process($clientSocket);
            ++$that->allConnections;
        });
        \Ev::run();
    }
    
    public function process($clientSocket)
    {
        socket_set_nonblock($clientSocket);
        $conn = new Connection($clientSocket);
        $that = $this;
        $id = uniqid();
        $watcher = new \EvIo($clientSocket, \Ev::READ, function() use ($that, $id){
            $that->log('----------------HANDLE----------------');
            $str = $that->read($id);
            if(false !== $str){
                $commands = $that->unserialize($str);
                $that->handle($id, $commands);
            }
            $that->log('----------------HANDLE FINISH---------');
        });
        $conn->_setId($id);
        $conn->_setWatcher($watcher);
        $this->connections[$id] = $conn;
        $this->socketLoop->run();
        \Ev::run();
    }
    
    public function handle()
    {
        
    }
    
    public function unserialize($str)
    {
        
    }
    
    public function serialize(...$data)
    {
        $str = implode(' ', $data);
        return strlen($str).$str;
    }
}