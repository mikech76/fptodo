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
        $login = substr(trim(Request::getSafeString('user_login')), 0, 30);
        if ($login) {

            $duplicate = self::load($login, 'login'); // Дубликат?

            if (!$duplicate) {
                $pass = substr(trim(Request::getSafeString('user_password')), 0, 30);
                if ($pass) {
                    $salt = md5(uniqid());
                    $password = md5($login . $pass . $salt);
                    //  создаем юзера
                    $db = Database::getInstance();
                    $db->query(
                        'INSERT INTO user(login,password,salt) VALUES (?s,?s,?s)', $login, $password, $salt
                    );
                    $id = $db->insertId();
                    if ($id) {
                        // авторизуем юзера
                        User::setSession('user_id', $id);
                        $user = self::load($id);

                        return $user;
                    }
                    throw new TodoException('user_create_error', 'Ошибка регистрации!');
                }
                throw new TodoException('user_password_bad', 'Недопустимый Пароль');
            }
            throw new TodoException('user_login_occupyed', 'Имя "' . $login . '" занято!');
        }
        throw new TodoException('user_login_bad', 'Недопустимый Логин');
    }

    /**
     * Авторизация
     * @test http://mikech.zapto.org/fptodo/?route=post&action=user_login&user_login=testuser&user_password=12345
     */
    public static function login()
    {
        $login = substr(trim(Request::getSafeString('user_login')), 0, 30);
        if ($login) {
            // загружаем юзера
            $user = self::load($login, 'login');
            if ($user) {
                $pass = substr(trim(Request::getSafeString('user_password')), 0, 30);
                $password = md5($login . $pass . $user->getSalt());
                if ($password == $user->getPassword()) {
                    User::setSession('user_id', $user->getId());

                    return $user;
                }
                throw new TodoException('user_password_bad', 'Не верный пароль!');
            }
            throw new TodoException('user_not_exist', 'Пользователь "' . $login . '"" не зарегистрирован!');
        }
        throw new TodoException('user_login_bad', 'Недопустимый Логин');
    }

    /**
     * Идентификация юзера
     * @return User
     * @throws TodoException
     */
    public static function auth()
    {
        $id = self::getSession('user_id');
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
            $shares = $db->getInd('todolist_id', 'SELECT * FROM share WHERE user_id=?i', $this->getId());
        }

        return $shares;
    }
}