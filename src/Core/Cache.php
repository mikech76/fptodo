<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 10.06.2017
 */

namespace Core;

use Core\Model;
use Core\Database;

/**
 * Class Cache - Эмуляция Memcache
 * @package Core
 */
class Cache
{
    protected static $_instance = array();

    /**
     * @var string Сессия приложения/префикс
     */
    private $_cacheSessid;

    private $db;

    /**
     * Cache constructor.
     * @param $modelName
     */
    protected function __construct($modelName)
    {
        $this->db = Database::getInstance();
        $this->_cacheSessid = $modelName;
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

    /**
     * @param $do
     * @param $id
     * @param mixed $value
     * @return mixed
     */
    protected function _do($do, $id, $value = null)
    {
        $return = null;

        switch ($do) {
            case 'get':
                $return = $this->db->getOne(
                    'SELECT val FROM memcache WHERE idkey=?s', $this->_cacheSessid . $id
                );
                return unserialize($return);
                break;

            case 'set':
                $data = array('idkey' => $this->_cacheSessid . $id, 'val' => serialize($value));
                $this->db->query(
                    'INSERT INTO memcache SET ?u ON DUPLICATE KEY UPDATE ?u', $data, $data
                );
                break;

            case 'delete':
                $this->db->query(
                    'DELETE FROM memcache WHERE idkey=?s', $this->_cacheSessid . $id
                );
        }

        return $return;
    }

    /**
     * очистить   кеш
     */
    public function clear()
    {
        $this->db->query(
            'TRUNCATE TABLE memcache'
        );
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
     * @param $key
     * @return mixed
     */
    public function getValue($key)
    {
        return $this->_do('get', $key);
    }

    /**
     * @param $key
     * @param $value
     */
    public function setValue($key, $value)
    {
        $this->_do('set', $key, $value);
    }

    /**
     * удаляет кеш модели
     * @param $key
     */
    public function delete($key)
    {
        $this->_do('delete', $key);
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}