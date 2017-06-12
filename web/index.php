<?php
/**
 * Инициализация приложения
 * Created by PhpStorm.
 * User: Mikech
 * Date: 01.07.2016
 * Time: 12:24
 */
function dump_str($var)
{
    ob_start();
    var_dump($var);
    $v = ob_get_clean();

    //под CLI не делаем украшательств
    if (defined('RUNNING_CLI') && RUNNING_CLI) return $v;

    //украшательства
    if (!extension_loaded('xdebug')) {
        $v = highlight_string("<?\n" . $v . '?>', true);
        $v = preg_replace('/=&gt;\s*<br\s*\/>\s*(&nbsp;)+/i', '=&gt;' . "\t" . '&nbsp;', $v);
    }
    $v = '<div style="background-color: #FFF;">' . $v . '</div>';
    return $v;
}

/**
 * улучшенная функция var_dump, выводит подсвеченную строку (с HTML-кодом)
 * @param mixed $var - переменная
 * @param mixed $var2 , $var3, ...
 */
function d($var)
{
    if (func_num_args() > 1) {
        foreach (func_get_args() as $var) echo dump_str($var);
    } else {
        echo dump_str($var);
    }
}
function dd($var){
    d($var); die();
};

// ===============================================================

use Core\Route;

// Composer autoloader
require '../vendor/autoload.php';

// константы для подключени к БД
//phpinfo();
define('HOST', 'localhost'); //сервер
define('USER', 'root'); //пользователь
define('PASSWORD', 'root'); //пароль
define('NAME_BD', 'todo');//база

//$db = \Core\Database::getInstance();

// Создаем роут
$route = new Route();

// получаем контроллер
$controller = $route->getController();
// выполнение
$controller->index();

?>