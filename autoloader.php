<?php

include 'vendor/autoload.php';

spl_autoload_register(function ($className) {
    $baseNamespace = 'EveInUa\MultiConf';
    if (strpos($className, $baseNamespace) === FALSE) {
        return;
    }
    $className = str_replace($baseNamespace, '', $className);
    $className = trim($className, '\\');
    $classParts = explode('\\', $className);
    $fileName = array_pop($classParts) . '.php';
    $filePathParts = array_merge([__DIR__, 'src'], $classParts, [$fileName]);
    $filePath = implode(DIRECTORY_SEPARATOR, $filePathParts);
    if (file_exists($filePath)) {
        include $filePath;
    } else {
        return;
    }
});
