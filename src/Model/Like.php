<?php
/**
 * Created by PhpStorm.
 * User: Mike
 * Date: 04.07.2016
 * Time: 20:14
 */

namespace Model;

use Core\Model;
use Core\Request;

class Like extends Model
{
    /**
     * @var User
     */
    private $user;

    /**
     * Like constructor
     * @param User $user
     */
    public function __construct(User $user)
    {
        parent::__construct();

        $this->user = $user;
    }

    /**
     * Устанавливает/отзывает  лайк
     * @param int $messageId
     * @return int
     */
    public function toggle($messageId)
    {
        // Сохраним пользователя
        $this->user->save();

        $res = $this->db->getOne('SELECT val FROM `like` WHERE user_id=?i AND message_id=?i', $this->user->getId(), $messageId);

        if ($res === '1') {
            // val==1
            $this->db->query('UPDATE `like` SET val=0, updated=NOW() WHERE user_id=?i AND message_id=?i', $this->user->getId(), $messageId);
        } elseif ($res === '0') {
            $this->db->query('UPDATE `like` SET val=1, updated=NOW() WHERE user_id=?i AND message_id=?i', $this->user->getId(), $messageId);
        } else {
            $this->db->query('INSERT INTO `like`(user_id,message_id,val) VALUES(?i,?i,1)', $this->user->getId(), $messageId);
        }

        // возврат сколько лайков у сообщения
        $count = $this->db->getInd('message_id', 'SELECT message_id, SUM(val) AS cnt, MAX(val*user_id = ?i) AS me
                                                  FROM `like` WHERE message_id=?i
                                                  GROUP BY message_id', $this->user->getId(), $messageId);

        return ($count) ? $count : array($messageId => array('cnt' => 0, 'me' => 0));
    }

    /**
     * загружает Лайки
     * @param $messageIds
     * @return array
     */
    public function get($messageIds)
    {
        $lastUpdate = Request::get('lastUpdate');

        $liked = $this->db->getInd('message_id', 'SELECT l.message_id , SUM(l.val) AS cnt, MAX(l.val*l.user_id = ?i) AS me
                FROM `like` l
                WHERE l.message_id IN(?a) OR l.message_id IN(
                    SELECT l2.message_id
                    FROM `like` l2
                    WHERE l2.updated >= FROM_UNIXTIME(?s)
                ) GROUP BY l.message_id', $this->user->getId(), $messageIds, $lastUpdate);

        return $liked;
    }

}