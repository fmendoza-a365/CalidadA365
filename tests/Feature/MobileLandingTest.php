<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileLandingTest extends TestCase
{
    public function test_landing_shows_android_app_download(): void
    {
        $this->assertFileExists(public_path('downloads/qa365-mobile.apk'));

        $this->get('/')
            ->assertOk()
            ->assertSee('QA365 Mobile')
            ->assertSee('qa365-mobile.apk')
            ->assertSee('Descargar APK');
    }
}
