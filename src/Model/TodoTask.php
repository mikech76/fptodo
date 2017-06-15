<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 11.06.2017
 */

namespace Model;

use Core\Model;
use Core\Cache;
use Core\Database;
use Core\Request;
use Core\MyException;

/**
 * Class TodoTask
 * @package Model
 * Задача
 */
class TodoTask extends Model
{
    /**
     * Имя задачи
     * @var string
     */
    public $name;

    /**
     * Статус задачи
     * @var int
     */
    public $status;

    /**
     * ID списка
     * @var int
     */
    public $todoListId;

    /**
     * время обновления
     * @var float
     */
    public $updated;

    /**
     * TodoTask constructor.
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Создание задачи
     * @param User $user
     * @return TodoTask
     * @throws MyException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todotask_create&todotask_todolist_id=10&todotask_name=Заправиться
     */
    public static function create(User $user)
    {
        // список для задачи
        $todoListId = Request::getId('todotask_todolist_id', array('todotask_todolist_id_bad', 'Не указан Id списка!'));

        $todoList = TodoList::load($todoListId, array('todotask_todolist_not_exist', 'Не найден список ' . $todoListId));

        // владелец связан со списком
        $todoList->checkUserAccess(
            $user,
            array(SHARE_OWNER, SHARE_EDIT),
            array('todolist_share_not_permission', 'Нет доступа к списку')
        );

        // юзер - владелец | редактор, имеет право создавать задачу
        $name = Request::getName('todotask_name', array('todotask_name_bad', 'Недопустимое имя списка'));

        // создаем задачу
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO todotask(todolist_id,name,status,updated) VALUES (?i,?s,?i,?s)',
            $todoListId, $name, TASK_OPEN, microtime(true)
        );
        $id = $db->insertId();
        $todoTask = self::load($id, array('todotask_create_error', 'Ошибка создания списка!'));

        return $todoTask;
    }

    /**
     * Обновление списка
     * @param User $user
     * @return TodoTask
     * @throws MyException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todotask_update&todotask_id=4&todotask_status=2&todotask_name=швшлык
     */
    public static function update(User $user)
    {
        // Id задачи
        $id = Request::getId('todotask_id', array('todotask_id_bad', 'Не указан Id задачи'));

        // задача
        $todoTask = self::load($id, array('todotask_not_exist', 'Не найдена задача ' . $id));

        // список
        $todoList = TodoList::load(
            $todoTask->getTodoListId(),
            array('todotask_todolist_not_exist', 'Не найден список ' . $todoTask->getTodoListId())
        );

        // владелец связан со списком
        $todoList->checkUserAccess(
            $user,
            array(SHARE_OWNER, SHARE_EDIT),
            array('todolist_share_not_permission', 'Нет доступа к списку')
        );

        // юзер - владелец | редактор, имеет право создавать задачу
        $name = Request::getName('todotask_name', array('todotask_name_bad', 'Недопустимое имя списка'));

        $fields = array();
        $fields['name'] = $name;

        // статус задачи
        $status = Request::getInteger('todotask_status');
        if (!in_array($status, array(TASK_DELETE, TASK_OPEN, TASK_CLOSE))) {
            // статус задан  но невалиден
            MyException::go(array('todotask_status_bad', 'Недопустимый статус задачи'));
        }
        $fields['status'] = $status;

        // изменить задачу
        $fields['updated'] = microtime(true);
        $db = Database::getInstance();
        $db->query('UPDATE todotask SET ?u WHERE id=?i ', $fields, $id);

        // очистить кеш
        $cache = Cache::getInstance(__CLASS__);
        $cache->delete($id);
        $user->clearShares();

        $todoTask = self::load($id);
        return $todoTask;
    }

    /**
     * Загружает задачу
     * @param int $id
     * @return TodoTask
     * @throws MyException
     */
    public static function load($id, $exteption = null)
    {
        $id = (int)$id;
        // из кеша
        $cache = Cache::getInstance(__CLASS__);
        $todoTask = $cache->get($id);
        if ($todoTask) {
            return $todoTask;
        }
        // из базы
        $db = Database::getInstance();
        $todoTaskData = $db->getRow('SELECT * FROM todotask WHERE id=?s', $id);

        // если запись найдена, создаем объект
        if ($todoTaskData) {
            $todoTask = new TodoTask;
            $todoTask->setId($todoTaskData['id']);
            $todoTask->setName($todoTaskData['name']);
            $todoTask->setStatus($todoTaskData['status']);
            $todoTask->setUpdated($todoTaskData['updated']);
            $todoTask->setTodoListId($todoTaskData['todolist_id']);

            // в кеш
            $cache->set($todoTask);
            return $todoTask;
        }

        if ($exteption) {
            MyException::go($exteption);
        }
        return null;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getTodoListId()
    {
        return $this->todoListId;
    }

    /**
     * @param int $todoListId
     */
    public function setTodoListId($todoListId)
    {
        $this->todoListId = $todoListId;
    }

    /**
     * @return int
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param int $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

}