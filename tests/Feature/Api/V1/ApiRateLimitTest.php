<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    public function test_authenticated_api_requests_are_rate_limited_per_user(): void
    {
        config()->set('api.rate_limit_per_minute', 2);
        $this->actingAsUser();

        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();

        $this->getJson('/api/v1/auth/me')
            ->assertTooManyRequests()
            ->assertJsonPath(
                'message',
                'Zu viele API-Anfragen. Bitte versuche es in Kürze erneut.',
            )
            ->assertHeader('Retry-After');
    }

    public function test_rate_limit_allowance_is_isolated_between_users(): void
    {
        config()->set('api.rate_limit_per_minute', 1);

        $this->actingAsUser();
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertTooManyRequests();

        $secondUser = User::factory()->create();
        Sanctum::actingAs($secondUser);
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $secondUser->id);
    }
}
