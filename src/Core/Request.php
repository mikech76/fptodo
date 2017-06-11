<?php

namespace Core;

/**
 * Валидатор параметров
 * Class Request
 * @package Core
 */
class Request
{
    /**
     * Возвращает параметр при наличии или $default
     * @param $key
     * @param null $default
     * @return null
     */
    static public function get($key, $default = null)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
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

}