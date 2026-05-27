<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateMediaRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_media_prune_command_dry_runs(): void
    {
        $this->artisan('qa:prune-private-media --days=30')
            ->expectsOutputToContain('Dry-run only')
            ->assertSuccessful();
    }
}
