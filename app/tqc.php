<?php

require_once __DIR__ . '/../vendor/autoload.php';

const DEFAULT_COMPILER = 'https://compile.tinyqueries.com';
const POSSIBLE_CONFIG_FILE_NAMES = [
    'tinyqueries.json',
    'tinyqueries.yml',
    'tinyqueries.yaml',
];

function pathAbs($path) : string
{
    // Check if $path is a relative or absolute path
    $pathAbs = ($path && preg_match('/^\./', $path))
        ? realpath(dirname(__FILE__) . "/" . $path)
        : realpath($path);

    if (!$pathAbs) {
        throw new \Exception("Cannot find path '" . $path . "'");
    }

    return $pathAbs;
}

function readConfig() : StdClass
{
    foreach (POSSIBLE_CONFIG_FILE_NAMES as $possibleConfigFile) {
        if (file_exists($possibleConfigFile)) {
            list ($dummy,$extension) = explode('.', $possibleConfigFile);
            switch ($extension) {
                case 'json':
                    $config = readJsonConfigFile($possibleConfigFile);
                    break;
                case 'yml':
                case 'yaml':
                    $config = readYamlConfigFile($possibleConfigFile);
                    break;
                default:
                    throw new \Exception('Unsupported config file format');
            }
            standardizeConfig($config);
            return $config;
        }
    }
    throw new \Exception('No config-file found in current folder');
}

function readJsonConfigFile(string $configFile) : StdClass
{
    $content = file_get_contents($configFile);
    if (!$content) {
        throw new \Exception('Error reading config file');
    }

    $config = json_decode($content);
    if (!$config) {
        throw new \Exception('Error decoding json config file');
    }

    return $config;
}

function readYamlConfigFile(string $configFile) : StdClass
{
    // Load XML file
    $config = @simplexml_load_file($configFile);

    // Check required fields
    if (!$config) {
        throw new \Exception("Cannot read configfile " . $configFile);
    }
    if (!$config->project) {
        throw new \Exception("Tag 'project' not found in " . $configFile);
    }
    if (!$config->project['label']) {
        throw new \Exception("Field label not found in project tag of " . $configFile);
    }
    if (!$config->compiler) {
        throw new \Exception("Tag 'compiler' not found in " . $configFile);
    }
    if (!$config->compiler['output']) {
        throw new \Exception("Field 'output' not found in compiler tag of " . $configFile);
    }

    $result = new \StdClass();

    // Import project fields
    $result->project = new \StdClass();
    $result->project->label	= (string) $config->project['label'];

    // Import compiler fields
    $result->compiler = new \StdClass();
    $result->compiler->apiKey = (string) $config->compiler['api_key'];
    $result->compiler->input = ((string) $config->compiler['input']) ? (string) $config->compiler['input'] : null;
    $result->compiler->output = ((string) $config->compiler['output']) ? (string) $config->compiler['output'] : null;
    $result->compiler->server = ((string) $config->compiler['server']) ? (string) $config->compiler['server'] : null;
    $result->compiler->version = ((string) $config->compiler['version']) ? (string) $config->compiler['version'] : null;
    $result->compiler->lang = ((string) $config->compiler['lang']) ? (string) $config->compiler['lang'] : null;
    $result->compiler->outputFieldNames = ((string) $config->compiler['output_field_names']) ? (string) $config->compiler['output_field_names'] : null;

    return $result;
}

