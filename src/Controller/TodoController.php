<?php

namespace Controller;

use Core\Controller;
use Core\Request;
use Core\View;
use Model\User;
use Model\TodoList;
//use Model\TodoTask;

define();
class TodoController extends Controller
{
    /**
     * @var array Данные
     */
    private $data = array();

    public function __construct()
    {
        session_start();
    }

    /**
     * Выбор действия/action
     */
    public function index()
    {
        try {
            // Операции с Юзером
            $this->data['user'] = $this->userActions();

            // Операции со Списками
            $this->todoListActions();


            // Операции с Задачами

        } catch (TodoException $e) {
            self::setSession();
            die();
            // todo
        }
        d($this->data['user'] );


        // создаем стандартный Вид
        $view = new View();
        $view->renderJson($this->data);
        //$view->renderStreamEvent($this->data);
    }

    /**
     * Операции над Юзером
     * @return User
     */
    private function userActions()
    {
        switch (Request::get('action')) {
            // Авторизация
            case 'user_login':
                $user = User::login();
                break;

            // Регистрация
            case 'user_register':
                $user = User::create();
                break;

            // Идентификация
            default:
                $user = User::auth();
                break;
        }

        // авторизован
        self::setSession($user->getId());

        return $user;
    }

    /**
     * @return TodoList
     */
    private function todoListActions()
    {
        switch (Request::get('action')) {
            // создать список
            case 'todolist_create':
                $todoList = TodoList::create($this->data['user']);
                break;

            // изменить список
            case 'todolist_update':
                $todoList = TodoList::udate();
                break;

            // расшарить список
            case 'todolist_share':
                $todoList = TodoList::toShare();
                break;
        }
    }

    /**
     * Обновить сессию
     * @param int $userId
     */
    private static function setSession($userId = null)
    {
        if ($userId) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['last_request'] = time();
        } else {
            session_destroy();
        }
    }

}

class TodoException extends \Exception
{
    public function __construct($error, $message, $code = 0, \Exception $previous = null)
    {
        parent::__construct(json_encode(array($error => $message)), $code, $previous);
    }

    public function get()
    {
        return json_decode($this->getMessage());
    }
}