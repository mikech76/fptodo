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
use Core\MyException;
use Core\Request;

/**
 * Class TodoList
 * @package Model
 * Список задач
 */
class TodoList extends Model
{

    /**
     * Имя списка
     * @var string
     */
    public $name;

    /**
     * @var float;
     */
    public $updated;

    /**
     * TodoList constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Создание списка
     * @param User $user
     * @return TodoList
     * @throws MyException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_create&todolist_name=На%20пикник
     */
    public static function create(User $user)
    {
        // имя списка
        $name = Request::getName('todolist_name', array('todolist_name_bad', 'Недопустимое имя списка'));

        $db = Database::getInstance();
        $db->query(
            'INSERT INTO todolist(name,updated) VALUES (?s,?s)', $name, microtime(true)
        );
        $id = $db->insertId();
        // создаем шару с юзером
        $todoList = self::load($id, array('todolist_create_error', 'Ошибка создания списка!'));
        self::createShare($user, $todoList, SHARE_OWNER);

        return $todoList;
    }

    /**
     * Обновление списка
     * @param User $user
     * @return TodoList
     * @throws MyException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_update&todolist_id=4&todolist_name=На%20рыбалку
     */
    public static function update(User $user)
    {
        // Id списка
        $id = Request::getId('todolist_id', array('todolist_id_bad', 'Не указан Id списка для обновления'));
        // имя списка
        $name = Request::getName('todolist_name', array('todolist_name_bad', 'Недопустимое имя списка'));

        // список
        $todoList = self::load($id, array('todolist_not_exist', 'Не найден список ' . $id));
        // доступ
        $todoList->checkUserAccess($user, array('todolist_not_permission', 'Нет доступа к списку'));

        // изменить имя списка
        $todoList->setName($name);
        $db = Database::getInstance();
        $db->query('UPDATE todolist SET ?u WHERE id=?i ',
            array('name' => $name, 'updated' => microtime(true)), $id
        );
        // очистить кеш
        $cache = Cache::getInstance(__CLASS__);
        $cache->delete($id);
        $user->clearShares();

        $todoList = self::load($id);

        return $todoList;
    }

    /**
     * Загружает список
     * @param $id
     * @param null $exteption
     * @return TodoList|null
     * @throws MyException
     */
    public static function load($id, $exteption = null)
    {
        $id = (int)$id;
        if ($id) {
            // из кеша
            $cache = Cache::getInstance(__CLASS__);
            $todoList = $cache->get($id);
            if ($todoList) {
                return $todoList;
            }
            // из базы
            $db = Database::getInstance();
            $todoListData = $db->getRow('SELECT * FROM todolist WHERE id=?s', $id);

            // если запись найдена, создаем объект
            if ($todoListData) {
                $todoList = new TodoList;
                $todoList->setId($todoListData['id']);
                $todoList->setName($todoListData['name']);
                $todoList->setUpdated($todoListData['updated']);
                // в кеш
                $cache->set($todoList);

                return $todoList;
            }
        }

        if ($exteption) {
            MyException::go($exteption);
        }

        return null;
    }

    /**
     * Создать связь
     * @param User $owner
     * @throws MyException
     * @return array
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_share&share_user_login=testuser2&share_todolist_id=7&share_mode=2
     */
    public static function toShare(User $owner)
    {
        // режим связи
        $mode = Request::getInteger('share_mode');
        if (!in_array($mode, array(SHARE_DELETE, SHARE_OWNER, SHARE_EDIT, SHARE_SEE))) {
            MyException::go(array('share_mode_bad', 'Некорректный режим шары!'));
        }
        // список для шары
        $todoListId = Request::getId('share_todolist_id', array('todolist_id_bad', 'Не указан Id списка для шары'));
        $todoList = self::load($todoListId, array('todolist_not_exist', 'Не найден список ' . $todoListId));

        // юзер для шары
        $userName = Request::getLogin('share_user_login');
        $user = User::load($userName, 'login', array('todolist_user_not_exist', 'Не найден юзер ' . $userName));

        // если это владелец списка
        $right = $todoList->checkUserAccess($owner, array(SHARE_OWNER));
        if ($right) {
            // создаем шару
            return self::createShare($user, $todoList, $mode);
        } elseif (!$mode && $todoList->checkUserAccess($owner, array(SHARE_OWNER, SHARE_EDIT, SHARE_SEE))) {
            // а если хочет удалить привязку к себе
            // создаем шару
            return self::createShare($user, $todoList, $mode);
        }
        MyException::go(array('todolist_share_not_permission', 'Нет доступа к списку'));

        return null;
    }

    /**
     * Создает связь TodoList-User
     * @param User $user
     * @param TodoList $todoList
     * @param int $mode
     * @return array
     * @throws MyException
     */
    public static function createShare(User $user, TodoList $todoList, $mode)
    {
        $shares = $todoList->loadShares();
        $db = Database::getInstance();
        if (array_key_exists($user->getId(), $shares)) {
            $id = $shares[$user->getId()]['id'];
            // связь с юэером уже есть, обновить
            $db->query('UPDATE share SET ?u WHERE id=?i ',
                array('mode' => $mode, 'updated' => microtime(true)), $id
            );
        } else {
            // создаем связь
            $db->query(
                'INSERT INTO share(user_id,todolist_id,mode,updated) VALUES (?i,?i,?i,?s)',
                $user->getId(), $todoList->getId(), $mode, microtime(true)
            );
            //$id = $db->insertId();
        }
        // удалить кеш
        $todoList->clearShares();
        $user->clearShares();
        $shares = $todoList->loadShares();
        return $shares;
    }

    /**
     * Возвращает все шары списка
     * @return array
     */
    public function loadShares()
    {
        $cache = Cache::getInstance(__CLASS__ . '-shares');
        $shares = $cache->getValue($this->getId());
        if (!$shares) {
            $db = Database::getInstance();
            $shares = $db->getInd(
                'user_id',
                'SELECT s.*, u.login
                    FROM share AS s
                    LEFT JOIN user AS u ON u.id=s.user_id
                    WHERE s.todolist_id=?i',
                $this->getId()
            );
            $this->saveShares($shares);
        }
        return $shares;
    }

    /**
     * Сохраняет все шары списка в кеш
     */
    public function saveShares($shares)
    {
        $cache = Cache::getInstance(__CLASS__ . '-shares');
        $cache->setValue($this->getId(), $shares);
    }

    /**
     * Удаляет все шары списка из кеша
     */
    public function clearShares()
    {
        $cache = Cache::getInstance(__CLASS__ . '-shares');
        $cache->delete($this->getId());
    }

    /**
     * Проверка доступа пользователя к списку
     * @param User $user
     * @param array $modes
     * @param null $exception - кидать исключение при ошибке
     * @return bool - есть запрашиваемый доступ
     * @throws MyException
     */
    public function checkUserAccess(User $user, $modes, $exception = null)
    {
        // все связи со списком
        $shares = $this->loadShares();

        if (!array_key_exists($user->getId(), $shares) || !in_array($shares[$user->getId()]['mode'], $modes)) {
            if ($exception) {
                MyException::go($exception);
            }
            return false;
        }
        return true;
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