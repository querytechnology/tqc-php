<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TinyQueries\Compiler;

try {
    $compiler = new Compiler();
    echo "\033[1;33mTiny\033[1;37mQueries\033[0m\n";
    $apiKey = $compiler->getApiKey();
    $config = $compiler->readConfig();
    echo "- project: " . $config['project']['label'] . "\n";
    echo "- server: " . $config['compiler']['server'] . "\n";
    echo "- version: " . $config['compiler']['version'] . "\n";
    echo "- input folder: " . $config['compiler']['input'] . "\n";
    $compiler->compile($config, $apiKey, true);
    echo "\033[1;37mReady\033[0m\n";
} catch (\Exception $e) {
    echo "\033[1;31m"
        . $e->getMessage()
        . "\033[0m\n";
    exit(1);
}

exit(0);
