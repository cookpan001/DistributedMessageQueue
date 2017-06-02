<?php

namespace cookpan001\Listener\Codec;

use cookpan001\Listener\Codec;

class Deflate implements Codec
{
    public function serialize($data)
    {
        $tmp = gzdeflate(json_encode($data), 9);
        return pack('N', strlen($tmp)).$tmp;
    }

    public function unserialize($data)
    {
        $ret = array();
        while(strlen($data)){
            $arr = unpack('N', substr($data, 0, 4));
            $strlen = array_pop($arr);
            $ret[] = json_decode(gzinflate(substr($data, 4, $strlen)));
            $data = substr($data, 4 + $strlen);
        }
        return $ret;
    }
    
    public function encode(...$data)
    {
        return $this->serialize($data);
    }
}