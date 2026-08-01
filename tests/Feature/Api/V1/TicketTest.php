<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    // ─── Index ────────────────────────────────────────────────────

    public function test_admin_can_list_all_tickets(): void
    {
        $this->actingAsAdmin();
        Ticket::factory()->count(3)->create();

        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'status', 'priority', 'requester']],
                'meta' => ['total', 'per_page', 'current_page'],
            ]);
    }

    public function test_normal_user_sees_only_own_tickets(): void
    {
        $user  = $this->actingAsUser();
        $other = User::factory()->create();

        Ticket::factory()->create(['requester_id' => $user->id]);
        Ticket::factory()->create(['requester_id' => $other->id]);

        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_tickets_can_be_filtered_by_status(): void
    {
        $this->actingAsAdmin();

        Ticket::factory()->open()->create();
        Ticket::factory()->resolved()->create();

        $this->getJson('/api/v1/tickets?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'open');
    }

    public function test_tickets_can_be_searched_by_title(): void
    {
        $this->actingAsAdmin();

        Ticket::factory()->create(['title' => 'VPN funktioniert nicht']);
        Ticket::factory()->create(['title' => 'Drucker defekt']);

        $this->getJson('/api/v1/tickets?search=VPN')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'VPN funktioniert nicht');
    }

    // ─── Store ────────────────────────────────────────────────────

    public function test_authenticated_user_can_create_ticket(): void
    {
        $user     = $this->actingAsUser();
        $category = TicketCategory::factory()->create();

        $response = $this->postJson('/api/v1/tickets', [
            'title'       => 'Mein Laptop startet nicht',
            'description' => 'Seit dem Update startet der Laptop nicht mehr.',
            'priority'    => 'high',
            'category_id' => $category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Mein Laptop startet nicht')
            ->assertJsonPath('status.value', 'open')
            ->assertJsonPath('requester.id', $user->id);

        $this->assertDatabaseHas('tickets', [
            'title'        => 'Mein Laptop startet nicht',
            'requester_id' => $user->id,
            'status'       => 'open',
        ]);
    }

    public function test_ticket_creation_requires_title_and_description(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/tickets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description']);
    }

    public function test_creating_ticket_records_history(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/v1/tickets', [
            'title'       => 'Test Ticket',
            'description' => 'Beschreibung',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $response->json('id'),
            'field'     => 'status',
            'new_value' => 'open',
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────

    public function test_can_show_single_ticket_with_relations(): void
    {
        $this->actingAsAdmin();
        $ticket = Ticket::factory()->create();

        $this->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'title', 'status', 'priority', 'requester', 'comments', 'attachments', 'history']
            ]);
    }

    public function test_returns_404_for_nonexistent_ticket(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/tickets/99999')
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────

    public function test_agent_can_update_ticket_status(): void
    {
        $agent  = $this->actingAsAgent();
        $ticket = Ticket::factory()->open()->create();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field'     => 'status',
            'old_value' => 'open',
            'new_value' => 'in_progress',
        ]);
    }

    public function test_resolving_ticket_sets_resolved_at_timestamp(): void
    {
        $this->actingAsAgent();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertOk();

        $this->assertDatabaseMissing('tickets', [
            'id'          => $ticket->id,
            'resolved_at' => null,
        ]);
    }

    public function test_closing_ticket_preserves_resolved_at(): void
    {
        $this->actingAsAgent();
        $ticket = Ticket::factory()->resolved()->create();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertOk();

        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $this->actingAsAgent();
        $ticket = Ticket::factory()->create();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'ungueltig'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_rejects_illegal_status_transition(): void
    {
        $this->actingAsAgent();
        $ticket = Ticket::factory()->open()->create();

        // 'closed' ist ein gültiger Enum-Wert, aber open → closed ist laut
        // Übergangsmatrix nicht erlaubt — muss als 409 Conflict abgelehnt werden.
        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertStatus(409);

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);
    }

    public function test_admin_is_also_bound_by_transition_matrix(): void
    {
        // Bewusst keine Sonderrolle für Admins — sonst existieren zwei
        // Geschäftsregeln parallel.
        $this->actingAsAdmin();
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'open'])
            ->assertStatus(409);
    }

    public function test_waiting_for_requester_can_be_set_by_agent(): void
    {
        $this->actingAsAgent();
        $ticket = Ticket::factory()->inProgress()->create();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'waiting_for_requester'])
            ->assertOk();
    }

    public function test_requester_reply_automatically_reopens_waiting_ticket(): void
    {
        $requester = $this->actingAsUser();
        $ticket    = Ticket::factory()->create([
            'requester_id' => $requester->id,
            'status'       => TicketStatus::WaitingForRequester,
        ]);

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body'        => 'Hier ist die angeforderte Information.',
            'is_internal' => false,
        ])->assertCreated();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
    }

    // ─── Kommentare ───────────────────────────────────────────────

    public function test_user_can_add_public_comment_to_own_ticket(): void
    {
        $user   = $this->actingAsUser();
        $ticket = Ticket::factory()->create(['requester_id' => $user->id]);

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body'        => 'Das Problem besteht weiterhin.',
            'is_internal' => false,
        ])->assertCreated();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id'   => $ticket->id,
            'body'        => 'Das Problem besteht weiterhin.',
            'is_internal' => false,
        ]);
    }

    public function test_comment_requires_body(): void
    {
        $this->actingAsAgent();
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_unauthenticated_user_cannot_access_tickets(): void
    {
        $this->getJson('/api/v1/tickets')
            ->assertUnauthorized();
    }
}
