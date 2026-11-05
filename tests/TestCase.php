<?php

namespace Tests;

use App\Support\WarehouseService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ID gudang di-cache statis; database tes di-reset tiap tes, jadi cache ikut dikosongkan.
        WarehouseService::flushCache();
    }
}
