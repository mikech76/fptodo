<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 04.07.2016
 * Time: 1:18
 */

namespace Core;


class Database
{

    protected static $_instance;
    protected static $db;


    private function __construct()
    {
        $opts = array(
            'user'    => USER,
            'pass'    => PASSWORD,
            'db'      => NAME_BD,
            'charset' => 'utf8',
            'host'    => HOST,
        );

        self::$db = new \SafeMySQL($opts);
    }

    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$db;
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}