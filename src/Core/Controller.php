<?php

namespace Core;

/**
 * Created by PhpStorm.
 * User: Mikech
 * Date: 01.07.2016
 * Time: 17:04
 */
abstract class Controller
{
    /**
     * файл шаблона
     * @var string
     */
    protected $template;

    /**
     * @return string
     */
    public function render()
    {
        define('DIRSEP', DIRECTORY_SEPARATOR);
        $site_path = realpath(dirname(__FILE__) . DIRSEP . '..' . DIRSEP) . DIRSEP;
        define('site_path', $site_path);

        $file = site_path . 'Template' . DIRSEP . $this->template . '.html';

        $rendered = file_get_contents($file);

        return $rendered;
    }

    abstract protected function index();
}
