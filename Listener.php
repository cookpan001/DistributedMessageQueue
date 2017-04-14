<?php

namespace cookpan001\Listener;

class Listener
{
    const FRAME_SIZE = 1500;
    
    public $host = '0.0.0.0';
    public $port;
    public $socket;
    public $allConnections = 0;
    public $codec = null;
    public $connections = array();
    public $callback = null;

    public function __construct($port, $codec)
    {
        $this->port = $port;
        $this->codec = $codec;
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
    
    public function setCodec($codec)
    {
        $this->codec = $codec;
    }
    
    public function setCallback(callable $func)
    {
        $this->callback = $func;
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
        $watcher = new \EvIo($clientSocket, \Ev::READ, function() use ($that, $conn){
            $that->log('----------------HANDLE BEGIN----------------');
            $str = $that->receive($conn);
            if(false !== $str){
                if($that->codec){
                    $commands = $that->codec->unserialize($str);
                    if($that->callback){
                        $that->callback($conn, $commands);
                    }
                }
            }
            $that->log('----------------HANDLE FINISH---------');
        });
        $conn->setId($id);
        $conn->setWatcher($watcher);
        $this->connections[$id] = $conn;
        \Ev::run();
    }
    
    public function receive($conn)
    {
        $tmp = '';
        $str = '';
        $i = 0;
        while(true){
            ++$i;
            $num = socket_recv($conn->clientSocket, $tmp, self::FRAME_SIZE, MSG_DONTWAIT);
            if(is_int($num) && $num > 0){
                $str .= $tmp;
            }
            $errorCode = socket_last_error($conn->clientSocket);
            socket_clear_error($conn->clientSocket);
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                break;
            }
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                if(isset($this->connections[$conn->id])){
                    unset($this->connections[$conn->id]);
                }
                $conn->close();
                return false;
            }
            if(0 === $num){
                break;
            }
        }
        return $str;
    }
    
    public function reply($conn, ...$param)
    {
        if($this->codec){
            $message = $this->codec->serialize($param);
            $num = socket_write($conn->clientSocket, $message, strlen($message));
            if(false === $num){
                if(isset($this->connections[$conn->id])){
                    unset($this->connections[$conn->id]);
                }
                $conn->close();
                return;
            }
            $tmp = '';
            socket_recv($conn->clientSocket, $tmp, self::FRAME_SIZE, MSG_DONTWAIT);
            $errorCode = socket_last_error($conn->clientSocket);
            socket_clear_error($conn->clientSocket);
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                if(isset($this->connections[$conn->id])){
                    unset($this->connections[$conn->id]);
                }
                $conn->close();
                return;
            }
            if(strlen($tmp)){
                $ret = $this->receive($conn);
                if(false === $ret){
                    return false;
                }
                return $ret . $tmp;
            }
        }
        return true;
    }
}