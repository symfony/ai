<?php
// Minimal bootstrap for testing

$basePath = __DIR__;

// Load exception interfaces in dependency order
$exceptionOrder = [
    'ExceptionInterface',
    'TransportExceptionInterface',
    'HttpExceptionInterface',
    'ClientExceptionInterface',
    'RedirectionExceptionInterface',
    'ServerExceptionInterface',
    'TimeoutExceptionInterface',
    'DecodingExceptionInterface',
];

foreach ($exceptionOrder as $file) {
    $path = $basePath . '/vendor/symfony/http-client-contracts/Exception/' . $file . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

// Load http-client contracts
require_once $basePath . '/vendor/symfony/http-client-contracts/HttpClientInterface.php';
require_once $basePath . '/vendor/symfony/http-client-contracts/ResponseInterface.php';
require_once $basePath . '/vendor/symfony/http-client-contracts/ResponseStreamInterface.php';
require_once $basePath . '/vendor/symfony/http-client-contracts/ChunkInterface.php';

// Load http-client exceptions
require_once $basePath . '/vendor/symfony/http-client/Exception/TransportException.php';
require_once $basePath . '/vendor/symfony/http-client/Exception/ClientException.php';
require_once $basePath . '/vendor/symfony/http-client/Exception/HttpException.php';
require_once $basePath . '/vendor/symfony/http-client/Exception/RedirectionException.php';
require_once $basePath . '/vendor/symfony/http-client/Exception/ServerException.php';
require_once $basePath . '/vendor/symfony/http-client/Exception/TimeoutException.php';

// Load http-client responses
require_once $basePath . '/vendor/symfony/http-client/Response/ResponseStream.php';
require_once $basePath . '/vendor/symfony/http-client/Response/MockResponse.php';
require_once $basePath . '/vendor/symfony/http-client/Response/JsonMockResponse.php';

// Load http-client traits and main class
require_once $basePath . '/vendor/symfony/http-client/HttpClientTrait.php';
require_once $basePath . '/vendor/symfony/http-client/MockHttpClient.php';

// Load deprecation contracts
if (file_exists($basePath . '/vendor/symfony/deprecation-contracts/function.php')) {
    require_once $basePath . '/vendor/symfony/deprecation-contracts/function.php';
}

// Load UID
require_once $basePath . '/vendor/symfony/uid/Uuid.php';

// Set up a simple auto loader for the tests
spl_autoload_register(function ($class) use ($basePath) {
    if (strpos($class, 'Symfony\\') === 0) {
        $path = str_replace('\\', '/', substr($class, 9));
        $file = $basePath . '/vendor/symfony/' . $path . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    if (strpos($class, 'Symfony\\AI\\') === 0) {
        $path = str_replace('Symfony\\AI\\', '', $class);
        $path = str_replace('\\', '/', $path);
        $file = $basePath . '/../../../../../' . $path . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    return false;
});
