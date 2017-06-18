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
 * Class User
 *
 * @package Model
 *          Пользователь блога
 */
class User extends Model
{
    /**
     * Логин
     *
     * @var string
     */
    public $login;

    /**
     * Хеш пароля
     *
     * @var string
     */
    private $password;

    /**
     * Соль
     *
     * @var string
     */
    private $salt;

    /**
     * Списки
     *
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
     * @test http://mysite/?route=post&action=user_register&user_login=testuser&user_password=12345
     */
    public static function create()
    {
        // имя юзера
        $login = Request::getLogin('user_login', [
            'user_login_bad',
            'Недопустимый Логин',
        ]);

        // проверка дубликата
        $duplicate = self::load($login, 'login'); // Дубликат?
        if ($duplicate) {
            MyException::go([
                'user_login_occupyed',
                'Имя "' . $login . '" занято!',
            ]);
        }

        // пароль
        $pass     = Request::getPassword('user_password', [
            'user_password_bad',
            'Недопустимый Пароль',
        ]);
        $salt     = md5(uniqid());
        $password = md5($login . $pass . $salt);

        //  создаем юзера
        $db = Database::getInstance();
        $db->query('INSERT INTO user(login,password,salt) VALUES (?s,?s,?s)', $login, $password, $salt);

        // запись создана
        $id   = $db->insertId();
        $user = self::load($id, 'id', [
            'user_create_error',
            'Ошибка регистрации!',
        ]);

        // авторизуем юзера
        User::setSession('user_id', $id);

        return $user;
    }

    /**
     * Авторизация
     * @test http://mysite/?route=post&action=user_login&user_login=testuser&user_password=12345
     */
    public static function login()
    {
        // имя юзера
        $login = Request::getLogin('user_login', [
            'user_login_bad',
            'Недопустимый Логин',
        ]);

        // загружаем юзера
        $user = self::load($login, 'login', [
            'user_not_exist',
            'Пользователь "' . $login . '" не зарегистрирован!',
        ]);

        // сверка пароля
        $pass = Request::getPassword('user_password', [
            'user_password_bad',
            'Недопустимый Пароль',
        ]);

        $password = md5($login . $pass . $user->getSalt());
        if ($password != $user->getPassword()) {
            MyException::go([
                'user_password_bad',
                'Не верный пароль!',
            ]);
        }

        User::setSession('user_id', $user->getId());

        return $user;
    }

    /**
     * Идентификация юзера
     *
     * @return User
     * @throws MyException
     */
    public static function auth()
    {
        $id = self::getSession('user_id');
        return self::load($id, 'id', [
            'user_no_auth',
            'Пользователь не авторизован!',
        ]);
    }

    /**
     * Загружает юзера
     *
     * @param mixed  $param
     * @param string $key {id|login}
     *
     * @return User
     * @throws MyException
     */
    public static function load($param, $key = 'id', $exteption = null)
    {
        if ($param) {
            $cache = Cache::getInstance(__CLASS__);

            if (in_array($key, [
                'id',
                'login',
            ])) {
                if ($key == 'id' && $user = $cache->get((int)$param)) {
                    return $user;
                }
                // из базы
                $db       = Database::getInstance();
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

        if ($exteption) {
            MyException::go($exteption);
        }

        return null;
    }

    /**
     * Возвращает все шары юзера
     *
     * @return array
     */
    public function loadShares()
    {
        $cache  = Cache::getInstance(__CLASS__ . '-share');
        $shares = $cache->get($this->getId());
        if (! $shares) {
            $db         = Database::getInstance();
            $sharesData = $db->getAll('
                    SELECT s.user_id, s.todolist_id, s.`mode`, s.updated AS share_updated, 
                             tl.name AS todolist_name, tl.updated AS todolist_updated,
                             tt.id AS task_id, tt.name AS task_name, tt.`status`, tt.updated AS task_updated 
                    FROM share AS s
                    LEFT JOIN todolist AS tl ON tl.id=s.todolist_id
                    LEFT JOIN todotask AS tt ON tl.id=tt.todolist_id
                    WHERE s.user_id=?i', $this->getId());

            $shares = [];
            foreach ($sharesData as $t) {
                // списки
                if (! isset($shares[$t['todolist_id']])) {
                    $shares[$t['todolist_id']] = [
                        'user_id'          => $t['user_id'],
                        'todolist_id'      => $t['todolist_id'],
                        'todolist_name'    => $t['todolist_name'],
                        'todolist_mode'    => $t['mode'],
                        'todolist_updated' => max($t['todolist_updated'], $t['share_updated']),
                        'todotask'         => [],
                    ];
                }
                // таски
                if ($t['task_id']) {
                    $shares[$t['todolist_id']]['todotask'][$t['task_id']] = [
                        'task_id'      => $t['task_id'],
                        'task_name'    => $t['task_name'],
                        'status'       => $t['status'],
                        'task_updated' => $t['task_updated'],
                    ];
                    $shares[$t['todolist_id']]['todolist_updated']        =
                        max($shares[$t['todolist_id']]['todolist_updated'],
                            $shares[$t['todolist_id']]['todotask'][$t['task_id']]['task_updated']);
                }
            }
        }
        $this->saveSharesCache($shares);
        return $shares;
    }

    /**
     * Сохраняет все шары юзера в кеш
     */
    public function saveSharesCache($shares)
    {
        $cache = Cache::getInstance(__CLASS__ . '-share');
        $cache->setValue($this->getId(), $shares);
    }

    /**
     * Удаляет все шары юзера из кеша
     */
    public function clearSharesCache()
    {
        $cache = Cache::getInstance(__CLASS__ . '-share');
        $cache->delete($this->getId());
    }
}