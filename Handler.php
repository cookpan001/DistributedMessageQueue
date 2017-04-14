<?php

namespace cookpan001\Loop;

class Handler
{
    public function __construct()
    {
        
    }
    
    public function main(Connection $conn, $data)
    {
        
    }
        
    public function handleRegister($kind, $conn)
    {
        if(($kind & self::OPTION_WORKER)){//as server
            if(!isset($this->server[$conn->id])){
                $this->server[$conn->id] = $conn;
            }
        }else{//as client
            if(!isset($this->client[$conn->id])){
                $this->client[$conn->id] = $conn;
            }
        }
        $this->log('register');
    }
    
    public function handleRequest($kind, Connection $conn, $message)
    {
        if(($kind & self::OPTION_REQUEST) == 0){
            return;
        }
        if(empty($message)){
            return;
        }
        $this->log(json_encode(msgpack_unpack($message)));
        $md5 = md5($message);
        $this->sequences[$md5] = $conn;//等待的客户端
        if(($kind & self::OPTION_MULTI)){//群发
            $reply = $this->pushMulti($kind, $md5, $message);
        }else{//单发
            $reply = $this->push($kind, $md5, $message);
        }
        $nullReply = array(self::OPTION_REPLY, $md5, null);
        if(0 === $reply){
            $this->reply($conn, $nullReply);
            unset($this->sequences[$md5]);
            return;
        }else if(is_string($reply)){
            $this->reply($conn, $reply);
            unset($this->sequences[$md5]);
            return;
        }
        $this->log('request');
        $that = $this;
        $watcher = new \EvTimer(1, function() use ($that, $watcher, $md5, $nullReply){
            $watcher->stop();
            if(isset($that->sequences[$md5])){
                $that->reply($that->sequences[$md5], $nullReply);
                unset($that->sequences[$md5]);
            }
            unset($that->watchers[$md5]);
        });
        $this->log('handleRequest Timer');
        $this->watchers[$md5] = $watcher;
    }
    
    public function handleReply($kind, $seqId, $message)
    {
        if(($kind & self::OPTION_REPLY) == 0){
            return Reply\NoReply::instance();
        }
        $this->log('reply');
        if(isset($this->sequences[$seqId])){
            $this->reply($this->sequences[$seqId], array($kind, $seqId, $message));
            unset($this->sequences[$seqId]);
            //$reply = Reply\NoReply::instance();
        }
        if(isset($this->watchers[$seqId])){
            $this->watchers[$seqId]->stop();
            unset($this->watchers[$seqId]);
        }
    }
}