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
     * @throws TodoException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_create&todolist_name=На%20пикник
     */
    public static function create(User $user)
    {
        $name = substr(trim(Request::getSafeString('todolist_name')), 0, 100);
        if ($name) {
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO todolist(name,updated) VALUES (?s,?s)', $name, microtime(true)
            );
            $id = $db->insertId();
            if ($id) {
                $todoList = self::load($id);
                // создаем шару с юзером
                self::createShare($user, $todoList, SHARE_OWNER);

                return $todoList;
            }
            throw new TodoException('todolist_create_error', 'Ошибка создания списка!');
        }
        throw new TodoException('todolist_name_bad', 'Недопустимое имя списка');
    }

    /**
     * Обновление списка
     * @throws TodoException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_update&todolist_id=4&todolist_name=На%20рыбалку
     */
    public static function update()
    {
        $id = Request::getInteger('todolist_id');
        if ($id) {
            $name = substr(trim(Request::getSafeString('todolist_name')), 0, 100);
            if ($name) {
                $todoList = self::load($id);
                if ($todoList) {
                    // изменить имя списка
                    $todoList->setName($name);
                    $db = Database::getInstance();
                    $db->query('UPDATE todolist SET ?u WHERE id=?i ',
                        array('name' => $name, 'updated' => microtime(true)), $id
                    );
                    // очистить кеш
                    $cache = Cache::getInstance(__CLASS__);
                    $cache->delete($id);

                    $todoList = self::load($id);

                    return $todoList;
                }
                throw new TodoException('todolist_not_exist', 'Не найден список ' . $id);
            }
            throw new TodoException('todolist_name_bad', 'Недопустимое имя списка');
        }
        throw new TodoException('todolist_id_bad', 'Не указан Id списка для обновления');
    }

    /**
     * Загружает список
     * @param int $id
     * @return TodoList
     */
    public static function load($id)
    {
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

            // в кеш
            $cache->set($todoList);

            return $todoList;
        }

        return null;
    }

    /**
     * @param User $owner
     * @throws TodoException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_share&share_user_id=1&share_todolist_id=7&share_mode=2
     */
    public static function toShare(User $owner)
    {
        // режим связи
        $mode = Request::getInteger('share_mode');
        if (!in_array($mode, array(SHARE_DELETE, SHARE_OWNER, SHARE_EDIT, SHARE_SEE))) {
            throw new TodoException('share_mode_bad', 'Некорректный режим шары!');
        }
        // список для шары
        $todoListId = Request::getInteger('share_todolist_id');
        if (!$todoListId) {
            throw new TodoException('todolist_id_bad', 'Не указан Id списка для шары');
        }
        $todoList = self::load($todoListId);
        if (!$todoList) {
            throw new TodoException('todolist_not_exist', 'Не найден список ' . $todoListId);
        }
        // юзер для шары
        $userId = Request::getInteger('share_user_id');
        if (!$userId) {
            throw new TodoException('todolist_user_id_bad', 'Не указан Id юзера для шары');
        }
        $user = User::load($userId);
        if (!$user) {
            throw new TodoException('todolist_user_not_exist', 'Не найден юзер ' . $userId);
        }
        // влыделец связан со списком
        $shares = $todoList->loadShares();
        if (array_key_exists($owner->getId(), $shares)) {
            if ($shares[$owner->getId()]['mode'] == SHARE_OWNER) {
                // юзер - владелец, имеет право шарить
                return self::createShare($user, $todoList, $mode);
            }
        }
        throw new TodoException('todolist_share_not_permission', 'Не владелец списка');
    }

    /**
     * Создает связь TodoList-User
     * @param User $user
     * @param TodoList $todoList
     * @param int $mode
     * @throws TodoException
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
            $id = $db->insertId();
        }
        // в кеш
        $share = $db->getRow('SELECT * FROM share WHERE id=?i', $id);
        $shares[$user->getId()] = $share;
        $todoList->saveShares($shares);
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
            $shares = $db->getInd('user_id', 'SELECT * FROM share WHERE todolist_id=?i', $this->getId());
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

}