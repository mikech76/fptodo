<?php

namespace Controller;

use Core\Controller;
use Core\Request;
use Core\View;
use Core\MyException;
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

        } catch (MyException $e) {
            $this->data['error'] = $e->get();
        }

        // создаем Вид
        $view = new View();
        $view->renderJson($this->data);
    }

    /**
     * Операции над Юзером
     * @return User
     */
    private function userActions()
    {
        switch (Request::getSafeString('action')) {
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
        switch (Request::getSafeString('action')) {
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
        switch (Request::getSafeString('action')) {
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
     * Server-Sent Events / event-stream
     * @test http://mikech.zapto.org/fptodo/?route=post&action=sse&todolist_id=12
     */
    private function sseActions()
    {
        if (Request::getSafeString('action') == 'sse') {
            set_time_limit(0);
            header("Content-Type: text/event-stream\n\n");
            ob_end_flush();

            $user = User::auth();

            $lastEventId = isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? $_SERVER["HTTP_LAST_EVENT_ID"] : 0;
            $count = 0;
            //  while (++$count < 60) {
            while (1) {
                $lastEventId = $this->sendEvent($user, $lastEventId);

                flush();
                sleep(1);
            }
            die();
        }
    }

    /**
     * отправка порции Server-Sent Events в event-stream
     * @param User $user
     * @param float $lastEventId
     * @return mixed $lastEventId
     */
    private function sendEvent($user, $lastEventId)
    {
        // все все списки юзера
        $todoLists = $user->loadShares(); // print_r($todoLists);

        // запрашивают список Id
        $todoListId = Request::getId('todolist_id');

        // проверка id списка
        list($newTodoListId, $lastEventId) = $this->findCurrentTodoListId($todoLists, $todoListId, $lastEventId);

        // фильтруем лишнее
        $todoLists = $this->filterNew($todoLists, $todoListId, $lastEventId);

        // текущая метка
        $lastEventId = microtime(true);

        // test ping
        // echo "event: ping" . PHP_EOL . "id: " . $lastEventId . PHP_EOL . 'data: {"time": "' . $lastEventId . ' = ' . date(DATE_ISO8601) . '"}' . PHP_EOL . PHP_EOL;

        if (!empty($todoLists)) {
            $message = array(
                '$lastEventId'        => $lastEventId,
                'user'                => $user,
                'current_todoList_id' => $newTodoListId,
                'todolist'            => $todoLists,
            );

            // Event
            echo "event: todo" . PHP_EOL;
            echo "id: " . $lastEventId . PHP_EOL;
            echo "data: " . json_encode($message) . PHP_EOL . PHP_EOL;
        }

        // сервер установил другой список
        if ($newTodoListId != $todoListId) {
            // recconect
            //die();
        }
        return $lastEventId;
    }

    /**
     * Проверка текущего листа
     * @param $todoLists
     * @param $todoListId
     * @param $lastEventId
     * @return array [$todoListId,$lastEventId]
     */
    private function findCurrentTodoListId($todoLists, $todoListId, $lastEventId)
    {
        // текущий лист не задан или чужой или удален
        if (!$todoListId || !array_key_exists($todoListId, $todoLists) || !$todoLists[$todoListId]['todolist_mode']) {
            // отдадим данные с начала
            $lastEventId = 0;
            $todoListId = null;
            // установим список сами
            foreach ($todoLists as $todoList) {
                if ($todoList['todolist_mode']) {
                    $todoListId = $todoList['todolist_id'];
                    break;
                }
            }
        }
        return array($todoListId, $lastEventId);
    }


    /**
     * Фильтрует только новые данные с последнего запроса
     * @param array $todoLists - списки текущего юзера с шарами и таскамии
     * @param int $todoListId - текущий список
     * @param int $lastEventId - время последнего запроса
     * @return array $todoLists - отфильтрованный массив
     */
    private function filterNew($todoLists, $todoListId, $lastEventId)
    {
        // списки
        foreach ($todoLists as $listId => $list) {
            // выберем текущий живой список если не выделен
            if (!$todoListId && $list['todolist_mode']) {
                $todoListId = $listId;
            }
            // задачи
            foreach ($list['todotask'] as $taskId => $task) {
                // если task_updated старая - удаляем
                if ($task['task_updated'] < $lastEventId) {
                    unset($todoLists[$listId]['todotask'][$taskId]);
                }
            }

            // Если список устарел и у него нет задач - удалить
            if ($list['todolist_updated'] < $lastEventId && empty($todoLists[$listId]['todotask'])) {
                unset($todoLists[$listId]);
            } elseif ($todoListId != $listId) {
                // если список не текущий - то удалить его задачи
                $todoLists[$listId]['todotask'] = array();
            }
        }

        // если есть текущий список. добавить юзерами
        if (array_key_exists($todoListId, $todoLists)) {
            $todoList = TodoList::load($todoListId);
            $shares = $todoList->loadShares();
            $userData = array();
            foreach ($shares as $share) {
                $userData[$share['id']] =
                    array(
                        'user_id'       => $share['user_id'],
                        'user_name'     => $share['login'],
                        'share_id'      => $share['id'],
                        'share_mode'    => $share['mode'],
                        'share_updated' => $share['updated'],
                    );
            }
            $todoLists[$todoListId]['user'] = $userData;
        }
        return $todoLists;
    }
}