function standardizeConfig(StdClass &$config)
{
    // Check for mandatory fields
    if (!isset($config->project)) {
        throw new \Exception("Tag 'project' not found in config file");
    }
    if (!isset($config->project->label)) {
        throw new \Exception("Field label not found in project tag of config file");
    }
    if (!isset($config->compiler)) {
        throw new \Exception("Tag 'compiler' not found in config file");
    }
    if (!isset($config->compiler->apiKey)) {
        throw new \Exception("Tag 'apiKey' not found in config file");
    }
    if (!isset($config->compiler->output)) {
        throw new \Exception("Field 'output' not found in compiler tag of config file");
    }

    // Set (non mandatory) missing fields to default value
    if (!isset($config->compiler->input)) {
        $config->compiler->input = null;
    }
    if (!isset($config->compiler->version)) {
        $config->compiler->version = null;
    }
    if (!isset($config->compiler->lang)) {
        $config->compiler->lang = null;
    }
    if (!isset($config->compiler->outputFieldNames)) {
        $config->compiler->outputFieldNames = null;
    }
    if (!isset($config->compiler->server)) {
        $config->compiler->server = DEFAULT_COMPILER;
    }

    // Set abs paths
    if (isset($config->compiler->input)) {
        $config->compiler->input = pathAbs($config->compiler->input);
    }
    if (isset($config->compiler->output)) {
        if (is_string($config->compiler->output)) {
            $outputFolder = $config->compiler->output;
            $defaultLang = $config->compiler->lang ?? 'sql';
            $config->compiler->output = new StdClass();
            $config->compiler->output->$defaultLang = $outputFolder;
        }
        foreach ($config->compiler->output as $lang => $folder) {
            $config->compiler->output->$lang = pathAbs($folder);
        }
    }
    // Add "v" to version if missing
    if (isset($config->compiler->version)
        && !preg_match("/^v/", $config->compiler->version)) {
        $config->compiler->version = 'v' . $config->compiler->version;
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

function uploadFiles($config)
{
    if (!function_exists('curl_init')) {
        throw new \Exception('Cannot compile queries - curl extension for PHP is not installed');
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

    $folderInput = $config->compiler->input ?? (file_exists('tiny') ? 'tiny' : '.');

    echo "folder: $folderInput\n";
    addFolderRecursivelyToZip($zip, $folderInput);

    $zip->close();

    $postBody = [
        'api_key' => $config->compiler->apiKey,
        'project' => $config->project->label,
        'version' => $config->compiler->version,
        'zip' => curl_file_create(realpath($zipFileName)),
        'output' => 'site',
        'lang' => $config->compiler->lang,
        'output_field_names' => $config->compiler->outputFieldNames
    ];

    curl_setopt($ch, CURLOPT_URL, $config->compiler->server);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);

    echo "Uploading zip to compiler\n";
    $responseRaw = curl_exec($ch);

    curl_close($ch);

    unlink($zipFileName);

    if ($responseRaw === false) {
        throw new \Exception('Did not receive a response from the query compiler; no internet?');
    }

    $status = null;

    $response = explode("\r\n\r\n", $responseRaw, 2);

    // Find the HTTP status code
    $matches = array();
    if (preg_match('/^HTTP.* ([0-9]+) /', $response[0], $matches)) {
        $status = intval($matches[1]);
    }

    if ($status != 200) {
        $error = @simplexml_load_string($response[1]);
        $errorMessage = ($error)
            ? $error->message
            : 'Received status ' . $status . ' - ' . $response[1];
        throw new \Exception($errorMessage);
    }

    $zip = new ZipArchive();
    $zipFileName = 'download-' . $tag . '.zip';
    file_put_contents($zipFileName, $response[1]);
    $zip->open($zipFileName);
    for ($i=0; $i<$zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $content = $zip->getFromName($filename);
        $path = explode('/', $filename);
        foreach ($config->compiler->output as $lang => $folder) {
            if ($path[0] == $lang) {
                if ($path[1]) {
                    file_put_contents($folder . DIRECTORY_SEPARATOR . $path[1], $content);
                }
            }
        }
    }
    $zip->close();
    unlink($zipFileName);
}

try {
    echo "-----------------\n";
    echo "TQ compile client\n";
    echo "-----------------\n";
    $config = readConfig();
    echo "project: " . $config->project->label . "\n";
    echo "compiler: " . $config->compiler->server . "\n";
    echo "version: " . $config->compiler->version . "\n";
    uploadFiles($config);
    echo "Ready\n";
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}

exit(0);
