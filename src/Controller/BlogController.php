<?php
/**
 * Created by PhpStorm.
 * User: Mike
 * Date: 03.07.2016
 * Time: 20:14
 */

namespace Controller;

use Core\Controller;
use Core\Request;
use Core\View;
use Model\Like;
use Model\Message;
use Model\User;

class BlogController extends Controller
{
    /**
     * @var array Данные для отправки
     */
    private $data = array();

    /**
     * Выбор действия/action
     */
    public function index()
    {
        switch (Request::get('action', 'none')) {
            case 'get':
                $this->getAction();
                break;

            case 'post':
                $this->postAction();
                break;

            case 'like':
                $this->likeAction();
                break;

            case 'delete':
                $this->deleteAction();
                break;

            case 'none':
                $this->data['error'] = 'Unknown action';
                break;
        }

        // создаем стандартный Вид
        $view = new View();
        $view->renderJson($this->data);
    }

    /**
     * Запрос актуальных данных Блога
     */
    private function getAction()
    {
        // текущий пользователь
        $user = new User();
        $this->data['user'] = $user->getArray();

        // новые сообщения
        $message = new Message($user);
        $latestMessages = $message->getLatest();
        $this->data['messages'] = $latestMessages;

        // удаленные сообщения
        $deletedMessages = $message->getDeleted();
        $this->data['deleted'] = $deletedMessages;

        // лайки
        $like = new Like($user);
        $this->data['like'] = $like->get(array_keys($latestMessages));

    }

    /**
     * новая запись Блога
     */
    private function postAction()
    {
        $user = new User();

        $message = new Message($user);
        $message->insert();

        $this->getAction();
    }

    /**
     * удалить запись / скрыть
     */
    private function deleteAction()
    {
        $user = new User();

        $message = new Message($user);
        $this->data['deleted'] = $message->delete();
    }

    /**
     * Лайки
     */
    private function likeAction()
    {
        $user = new User();

        $messageId = Request::getInteger('messageId');
        $like = new Like($user);
        $this->data['like'] = $like->toggle($messageId);
    }
}