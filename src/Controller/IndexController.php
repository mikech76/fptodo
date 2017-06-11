<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 09.06.2017
 * Time: 15:18
 *
 * Главная страница
 */

namespace Controller;

use Core\Controller;
use Core\View;

class IndexController extends Controller
{
    public function __construct()
    {
        $this->template = 'index.html';
    }

    public function index()
    {
        // создаем стандартный Вид
        $view = new View();
        $view->renderHtml(array(), $this->template);
    }
}