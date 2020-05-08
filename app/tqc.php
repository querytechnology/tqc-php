<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;

const DEFAULT_COMPILER = 'https://compile.tinyqueries.com';
const POSSIBLE_CONFIG_FILE_NAMES = [
    'tinyqueries.json',
    'tinyqueries.yml',
    'tinyqueries.yaml',
];

function getApiKey() : string
{
    $dotenv = new Dotenv();
    try {
        $dotenv->overload('.env');
    } catch (\Exception $e) {
    }
    $key = $_ENV['TINYQUERIES_API_KEY'] ?? getenv('TINYQUERIES_API_KEY');

    if (!$key) {
        throw new \Exception('No API key found - please add your TinyQueries API key to your ENV variables');
    }

    return trim($key);
}

function readConfig() : array
{
    foreach (POSSIBLE_CONFIG_FILE_NAMES as $possibleConfigFile) {
        if (file_exists($possibleConfigFile)) {
            $content = file_get_contents($possibleConfigFile);
            if (!$content) {
                throw new \Exception('Error reading config file ' . $possibleConfigFile);
            }
            list ($dummy,$extension) = explode('.', $possibleConfigFile);
            switch ($extension) {
                case 'json':
                    $config = json_decode($content, true);
                    break;
                case 'yml':
                case 'yaml':
                    $config = Yaml::parse($content);
                    break;
                default:
                    throw new \Exception('Unsupported config file format');
            }
            if (!$config) {
                throw new \Exception('Error decoding config file ' . $possibleConfigFile);
            }
            standardizeConfig($config);
            $config['fileName'] = $possibleConfigFile;
            return $config;
        }
    }
    throw new \Exception('No config file found in current folder - Please create a config file tinyqueries.yaml');
}

function standardizeConfig(array &$config)
{
    if (!isset($config['project']['label'])) {
        $config['project']['label'] = '';
    }
    if (!isset($config['compiler']['server'])) {
        $config['compiler']['server'] = DEFAULT_COMPILER;
    }
    if (!isset($config['compiler']['version'])) {
        $config['compiler']['version'] = 'latest';
    }
    if (!isset($config['compiler']['input'])) {
        $config['compiler']['input'] = 'tinyqueries';
    }
}

function isFileToUpload($filename) : bool
{
    if (in_array(
            $filename,
            array_merge(
                ['.', '..'],
                POSSIBLE_CONFIG_FILE_NAMES
            )
        )) {
        return false;
    }
    return true;
}

function addFolderRecursivelyToZip(&$zip, $folder, $folderRelative = '')
{
    $content = scandir($folder);
    foreach ($content as $element) {
        if (isFileToUpload($element)) {
            $path = $folder . DIRECTORY_SEPARATOR . $element;
            $pathRelative = $folderRelative
                ? $folderRelative . '/' . $element
                : $element;
            if (is_dir($path)) {
                $zip->addEmptyDir($pathRelative);
                addFolderRecursivelyToZip($zip, $path, $pathRelative);
            } else {
                $zip->addFile($path, $pathRelative);
            }
        }
    }
}

function sendCompileRequest(array $config, string $apiKey)
{
    if (!function_exists('curl_init')) {
        throw new \Exception('Cannot compile queries - curl extension for PHP is not installed');
    }

    if (!file_exists($config['compiler']['input'])) {
        throw new \Exception('Cannot find input folder ' . $config['compiler']['input']);
    }

    $ch = curl_init();

    if (!$ch) {
        throw new \Exception('Cannot initialize curl');
    }

    $zip = new ZipArchive();
    $tag = md5(rand());
    $zipFileName = 'upload-' . $tag . '.zip';

    if ($zip->open($zipFileName, ZipArchive::CREATE)!==true) {
        throw new \Exception("Cannot open $zipFileName");
    }

    $zip->addFile($config['fileName']);

    addFolderRecursivelyToZip($zip, $config['compiler']['input']);

    $zip->close();

    $postBody = [
        'tq_code' => curl_file_create(realpath($zipFileName)),
    ];

    curl_setopt($ch, CURLOPT_URL, $config['compiler']['server']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Expect:',
        'Authorization: Bearer ' . $apiKey
    ]);

    echo "Uploading zip to compiler..\n";
    $responseRaw = curl_exec($ch);

    curl_close($ch);

    unlink($zipFileName);

    if ($responseRaw === false) {
        throw new \Exception('Did not receive a response from the query compiler; no internet?');
    }

    $status = null;

    $response = explode("\r\n\r\n", $responseRaw, 2);

    // Find the HTTP status code
    $matches = [];
    if (preg_match('/^HTTP.* ([0-9]+) /', $response[0], $matches)) {
        $status = intval($matches[1]);
    }

    if ($status != 200) {
        $responseDecoded = @json_decode($response[1], true);
        $errorMessage = ($responseDecoded)
            ? $responseDecoded['error']
            : 'Received status ' . $status . ' - ' . $response[1];
        throw new \Exception($errorMessage);
    }

    echo "Extracting received zip..\n";

    $zip = new ZipArchive();
    $zipFileName = 'download-' . $tag . '.zip';
    file_put_contents($zipFileName, $response[1]);
    $r = $zip->open($zipFileName);
    if ($r !== true) {
        throw new \Exception('Error opening ZIP coming from compiler - error code = ' . $r);
    }
    for ($i=0; $i<$zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $content = $zip->getFromName($filename);
        if (!preg_match('/\/$/', $filename)) {
            $r = @file_put_contents($filename, $content);
            if ($r === false) {
                throw new \Exception('Cannot write file ' . $filename);
            }
        }
    }
    $zip->close();
    unlink($zipFileName);
}

try {
    echo "\033[1;33mTiny\033[1;37mQueries\033[0m\n";
    $apiKey = getApiKey();
    $config = readConfig();
    echo "- project: " . $config['project']['label'] . "\n";
    echo "- server: " . $config['compiler']['server'] . "\n";
    echo "- version: " . $config['compiler']['version'] . "\n";
    echo "- input folder: " . $config['compiler']['input'] . "\n";
    sendCompileRequest($config, $apiKey);
    echo "\033[1;37mReady\033[0m\n";
} catch (\Exception $e) {
    echo "\033[1;31m"
        . $e->getMessage()
        . "\033[0m\n";
    exit(1);
}

exit(0);
