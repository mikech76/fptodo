<?php

namespace Core;

use Core\MyException;

/**
 * Валидатор параметров
 * Class Request
 * @package Core
 */
class Request
{
    /**
     * Возвращает параметр при наличии или $default|throw $default
     * @param $key
     * @param null $default
     * @return null
     * @throws MyException
     */
    static public function get($key, $default = null)
    {
        if (isset($_REQUEST[$key])) {
            return $_REQUEST[$key];
        }

        if ($default instanceof MyException) {
            MyException::go($default);
        }
        return $default;
    }

    /**
     * Возвращает параметр с экранированными спецсимволами
     * @param $key
     * @param null $default
     * @return string
     */
    static public function getSafeString($key, $default = null)
    {
        return (htmlspecialchars(self::get($key, $default)));
    }

    /**
     * Возвращает целочисленный параметр
     * @param $key
     * @return int
     */
    static public function getInteger($key)
    {
        return (int)self::get($key, 0);
    }

    /**
     * Возвращает float
     * @param $key
     * @return float
     */
    static public function getFloat($key)
    {
        return (float)self::get($key, 0);
    }

    static public function getId($key, $exception = null)
    {
        return (int)self::get($key, 0);
    }

    static public function getName($key, $exception = null)
    {
        $name = trim(self::getSafeString($key, 0));
        if ($exception instanceof MyException && !$name && strlen($name)>100) {
            MyException::go($exception);
        }
        return $name;
    }

    static public function getLogin($key, $exception = null)
    {
        $login = trim(self::getSafeString($key, 0));

        if ($exception instanceof MyException && !preg_match("/^[a-z0-9_-]{3,16}$/", $login)) {
            MyException::go($exception);
        }
        return $login;

    }

    static public function getPassword($key, $exception = null)
    {
        $password = trim(self::getSafeString($key, 0));
        if ($exception instanceof MyException && !$password) {
            MyException::go($exception);
        }
        return $password;
    }

}