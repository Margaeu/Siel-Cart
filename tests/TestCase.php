<?php

namespace Tests;

use App\Models\Theme;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Theme::activeCached() memoizes in a static, which is request-scoped
     * under PHP-FPM but process-scoped here -- without this, a theme resolved
     * in one test would still be held after the next test's database has been
     * rolled back and rebuilt.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Theme::forgetActiveMemo();
    }
}
