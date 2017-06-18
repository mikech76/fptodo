<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 02.07.2016
 * Time: 17:15
 */

namespace Core;

use Controller\TodoController;
use Controller\IndexController;

class Route
{
    public function getController()
    {
        if (! $route = Request::getSafeString('route')) {
            $route = 'index';
        }

        switch ($route) {

            case 'post':
                return new TodoController();
                break;

            case 'index':
            case '':
                return new IndexController();
                break;

            default:
                header("HTTP/1.0 404 Not Found");
                header("HTTP/1.1 404 Not Found");
                header("Status: 404 Not Found");
                die( 'No routes: <i>' . $route . '</i>' );
        }
    }

}
