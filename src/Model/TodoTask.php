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
use Controller\TodoException;

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
     * @throws TodoException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todotask_create&todotask_todolist_id=10&todotask_name=Заправиться
     */
    public static function create(User $user)
    {
        // список для задачи
        $todoListId = Request::getInteger('todotask_todolist_id');
        if (!$todoListId) {
            throw new TodoException('todotask_todolist_id_bad', 'Не указан Id списка!');
        }
        $todoList = TodoList::load($todoListId);
        if (!$todoList) {
            throw new TodoException('todotask_todolist_not_exist', 'Не найден список ' . $todoListId);
        }
        // владелец связан со списком
        $shares = $todoList->loadShares();
        if (!array_key_exists($user->getId(), $shares)
            || !in_array($shares[$user->getId()]['mode'], array(SHARE_OWNER, SHARE_EDIT))
        ) {
            throw new TodoException('todotask_todolist_not_permission', 'Нет доступа к списку');
        }

        // юзер - владелец | редактор, имеет право создавать задачу
        $name = substr(trim(Request::getSafeString('todotask_name')), 0, 100);
        if (!$name) {
            throw new TodoException('todotask_name_bad', 'Недопустимое имя списка');
        }

        // создаем задачу
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO todotask(todolist_id,name,status,updated) VALUES (?i,?s,?i,?s)',
            $todoListId, $name, TASK_OPEN, microtime(true)
        );
        $id = $db->insertId();
        if (!$id) {
            throw new TodoException('todotask_create_error', 'Ошибка создания списка!');
        }
        $todoTask = self::load($id);

        return $todoTask;
    }

    /**
     * Обновление списка
     * @param User $user
     * @return TodoTask
     * @throws TodoException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todotask_update&todotask_id=4&todotask_status=2&todotask_name=швшлык
     */
    public static function update(User $user)
    {
        // Id задачи
        $id = Request::getInteger('todotask_id');
        if (!$id) {
            throw new TodoException('todotask_id_bad', 'Не указан Id задачи');
        }
        // задача
        $todoTask = self::load($id);
        if (!$todoTask) {
            throw new TodoException('todotask_not_exist', 'Не найдена задача ' . $id);
        }
        // список
        $todoList = TodoList::load($todoTask->getTodoListId());
        // владелец связан со списком
        $shares = $todoList->loadShares();
        if (!array_key_exists($user->getId(), $shares)
            || !in_array($shares[$user->getId()]['mode'], array(SHARE_OWNER, SHARE_EDIT))
        ) {
            throw new TodoException('todotask_todolist_not_permission', 'Нет доступа к списку');
        }

        // юзер - владелец | редактор, имеет право создавать задачу
        $name = substr(trim(Request::getSafeString('todotask_name')), 0, 100);
        if (!$name) {
            throw new TodoException('todotask_name_bad', 'Недопустимое имя списка');
        }

        $fields = array();
        // имя задачи
        $name = Request::getSafeString('todotask_name');
        if ($name) {
            $name = substr(trim($name), 0, 100);
            if (!$name) {
                // имя задано но невалидно
                throw new TodoException('todotask_name_bad', 'Недопустимое имя задачи');
            }
            $fields['name'] = $name;
        }

        // статус задачи
        $status = Request::getInteger('todotask_status');
        if ($status) {
            $status = substr(trim($status), 0, 100);
            if (!in_array($status, array(TASK_DELETE, TASK_OPEN, TASK_CLOSE))) {
                // статус задан  но невалиден
                throw new TodoException('todotask_status_bad', 'Недопустимый статус задачи');
            }
            $fields['status'] = $status;
        }

        // изменить задачу
        if ($fields) {
            $fields['updated'] = microtime(true);
            $db = Database::getInstance();
            $db->query('UPDATE todotask SET ?u WHERE id=?i ', $fields, $id);

            // очистить кеш
            $cache = Cache::getInstance(__CLASS__);
            $cache->delete($id);

            $todoTask = self::load($id);
            return $todoTask;
        }
        return null;
    }

    /**
     * Загружает задачу
     * @param int $id
     * @return TodoTask
     */
    public static function load($id)
    {
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

            // в кеш
            $cache->set($todoTask);
            return $todoTask;
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