<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The SMTP transport must carry a finite timeout.
 *
 * Nothing sends mail in the background -- production runs
 * QUEUE_CONNECTION=sync -- so an admin moving an order to "processing" waits
 * for the whole SMTP conversation inside their own request. Left unset,
 * Laravel skips setTimeout() (MailManager guards on isset(), which null
 * fails) and Symfony falls back to default_socket_timeout: 60 seconds per
 * step, across a conversation of many steps, until max_execution_time kills
 * the request at 300s. On the B1 plan that is a worker held hostage by a mail
 * server, and there are very few workers to lose.
 *
 * Measured against an unroutable host, a configured timeout is honoured
 * almost exactly: 5s -> 5.15s, 10s -> 10.10s.
 */
class MailTimeoutTest extends TestCase
{
    public function test_the_smtp_mailer_has_a_finite_timeout(): void
    {
        $timeout = config('mail.mailers.smtp.timeout');

        $this->assertNotNull(
            $timeout,
            'A null timeout is not passed to Symfony at all, leaving the 60s-per-step default in place.'
        );

        $this->assertIsFloat($timeout);
        $this->assertGreaterThan(0, $timeout);
        $this->assertSame(10.0, $timeout);
    }

    /**
     * The value has to survive Laravel's isset() guard in MailManager, which
     * is the specific thing `null` failed.
     */
    public function test_the_timeout_survives_the_isset_guard_that_null_failed(): void
    {
        $config = config('mail.mailers.smtp');

        $this->assertTrue(isset($config['timeout']));
    }
}
