<?php
namespace App;


class Config
{
    static function get($var){
        return $_ENV[$var];
    }
}