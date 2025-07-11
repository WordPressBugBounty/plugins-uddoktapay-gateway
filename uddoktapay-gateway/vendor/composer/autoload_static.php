<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1410a8b3cb225854a5abb0146dd1968b
{
    public static $prefixLengthsPsr4 = array (
        'U' => 
        array (
            'UddoktaPay\\UddoktaPayGateway\\' => 29,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'UddoktaPay\\UddoktaPayGateway\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'UddoktaPay\\UddoktaPayGateway\\APIHandler' => __DIR__ . '/../..' . '/src/APIHandler.php',
        'UddoktaPay\\UddoktaPayGateway\\Blocks\\InternationalBlocks' => __DIR__ . '/../..' . '/src/Blocks/InternationalBlocks.php',
        'UddoktaPay\\UddoktaPayGateway\\Blocks\\LocalBlocks' => __DIR__ . '/../..' . '/src/Blocks/LocalBlocks.php',
        'UddoktaPay\\UddoktaPayGateway\\Enums\\OrderStatus' => __DIR__ . '/../..' . '/src/Enums/OrderStatus.php',
        'UddoktaPay\\UddoktaPayGateway\\InternationalGateway' => __DIR__ . '/../..' . '/src/InternationalGateway.php',
        'UddoktaPay\\UddoktaPayGateway\\LocalGateway' => __DIR__ . '/../..' . '/src/LocalGateway.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1410a8b3cb225854a5abb0146dd1968b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1410a8b3cb225854a5abb0146dd1968b::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit1410a8b3cb225854a5abb0146dd1968b::$classMap;

        }, null, ClassLoader::class);
    }
}
