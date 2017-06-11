<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 10.06.2017
 */

namespace Core;

use Core\Model;

/**
 * Class Cache - Кеш на Сессиях
 * @package Core
 */
class Cache
{
    protected static $_instance = array();

    /**
     * @var string Сессия клиента PHPSESSID
     */
    private $_phpSessid;
    /**
     * @var string Сессия приложения/префикс
     */
    private $_cacheSessid;

    /**
     * Cache constructor.
     * @param $modelName
     */
    protected function __construct($modelName)
    {
        $this->_phpSessid = session_id();
        $this->_cacheSessid = CACHESESSID . '-' . $modelName;
    }

    /**
     * Получить кеш-инстанс для модели
     * @param string $modelName
     * @return mixed
     */
    public static function getInstance($modelName)
    {

        $modelName = strtr($modelName, '\\', '-');
        if (!array_key_exists($modelName, self::$_instance) || self::$_instance[$modelName] === null) {
            self::$_instance[$modelName] = new self($modelName);
        }

        return self::$_instance[$modelName];
    }

    protected function _do($do, $id, $val = null)
    {
        $return = null;
        // начать сессию
        session_write_close();
        session_id($this->_cacheSessid . $id);
        session_start();

        switch ($do) {
            case 'get':
                $return = array_key_exists('id' . $id, $_SESSION) ? $_SESSION['id' . $id] : null;
                if (!$return) {
                    session_destroy();
                }
                break;

            case 'set':
                $_SESSION['id' . $id] = $val;
                break;

            case 'delete':
                session_destroy();
        }

        // закрыть сессию
        session_write_close();
        session_id($this->_phpSessid);
        session_start();

        return $return;
    }


    /**
     * Возвращает модель из кеша
     * @param $id
     * @return Model
     */
    public function get($id)
    {
        return $this->_do('get', $id);
    }

    /**
     * Сохранияет модель в кеш
     * @param \Core\Model $model
     */
    public function set(Model $model)
    {
        $this->_do('set', $model->getId(), $model);
    }

    /**
     * удаляет кеш модели
     * @param $key
     */
    public function delete($id)
    {
        $this->_do('delete', $id);
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}