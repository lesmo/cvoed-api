<?php

// Composer y su magia
require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL); 

// Cargar y configurar Slim
$app = new \Slim\App;

// Cargar los cosos del IMSS
require __DIR__ . '/imss.php';

// Correr
$app->run();