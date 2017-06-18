<?php
/**
 * Created by PhpStorm.
 * User: Mike
 * Date: 15.06.2017
 * Time: 6:32
 */

namespace Core;

class MyException extends \Exception
{
    public function __construct($error, $message, $code = 0, \Exception $previous = null)
    {
        parent::__construct(json_encode([$error => $message]), $code, $previous);
    }

    public function get()
    {
        return json_decode($this->getMessage());
    }

    public static function go($exception)
    {
        throw  new self($exception[0], $exception[1]);
    }
}