<?php

namespace cookpan001\Listener;

class Client
{

    /**
     * @var callable
     */
    private $func;

    const SIZE = 1500;
    const END = "\r\n";
    
    private $host;
    public $socket = null;
    private $watcher = null;
    private $key = 'test';
    public $codec = null;
    public $onConnect = null;
    
    public function __construct($host = '127.0.0.1', $port = 6379, Callable $func = null)
    {
        $this->port = $port;
        $this->host = $host;
        $this->func = $func;
        $this->setParam();
    }
    
    public function setParam($key)
    {
        $this->key = $key;
    }
    
    public function setCodec($codec)
    {
        $this->codec = $codec;
    }
    
    public function setOnConnect(callable $onConnect = null)
    {
        $this->onConnect = $onConnect;
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
        list($m1, ) = explode(' ', microtime());
        $date = date('Y-m-d H:i:s') . substr($m1, 1);
        echo $date."\t".$message."\n";
    }
    
    public function register()
    {
        $buffer = "zadd hello 0 test\r\n";
        socket_write($this->socket, $buffer, strlen($buffer));
    }
    
    public function read()
    {
        $tmp = '';
        socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
        $errorCode = socket_last_error($this->socket);
        $this->log("read , errorCode: $errorCode, error:".socket_strerror($errorCode));
        if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
            socket_clear_error($this->socket);
            return '';
        }
        if( (0 === $errorCode && null === $tmp) ||EPIPE == $errorCode || ECONNRESET == $errorCode){
            $this->watcher->stop();
            socket_close($this->socket);
            $this->connect();
            $this->process();
            return false;
        }
        return $tmp;
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
        $this->log("socket write len: ". json_encode($num) .", ". json_encode($str) );
        return $num;
    }
    
    public function parse(&$cur = 1)
    {
        $pos = strpos($this->response, self::END, $cur);
        switch ($this->response[0]) {
            case '-' : // Error message
                $ret = substr($this->response, $cur, $pos - 1);
                $this->response = substr($this->response, $pos + 2);
                break;
            case '+' : // Single line response
                $ret = substr($this->response, $cur, $pos - 1);
                $this->response = substr($this->response, $pos + 2);
                break;
            case ':' : //Integer number
                $ret = (int)substr($this->response, $cur, $pos - 1);
                $this->response = substr($this->response, $pos + 2);
                break;
            case '$' : //bulk string or null
                $ret = (int)substr($this->response, $cur, $pos - 1);
                if($ret == '-1'){
                    $ret = null;
                    $this->response = substr($this->response, $pos + 2);
                }else{
                    $ret = substr($this->response, $pos + 2, intval($ret));
                    $this->response = substr($this->response, $pos + 2 + intval($ret) + 2);
                }
                break;
            case '*' : //Bulk data response
                $length = (int)substr($this->response, $cur, $pos - 1);
                $cur = $pos + 2;
                if($length == -1){
                    $ret = array();//empty array
                    $this->response = substr($this->response, $cur);
                    break;
                }
                for ($c = 0; $c < $length; $c ++) {
                    if($this->response[$cur] == '$'){
                        $strlen = '';
                        $cur += 1;
                        while($this->response[$cur] != "\r"){
                            $strlen .= $this->response[$cur];
                            ++$cur;
                        }
                        $cur += 2;
                        if($strlen == '-1'){
                            $ret[] = null;
                        }else{
                            $ret[] = substr($this->response, $cur, (int)$strlen);
                            $cur += (int)$strlen + 2;
                        }
                    }else if($this->response[$cur] == '*'){
                        $ret[] = $this->parse($cur);
                    }
                }
                $this->response = substr($this->response, $cur);
                break;
            default :
                break;
        }
        return $ret;
    }
    
    public function handle()
    {
        $str = $this->receive();
        if(empty($str)){
            return;
        }
        $ret = $this->codec->unserialize($str);
        if(is_callable($this->func)){
            call_user_func_array($this->func, array($ret));
        }else{
            $this->log(json_encode($ret));
        }
    }
    
    public function process()
    {
        $that = $this;
        $this->watcher = new \EvIo($this->socket, Ev::WRITE, function ($w)use ($that){
            $w->stop();
            $that->register();
            if($that->onConnect){
                $that->onConnect();
            }
            $that->watcher = new \EvIo($that->socket, Ev::READ, function() use ($that){
                $that->handle();
            });
        });
    }
}