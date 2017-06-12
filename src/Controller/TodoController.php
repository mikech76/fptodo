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
        // post запросы
        try {
            // Операции с Юзером
            $this->data['user'] = $this->userActions();

            // открыть SSE
            $this->sseActions();

            // Операции со Списками
            $this->data['todolist'] = $this->todoListActions();

            // Операции с Задачами
            $this->data['todotask'] = $this->todoTaskActions();

        } catch (TodoException $e) {
            $this->data['error'] = $e->get();
        }

        // создаем Вид
        $view = new View();
        $view->renderJson($this->data);
    }

    /**
     * Server-Sent Events / event-stream
     */
    private function sseActions()
    {
        if (Request::get('action') == 'sse') {
            set_time_limit(0);
            header("Content-Type: text/event-stream\n\n");
            ob_end_flush();

            $user = $user = User::auth();
            $lastEventId = isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0;
            while (true) {
                $lastEventId = $this->sendEvent($user, $lastEventId);
                flush();
                sleep(1);
            }
            die();
        }
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

        return $user;
    }

    /**
     * @return TodoList
     */
    private function todoListActions()
    {
        switch (Request::get('action')) {
            // выбрать текущий список
            case 'todolist_choose':
                $todoList = TodoList::choose($this->data['user']);
                break;

            // создать список
            case 'todolist_create':
                $todoList = TodoList::create($this->data['user']);
                break;

            // изменить список
            case 'todolist_update':
                $todoList = TodoList::update($this->data['user']);
                break;

            // расшарить список
            case 'todolist_share':
                $todoList = TodoList::toShare($this->data['user']);
                break;

            default:
                $todoList = null;
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
                $todoTask = TodoTask::update($this->data['user']);
                break;

            default:
                $todoTask = null;
        }

        return $todoTask;
    }

    /**
     * отправка порции Server-Sent Events в event-stream
     * @param User $user
     * @param float $lastEventId
     * @return mixed $lastEventId
     */
    private function sendEvent($user, $lastEventId)
    {
        $data = $user->loadShares();
        $data = $this->filterNew($data, $lastEventId);

        $lastEventId = microtime(true);

        if (!empty($data)) {
            $message = array('user' => $user, 'todolist' => $data);

            // Event
            echo "id: " . $lastEventId . PHP_EOL;
            echo "data: " . json_encode($message) . PHP_EOL;
            echo PHP_EOL;
        }
        return $lastEventId;
    }

    /**
     * фильтрует только новые данные
     * @param $data
     * @param $lastEventId
     */
    private function filterNew($data, $lastEventId)
    {
        if (!$lastEventId) {
            // нужны все данные
            return $data;
        }
        // списки
        foreach ($data as $listId => $list) {

            // если share_updated новая - оставляем список - он новый для юзера
            if ($list['share_updated'] > $lastEventId) {
                continue;
            }

            // задачи
            foreach ($list['todotask'] as $taskId => $task) {
                // если task_updated старая - удаляем
                if ($task['task_updated'] < $lastEventId) {
                    unset($data[$listId]['todotask'][$taskId]);
                }
            }

            // Если список устарел и у него нет задач - удалить
            if ($list['todolist_updated'] < $lastEventId && empty($data[$listId]['todotask'])) {
                unset($data[$listId]);
            }
        }
        return $data;
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