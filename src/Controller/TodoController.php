<?php

namespace Controller;

use Core\Controller;
use Core\Request;
use Core\Model;
use Core\View;
use Model\User;
use Model\TodoList;
use Model\TodoTask;

//use Model\TodoTask;

define("SHARE_DELETE", 0);
define("SHARE_OWNER", 1);
define("SHARE_EDIT", 2);
define("SHARE_SEE", 3);

define("TASK_DELETE", 0);
define("TASK_OPEN", 1);
define("TASK_CLOSE", 2);


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
            $this->data['todolist'] = $this->todoListActions();

            // Операции с Задачами
            $this->data['todotask'] = $this->todoTaskActions();

        } catch (TodoException $e) {
            //session_destroy();
            d($e->get());
            die();
            // @todo return http
        }

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
        User::setSession('last_request', Request::getFloat('last_request'));

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
                $todoList = TodoList::update();
                break;

            // расшарить список
            case 'todolist_share':
                $todoList = TodoList::toShare($this->data['user']);
                break;
        }

        return $todoList;
    }
   /**
     * @return TodoTask
     */
    private function todoTaskActions()
    {
        switch (Request::get('action')) {
            // создать задачу
            case 'todotask_create':
                $todoTask = TodoTask::create($this->data['user']);
                break;

            // изменить задачу
            case 'todotask_update':
                $todoTask = TodoTask::update();
                break;
        }

        return $todoTask;
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