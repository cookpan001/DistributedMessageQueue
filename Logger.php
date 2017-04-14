<?php

namespace cookpan001\Loop;

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
            $str = $date."\t". json_encode($message)."\n";
        }else if(is_object($message)){
            $str = $date."\t".json_encode($message)."\n";
        }else{
            $str = $date."\t".$message."\n";
        }
        global $STDOUT;
        fwrite($STDOUT, $str);
    }
    
    public function error($message)
    {
        $date = $this->date();
        if(is_array($message)){
            $str = $date."\t". json_encode($message)."\n";
        }else if(is_object($message)){
            $str = $date."\t".json_encode($message)."\n";
        }else{
            $str = $date."\t".$message."\n";
        }
        global $STDERR;
        fwrite($STDERR, $str);
    }
}