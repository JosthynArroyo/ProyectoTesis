<?php

$basePath = dirname(__DIR__);
$dbConnection = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: null;
$dbDatabase = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: null;

if ($dbConnection === 'sqlite' && is_string($dbDatabase) && $dbDatabase !== '' && $dbDatabase !== ':memory:') {
    $processToken = $_ENV['TEST_TOKEN'] ?? $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN') ?: getmypid();
    $databasePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbDatabase);

    if (! preg_match('/^[A-Za-z]:\\\\|^\//', $databasePath)) {
        $databasePath = $basePath.DIRECTORY_SEPARATOR.$databasePath;
    }

    $directory = dirname($databasePath);
    $filename = pathinfo($databasePath, PATHINFO_FILENAME);
    $extension = pathinfo($databasePath, PATHINFO_EXTENSION);
    $isolatedPath = $directory.DIRECTORY_SEPARATOR.$filename.'-'.$processToken.($extension !== '' ? '.'.$extension : '');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    if (! file_exists($isolatedPath)) {
        touch($isolatedPath);
    }

    putenv('DB_DATABASE='.$isolatedPath);
    $_ENV['DB_DATABASE'] = $isolatedPath;
    $_SERVER['DB_DATABASE'] = $isolatedPath;
}

require $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
