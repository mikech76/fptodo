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
 * Class User
 * @package Model
 * Пользователь блога
 */
class User extends Model
{
    /**
     * Логин
     * @var string
     */
    public $login;

    /**
     * Хеш пароля
     * @var string
     */
    private $password;

    /**
     * Соль
     * @var string
     */
    private $salt;

    /**
     * Списки
     * @var string
     */
    private $todoLists;

    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param string $login
     */
    public function setLogin($login)
    {
        $this->login = $login;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * @param string $salt
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
    }

    /**
     * Регистрация юзера
     * @test http://mikech.zapto.org/fptodo/?route=post&action=user_register&user_login=testuser&user_password=12345
     */
    public static function create()
    {
        // имя юзера
        $login = substr(trim(Request::getSafeString('user_login')), 0, 30);
        if (!$login) {
            throw new TodoException('user_login_bad', 'Недопустимый Логин');
        }

        // проверка дубликата
        $duplicate = self::load($login, 'login'); // Дубликат?
        if ($duplicate) {
            throw new TodoException('user_login_occupyed', 'Имя "' . $login . '" занято!');
        }

        // пароль
        $pass = substr(trim(Request::getSafeString('user_password')), 0, 30);
        if (!$pass) {
            throw new TodoException('user_password_bad', 'Недопустимый Пароль');
        }
        $salt = md5(uniqid());
        $password = md5($login . $pass . $salt);

        //  создаем юзера
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO user(login,password,salt) VALUES (?s,?s,?s)', $login, $password, $salt
        );

        // запись создана
        $id = $db->insertId();
        if (!$id) {
            throw new TodoException('user_create_error', 'Ошибка регистрации!');
        }

        // авторизуем юзера
        User::setSession('user_id', $id);
        $user = self::load($id);

        return $user;
    }

    /**
     * Авторизация
     * @test http://mikech.zapto.org/fptodo/?route=post&action=user_login&user_login=testuser&user_password=12345
     */
    public static function login()
    {
        // имя юзера
        $login = substr(trim(Request::getSafeString('user_login')), 0, 30);
        if (!$login) {
            throw new TodoException('user_login_bad', 'Недопустимый Логин');
        }

        // загружаем юзера
        $user = self::load($login, 'login');
        if (!$user) {
            throw new TodoException('user_not_exist', 'Пользователь "' . $login . '"" не зарегистрирован!');
        }

        // сверка пароля
        $pass = substr(trim(Request::getSafeString('user_password')), 0, 30);
        $password = md5($login . $pass . $user->getSalt());
        if ($password != $user->getPassword()) {
            throw new TodoException('user_password_bad', 'Не верный пароль!');
        }

        User::setSession('user_id', $user->getId());

        return $user;
    }

    /**
     * Идентификация юзера
     * @return User
     * @throws TodoException
     */
    public static function auth()
    {
        $id = self::getSession('user_id');
        session_write_close();
        if ($id) {
            // загружаем юзера
            $user = self::load($id);
            if ($user) {
                return $user;
            }
        }
        throw new TodoException('user_no_auth', 'Пользователь не авторизован!');
    }

    /**
     * Загружает юзера
     * @param mixed $param
     * @param string $key
     * @return User
     */
    public static function load($param, $key = 'id')
    {
        if ($param) {
            $cache = Cache::getInstance(__CLASS__);

            if (in_array($key, array('id', 'login'))) {
                if ($key == 'id' && $user = $cache->get((int)$param)) {
                    return $user;
                }
                // из базы
                $db = Database::getInstance();
                $userData = $db->getRow('SELECT * FROM user WHERE ?n=?s', $key, $param);
                // если запись найдена, создаем объект
                if ($userData) {
                    $user = new User;
                    $user->setId($userData['id']);
                    $user->setLogin($userData['login']);
                    $user->setPassword($userData['password']);
                    $user->setSalt($userData['salt']);
                    // в кеш
                    $cache->set($user);
                    return $user;
                }
            }
        }

        return null;
    }

    /**
     * Возвращает все шары юзера
     * @return array
     */
    public function loadShares()
    {
        $cache = Cache::getInstance(__CLASS__ . '-share');
        $shares = $cache->get($this->getId());
        if (!$shares) {
            $db = Database::getInstance();
            $sharesData = $db->getAll(
                'SELECT s.user_id, s.todolist_id, s.`mode`, s.updated AS share_updated, 
                             tl.name AS todolist_name, tl.updated AS todolist_updated,
                             tt.id AS task_id, tt.name AS task_name, tt.`status`, tt.updated AS task_updated 
                    FROM share AS s
                    LEFT JOIN todolist AS tl ON tl.id=s.todolist_id
                    LEFT JOIN todotask AS tt ON tl.id=tt.todolist_id
                    WHERE s.user_id=?i', $this->getId());

            $shares = array();
            foreach ($sharesData as $t) {
                // списки
                if (!isset($shares[$t['todolist_id']])) {
                    $shares[$t['todolist_id']] = array(
                        'user_id'          => $t['user_id'],
                        'todolist_id'      => $t['todolist_id'],
                        'todolist_name'    => $t['todolist_name'],
                        'todolist_mode'    => $t['mode'],
                        'share_updated'    => $t['share_updated'],
                        'todolist_updated' => $t['todolist_updated'],
                        'todotask'         => array(),
                    );
                }
                // таски
                if ($t['task_id']) {
                    $shares[$t['todolist_id']]['todotask'][$t['task_id']] = array(
                        'task_id'      => $t['task_id'],
                        'task_name'    => $t['task_name'],
                        'status'       => $t['status'],
                        'task_updated' => $t['task_updated'],
                    );
                }
            }
        }
        $this->saveShares($shares);
        return $shares;
    }

    /**
     * Сохраняет все шары юзера в кеш
     */
    public function saveShares($shares)
    {
        $cache = Cache::getInstance(__CLASS__ . '-share');
        $cache->setValue($this->getId(), $shares);
    }

    /**
     * Удаляет все шары юзера из кеша
     */
    public function clearShares()
    {
        $cache = Cache::getInstance(__CLASS__ . '-share');
        $cache->delete($this->getId());
    }
}