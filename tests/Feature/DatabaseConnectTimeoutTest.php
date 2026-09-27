<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

/**
 * The mysql connection must keep a bounded connect timeout.
 *
 * Production is Azure (Malaysia West) against Aiven MySQL (Singapore), so every
 * connection is a cross-border handshake and the free tier can be slow to accept
 * one after idling. Without an explicit limit the ceiling is whatever the driver
 * was built with -- mysqlnd.connect_timeout is not set in php.ini -- and an
 * unbounded connect holds a PHP-FPM worker for the whole wait. The B1 plan has
 * few workers, so a database that stops answering takes the entire site down
 * rather than the one page that needed it.
 *
 * These assertions exist because the setting is invisible in day-to-day work:
 * nothing locally is slow enough to notice it missing, so a regression would
 * only surface on the live site, under load.
 */
class DatabaseConnectTimeoutTest extends TestCase
{
    public function test_the_mysql_connection_bounds_how_long_a_connect_may_take(): void
    {
        $options = config('database.connections.mysql.options');

        $this->assertIsArray($options);

        $this->assertArrayHasKey(
            PDO::ATTR_TIMEOUT,
            $options,
            'The mysql connection has no connect timeout, so an unreachable database can hold a PHP-FPM worker indefinitely.'
        );

        $this->assertSame(10, $options[PDO::ATTR_TIMEOUT]);
    }

    /**
     * The timeout is appended to the TLS options rather than replacing them.
     * Aiven refuses unencrypted connections, and DB_SSL_REQUIRE_VERIFIED_CA
     * makes a missing CA a hard failure on Azure -- so silently dropping the
     * CA here would either break every connection or weaken verification.
     */
    public function test_the_connect_timeout_does_not_displace_the_tls_options(): void
    {
        $options = config('database.connections.mysql.options');

        // PDO::MYSQL_ATTR_* is deprecated from PHP 8.5 in favour of
        // Pdo\Mysql::ATTR_*, matching the guard in config/database.php.
        $sslCaConstant = match (true) {
            defined('PDO::MYSQL_ATTR_SSL_CA') => PDO::MYSQL_ATTR_SSL_CA,
            defined('Pdo\Mysql::ATTR_SSL_CA') => constant('Pdo\Mysql::ATTR_SSL_CA'),
            default => null,
        };

        if ($sslCaConstant === null) {
            $this->markTestSkipped('No pdo_mysql SSL CA constant on this build.');
        }

        $this->assertArrayHasKey(
            $sslCaConstant,
            $options,
            'The Aiven CA option vanished from the mysql connection; the connect timeout must be added alongside the TLS options, not in place of them.'
        );

        $this->assertArrayHasKey(PDO::ATTR_TIMEOUT, $options);
    }
}
