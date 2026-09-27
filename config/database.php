<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

// Aiven requires TLS for MySQL connections. The CA cert lives in the repo
// (it isn't a secret) so this resolves on any OS/host without a per-machine
// env var; MYSQL_ATTR_SSL_CA still overrides it if a machine needs a
// different cert path. A relative override is read from the project root;
// absolute Linux (/home/...) and Windows (C:\...) paths are used as given.
$mysqlSslCa = trim((string) env('MYSQL_ATTR_SSL_CA', ''));

if ($mysqlSslCa !== '' && ! preg_match('~^(/|\\\\|[A-Za-z]:[\\\\/])~', $mysqlSslCa)) {
    $mysqlSslCa = base_path($mysqlSslCa);
}

if ($mysqlSslCa === '') {
    $defaultCa = storage_path('certs/aiven-ca.pem');
    $mysqlSslCa = is_readable($defaultCa) ? $defaultCa : null;
}

$mysqlSslCaReadable = $mysqlSslCa !== null && is_readable($mysqlSslCa);

// DB_SSL_REQUIRE_VERIFIED_CA turns a missing CA from "connect without
// verifying" into a hard failure. It is read while the config is loaded, so
// on Azure `php artisan config:cache` in startup.sh stops the start with this
// message instead of quietly connecting to whatever answers on DB_HOST.
$mysqlRequireVerifiedCa = filter_var(env('DB_SSL_REQUIRE_VERIFIED_CA', false), FILTER_VALIDATE_BOOL);

// Server-certificate verification defaults to on whenever a CA is present;
// DB_SSL_VERIFY_SERVER_CERT only exists to switch it off on a machine that
// has to, and is refused outright when a verified CA is required.
$mysqlVerifySetting = env('DB_SSL_VERIFY_SERVER_CERT');
$mysqlVerifyServerCert = $mysqlVerifySetting === null || $mysqlVerifySetting === ''
    ? $mysqlSslCa !== null
    : filter_var($mysqlVerifySetting, FILTER_VALIDATE_BOOL);

if ($mysqlRequireVerifiedCa && ! $mysqlSslCaReadable) {
    throw new RuntimeException(
        'DB_SSL_REQUIRE_VERIFIED_CA is true but no readable CA certificate was found'
        .($mysqlSslCa !== null ? " at [{$mysqlSslCa}]" : '')
        .'. Commit the Aiven CA to storage/certs/aiven-ca.pem or point MYSQL_ATTR_SSL_CA at it.'
    );
}

if ($mysqlRequireVerifiedCa && ! $mysqlVerifyServerCert) {
    throw new RuntimeException(
        'DB_SSL_VERIFY_SERVER_CERT cannot be false while DB_SSL_REQUIRE_VERIFIED_CA is true.'
    );
}

// Built with plain ifs rather than array_filter(), which would drop a
// deliberate `false` for the verify flag along with the empty values.
// PDO::MYSQL_ATTR_* is deprecated from PHP 8.5 in favour of Pdo\Mysql::ATTR_*,
// so the new constant is only used where the old one is gone.
//
// An explicitly configured CA is passed on even when this process cannot
// read it: PDO then refuses to connect, which is louder than dropping TLS
// verification without a word.
$mysqlOptions = [];

if (extension_loaded('pdo_mysql')) {
    if ($mysqlSslCa !== null) {
        $mysqlOptions[defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : Mysql::ATTR_SSL_CA] = $mysqlSslCa;
    }

    $verifyConstant = match (true) {
        defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') => PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT,
        defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') => constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT'),
        default => null,
    };

    if ($verifyConstant !== null && $mysqlSslCa !== null) {
        $mysqlOptions[$verifyConstant] = $mysqlVerifyServerCert;
    }

    // Bound how long establishing a connection may take. The app runs on Azure
    // (Malaysia West) against Aiven MySQL (Singapore), so every connection is a
    // cross-border TCP + TLS handshake, and a free-tier database can be slow to
    // accept the first one after it has been idle.
    //
    // Without this the ceiling is whatever the driver was built with:
    // mysqlnd.connect_timeout is not set in php.ini here, so the effective
    // limit is implicit and can differ between the local machine, CI and the
    // Azure image. An unbounded connect occupies a PHP-FPM worker for the whole
    // wait, and the B1 plan has few workers -- so a database that stops
    // answering takes the entire site down rather than the one page that needed
    // it. startup.sh already retries migrations for the same reason; this is the
    // request-time half of that.
    //
    // Scope, so nobody mistakes this for a query timeout: pdo_mysql maps
    // PDO::ATTR_TIMEOUT onto the driver's *connect* timeout only. A query that
    // is slow once connected still runs to completion -- bounding that needs a
    // read timeout, which PDO does not expose. PDO::ATTR_TIMEOUT is core PDO
    // (not one of the MYSQL_ATTR_* constants deprecated in PHP 8.5), so it
    // needs no version guard.
    //
    // DB_CONNECT_TIMEOUT=0 restores the driver default for a host that needs it.
    $mysqlConnectTimeout = (int) env('DB_CONNECT_TIMEOUT', 10);

    if ($mysqlConnectTimeout > 0) {
        $mysqlOptions[PDO::ATTR_TIMEOUT] = $mysqlConnectTimeout;
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => $mysqlOptions,
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => $mysqlOptions,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
