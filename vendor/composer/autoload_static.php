<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit543c3bcaa2d68e2e9d9117fb7807af64
{
    public static $files = array (
        '7b4ea634fa5bd1ccea7e5ca039562961' => __DIR__ . '/..' . '/colshrapnel/safemysql/safemysql.class.php',
    );

    public static $fallbackDirsPsr4 = array (
        0 => __DIR__ . '/../..' . '/src',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->fallbackDirsPsr4 = ComposerStaticInit543c3bcaa2d68e2e9d9117fb7807af64::$fallbackDirsPsr4;

        }, null, ClassLoader::class);
    }
}
