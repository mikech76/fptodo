<?php
/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 03.07.2016
 * Time: 20:45
 */

namespace Core;

/**
 * Базовый Вид
 * Class View
 * @package Core
 */
class View
{
    /**
     * Вывод Html файла
     * @param $data
     * @param $template
     */
    public function renderHtml($data, $template)
    {
        $this->render('html', $data, $template);
    }

    /**
     * Вывод Json строки
     * @param $data
     */
    public function renderJson($data)
    {
        $this->render('json', $data, '');
    }

    /**
     * Вывод Server-side event строки
     * @param $data
     */
    public function renderSSE($data)
    {
        $this->render('sse', $data, '');
    }

    /**
     * Вывод данных
     * @param $type типа вывода {html|json}
     * @param $data данные
     * @param $template файл шаблона
     * @throws \Exception
     */
    public function render($type, $data, $template)
    {
        $output = '';

        switch ($type) {
            case 'html':
                define('DIRSEP', DIRECTORY_SEPARATOR);
                $site_path = realpath(dirname(__FILE__) . DIRSEP . '..' . DIRSEP) . DIRSEP;
                define('site_path', $site_path);

                $file = site_path . 'Template' . DIRSEP . $template;

                $output = file_get_contents($file);
                break;

            case 'json':
                //header('Content-Type: application/json');
                $output = json_encode($data); //, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                break;

            case 'sse':
                $output = json_encode($data);
                break;

            default:
                throw new \Exception('Неопределен тип View:render.');
        }

        echo $output;
    }


}