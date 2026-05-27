<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_command_reports_json_status(): void
    {
        $this->artisan('qa:health --json')
            ->expectsOutputToContain('"status": "ok"')
            ->assertSuccessful();
    }
}
