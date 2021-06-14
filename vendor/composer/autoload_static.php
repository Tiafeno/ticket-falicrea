<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2dfeb87ae955da45eedc5c8fe65ed315
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Liquid\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Liquid\\' => 
        array (
            0 => __DIR__ . '/..' . '/liquid/liquid/src/Liquid',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2dfeb87ae955da45eedc5c8fe65ed315::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2dfeb87ae955da45eedc5c8fe65ed315::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit2dfeb87ae955da45eedc5c8fe65ed315::$classMap;

        }, null, ClassLoader::class);
    }
}
