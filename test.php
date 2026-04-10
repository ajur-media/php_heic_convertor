<?php

use AJUR\Toolkit\HEIC_Convertor;

require_once __DIR__ . '/vendor/autoload.php';

if (!(PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? ''))) {
    return false;
}

if (empty($argv[1])) {
    die('Need file');
}

try {
    $converter = new HEIC_Convertor([
        'quality' => 85,
        'logger' => function($msg, $level) {
            echo "[$level] $msg\n";
        }
    ]);

    // Проверяем файл
    $diagnosis = $converter->diagnose($argv[1]);
    print_r($diagnosis);

    // Конвертируем
    $output = $converter->convert($argv[1], 'output.jpg');
    echo "Готово: $output\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}