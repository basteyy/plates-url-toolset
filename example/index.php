<?php
/**
 * @author xzit.online <hallo@xzit.email>
 * @website https://github.com/basteyy
 * @website https://xzit.online
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__, 1));

if (!file_exists($autoload = ROOT . '/vendor/autoload.php')) {
    die('Please run composer install. Not found: ' . $autoload);
}

include $autoload;

$engine = new \League\Plates\Engine(ROOT . '/workspace/tpl');

$url = new \basteyy\PlatesUrlToolset\PlatesUrlToolset();
$url->addNamedUrl('home', '/home');

$engine->loadExtension($url);

echo $engine->render('page');