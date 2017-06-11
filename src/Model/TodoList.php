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
    private $name;


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
     * @throws TodoException
     * @test http://mikech.zapto.org/fptodo/?route=post&action=todolist_create&todolist_name=%D0%9D%D0%B0%20%D0%BF%D0%B8%D0%BA%D0%BD%D0%B8%D0%BA
     */
    public static function create(User $user)
    {
        $name = substr(trim(Request::getSafeString('todolist_create')), 0, 100);
        if ($name) {
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO todolist(name) VALUES (?s)', $name
            );
            $id = $db->insertId();
            if($id){
                // создаем шару с юзером
                self::toShare( $user, $id, SHARE_OWNER);
            }
            throw new TodoException('todolist_create_error', 'Ошибка создания списка!');
        }
        throw new TodoException('todolist_name_bad', 'Недопустимое имя списка');
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