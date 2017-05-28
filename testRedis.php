<?php

include __DIR__ . DIRECTORY_SEPARATOR . 'base.php';
include __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

$app = new cookpan001\Listener\Codec\Redis();

function testInt()
{
    global $app;
    $input = 10;
    $str = $app->serialize($input);
    echo __FUNCTION__ . ", input: $input, output: " . json_encode($app->unserialize($str)) . "\n";
}

function testBulk()
{
    global $app;
    $input = 'hjkl';
    $str = $app->serialize($input);
    echo __FUNCTION__ . ", input: {$input}, output: " . json_encode($app->unserialize($str)) . "\n";
}

function testString()
{
    global $app;
    $input = 'OK';
    $str = $app->serialize($input);
    echo __FUNCTION__ . ", input: $input, output: " . json_encode($app->unserialize($str)) . "\n";
}

function testError()
{
    global $app;
    $input = '-ERR my error';
    $str = $app->serialize($input);
    echo __FUNCTION__ . ", input: " . $input . ", output: " . json_encode($app->unserialize($str)) . "\n";
}

function testNull()
{
    global $app;
    $input = null;
    $str = $app->serialize($input);
    echo __FUNCTION__ . ", input: $input, output: " . json_encode($app->unserialize($str)) . "\n";
}

function testRaw()
{
    global $app;
    $input = 'zadd test key value';
    $str = $input;
    echo __FUNCTION__ . ", input: $input, output: " . json_encode($app->unserialize($str)) . "\n";
}

function testRaw2()
{
    global $app;
    $input = 'a';
    $str = "a\r\n";
    echo __FUNCTION__ . ", input: $input, output: " . json_encode($app->unserialize($str)) . "\n";
}

function testArray()
{
    global $app;
    $input = array(1, 100, 2, 200, null, null, 4, 400, [5, '500'], [], 1, []);
    $str = $app->serialize($input);
    echo __FUNCTION__ . ", input: " . json_encode($input) . ", output: " . json_encode($app->unserialize($str)) . "\n";
}

function testPacket()
{
    global $app;
    $str = "$13\r\n58ffffbe058a0\r\n:1\r\n";
    echo __FUNCTION__ . ", output: " . json_encode($app->unserialize($str)) . "\n";
}

testPacket();
testInt();
testBulk();
testString();
testError();
testNull();
testArray();
testRaw2();
