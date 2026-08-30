<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    #[Test]
    public function portal_contract_is_valid_json_and_declares_every_consumed_operation(): void
    {
        $contents = file_get_contents(base_path('openapi/portal-v1.json'));

        $this->assertNotFalse($contents);

        /** @var array<string, mixed> $schema */
        $schema = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('3.1.0', $schema['openapi']);
        $this->assertSame('1.0.0', $schema['info']['version']);

        $expectedOperations = [
            'POST /auth/login' => 'login',
            'POST /auth/logout' => 'logout',
            'GET /auth/me' => 'getCurrentUser',
            'GET /tickets' => 'listTickets',
            'POST /tickets' => 'createTicket',
            'GET /tickets/{id}' => 'getTicket',
            'POST /tickets/{id}/comments' => 'addTicketComment',
            'GET /assets' => 'listAssets',
            'GET /kb/articles' => 'listKbArticles',
        ];

        foreach ($expectedOperations as $operation => $operationId) {
            [$method, $path] = explode(' ', $operation, 2);
            $this->assertSame($operationId, $schema['paths'][$path][strtolower($method)]['operationId'] ?? null);
        }
    }

    #[Test]
    public function all_non_login_operations_require_bearer_authentication(): void
    {
        $schema = json_decode(
            (string) file_get_contents(base_path('openapi/portal-v1.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame([['sanctumToken' => []]], $schema['security']);
        $this->assertSame([], $schema['paths']['/auth/login']['post']['security']);
        $this->assertSame('http', $schema['components']['securitySchemes']['sanctumToken']['type']);
        $this->assertSame('bearer', $schema['components']['securitySchemes']['sanctumToken']['scheme']);
    }
}
