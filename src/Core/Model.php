<?php

namespace Core;

use Core\Database;

class Model
{
    /**
     * @var int;
     */
    public $id;

    /**
     * ошибки модели
     * @var array
     */
    protected $error = array();

    /**
     * Model constructor.
     */
    public function __construct()
    {
    }

    /**
     * Записывает ошибку
     * @param $error
     */
    public function setError($error, $key = null)
    {
        if ($key) {
            $this->error[] = $error;
        } else {
            $this->error[] = $error;
        }
    }

    /**
     * возвращает модель в виде ассоциативного массива
     */
    public function getArray()
    {
    }

    /**
     * Получить переменную из сессии
     * @param string $name
     * @return mixed
     */
    public static function getSession($name)
    {
        return (array_key_exists($name, $_COOKIE) && $_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

    /**
     * Записывает переменную в сессию
     * @param $name
     * @param $value
     */
    public static function setSession($name, $value=null)
    {
        setcookie($name, $value, strtotime('+1 Month'));
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}