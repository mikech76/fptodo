<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 04.07.2016
 * Time: 16:27
 */

namespace Model;

use Core\Model;
use Core\Request;

class Message extends Model
{
    const statusVisible = 1;
    const statusDeleted = 0;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Like
     */
    private $like;


    /**
     * Message constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        parent::__construct();

        $this->user = $user;
        $this->like = new Like($user);
    }

    /**
     * создания нового сообщения
     */
    public function insert()
    {
        // Сохраним пользователя
        $this->user->save();

        // текст сообщения
        $text = Request::get('text');

        if (strlen($text)) {
            $this->db->query('INSERT INTO message(text,created,updated,status,user_id) VALUES (?s,NOW(),NOW(),?i,?i)',
                $text, self::statusVisible, $this->user->getId());

            $id = $this->db->insertId();
        }
     }

    /**
     * сообщения, сщзданные/обновленные после $lastUpdate
     * @return array
     */
    public function getLatest()
    {
        $lastUpdate = Request::get('lastUpdate');

        $messages = $this->db->getInd('id',
            'SELECT UNIX_TIMESTAMP(m.created) AS created, m.id, m.text, m.user_id AS userId, u.name AS userName
                FROM message m
                LEFT JOIN `user` u ON u.id=m.user_id
                WHERE  m.status=?i AND m.updated >= FROM_UNIXTIME(?s) ORDER BY m.created', self::statusVisible, $lastUpdate);

        return $messages;
    }

    /**
     * сообщения, удаленные  после $lastUpdate
     * @return array|FALSE
     */
    public function getDeleted()
    {
        $lastUpdate = Request::get('lastUpdate');

        $messages = $this->db->getCol(
            'SELECT   m.id
                FROM message m
                WHERE  m.status=?i AND m.updated >= FROM_UNIXTIME(?s)', self::statusDeleted, $lastUpdate);

        return $messages;

    }

    /**
     * скрываем сообщение
     */
    public function delete()
    {
        $messageId = Request::getInteger('messageId');
        $this->db->query("UPDATE message SET status=?i, updated=NOW() WHERE id=?i AND user_id=?i",
            self::statusDeleted, $messageId, $this->user->getId());

        return array($messageId);
    }
}

