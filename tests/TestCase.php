<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Seed roles/permissions so registration + RBAC work in every test. */
    protected bool $seed = true;
}
