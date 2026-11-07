<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_the_health_check_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_cors_allows_each_origin_in_frontend_url(): void
    {
        // config/cors.php reads FRONTEND_URL as a comma-separated list.
        $saved = [$_ENV['FRONTEND_URL'] ?? null, $_SERVER['FRONTEND_URL'] ?? null];
        $_ENV['FRONTEND_URL'] = $_SERVER['FRONTEND_URL'] = 'https://itrm98.github.io/, http://localhost:5173';
        $origins = (require base_path('config/cors.php'))['allowed_origins'];
        [$_ENV['FRONTEND_URL'], $_SERVER['FRONTEND_URL']] = $saved;

        $this->assertSame(['https://itrm98.github.io', 'http://localhost:5173'], $origins);
    }

    public function test_requests_through_an_https_proxy_generate_https_urls(): void
    {
        $this->get('/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.5'])->assertOk();

        $this->assertTrue(request()->secure());
        $this->assertStringStartsWith('https://', url('/x'));
    }
}
