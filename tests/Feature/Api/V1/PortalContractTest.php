<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\KbArticle;
use Tests\TestCase;

class PortalContractTest extends TestCase
{
    public function test_requester_portal_contract_for_profile_and_ticket_flow(): void
    {
        $user = $this->actingAsUser();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $created = $this->postJson('/api/v1/tickets', [
            'title' => 'VPN funktioniert nicht',
            'description' => 'Die Verbindung wird sofort getrennt.',
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'VPN funktioniert nicht')
            ->assertJsonPath('status.value', 'open')
            ->assertJsonPath('status.label', 'Offen');

        $ticketId = $created->json('id');

        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticketId)
            ->assertJsonPath('data.0.title', 'VPN funktioniert nicht')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->postJson("/api/v1/tickets/{$ticketId}/comments", [
            'body' => 'Der Fehler tritt auch im LAN auf.',
        ])
            ->assertCreated()
            ->assertExactJson(['message' => 'Kommentar hinzugefügt.']);

        $this->getJson("/api/v1/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.id', $ticketId)
            ->assertJsonPath('data.comments.0.body', 'Der Fehler tritt auch im LAN auf.')
            ->assertJsonPath('data.comments.0.user.id', $user->id)
            ->assertJsonMissingPath('data.comments.0.author');
    }

    public function test_requester_portal_contract_for_knowledge_base_search(): void
    {
        $this->actingAsUser();

        KbArticle::factory()->published()->create([
            'title' => 'VPN unter Windows einrichten',
            'body' => 'Schritt-für-Schritt-Anleitung für Windows.',
        ]);
        KbArticle::factory()->published()->create([
            'title' => 'Drucker einrichten',
            'body' => 'Anleitung für den Bürodrucker.',
        ]);

        $this->getJson('/api/v1/kb/articles?search=VPN')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'VPN unter Windows einrichten')
            ->assertJsonPath('data.0.body', 'Schritt-für-Schritt-Anleitung für Windows.')
            ->assertJsonPath('data.0.status.value', 'published')
            ->assertJsonMissingPath('data.0.excerpt')
            ->assertJsonMissingPath('data.0.content');
    }
}
