<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_configured_portal_origin_can_preflight_authorized_requests(): void
    {
        config()->set('cors.allowed_origins', ['https://portal.example.test']);

        $response = $this->json('OPTIONS', '/api/v1/auth/me', [], [
            'Origin' => 'https://portal.example.test',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://portal.example.test')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('content-type', $allowedHeaders);
    }

    public function test_unconfigured_origin_is_not_reflected_as_allowed(): void
    {
        config()->set('cors.allowed_origins', ['https://portal.example.test']);

        $response = $this->json('OPTIONS', '/api/v1/tickets', [], [
            'Origin' => 'https://attacker.example.test',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://portal.example.test')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $this->assertNotSame(
            'https://attacker.example.test',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
