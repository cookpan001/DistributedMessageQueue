<?php

namespace cookpan001\Listener;

class Logger
{
    public function date()
    {
        list($m1, ) = explode(' ', microtime());
        return $date = date('Y-m-d H:i:s') . substr($m1, 1);
    }
    /**
     * 写日志
     */
    public function log($message)
    {
        $date = $this->date();
        if(is_array($message)){
            $str = $date."\t". json_encode($message, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
        }else if(is_object($message)){
            $str = $date."\t".json_encode($message, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
        }else{
            $str = $date."\t".$message."\n";
        }
        global $STDOUT;
        if($STDOUT){
            fwrite($STDOUT, $str);
        }else if(STDOUT && is_resource(STDOUT)){
            fwrite(STDOUT, $str);
        }else{
            echo $str;
        }
    }
    
    public function error($message)
    {
        $date = $this->date();
        if(is_array($message)){
            $str = $date."\t". json_encode($message, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
        }else if(is_object($message)){
            $str = $date."\t".json_encode($message, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
        }else{
            $str = $date."\t".$message."\n";
        }
        global $STDERR;
        if($STDERR){
            fwrite($STDERR, $str);
        }else if(STDERR && is_resource(STDERR)){
            fwrite(STDERR, $str);
        }else{
            echo $str;
        }
    }
}