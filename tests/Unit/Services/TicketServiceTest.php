<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TicketService();
    }

    public function test_create_sets_requester_and_open_status(): void
    {
        $user = User::factory()->create();

        $ticket = $this->service->create($user, [
            'title'       => 'Test Ticket',
            'description' => 'Beschreibung',
        ]);

        $this->assertEquals('Test Ticket', $ticket->title);
        $this->assertEquals($user->id, $ticket->requester_id);
        $this->assertEquals(TicketStatus::Open, $ticket->status);
    }

    public function test_create_records_initial_history_entry(): void
    {
        $user   = User::factory()->create();
        $ticket = $this->service->create($user, [
            'title'       => 'Test',
            'description' => 'Beschreibung',
        ]);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field'     => 'status',
            'old_value' => null,
            'new_value' => 'open',
        ]);
    }

    public function test_update_records_status_change_in_history(): void
    {
        $user   = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $this->service->update($ticket, $user, ['status' => 'in_progress']);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field'     => 'status',
            'old_value' => 'open',
            'new_value' => 'in_progress',
        ]);
    }

    public function test_resolving_ticket_sets_resolved_at(): void
    {
        $user   = User::factory()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $updated = $this->service->update($ticket, $user, ['status' => 'resolved']);

        $this->assertNotNull($updated->resolved_at);
        $this->assertEquals(TicketStatus::Resolved, $updated->status);
    }

    public function test_reopening_resolved_ticket_clears_resolved_at(): void
    {
        $user   = User::factory()->create();
        $ticket = Ticket::factory()->resolved()->create();

        $updated = $this->service->update($ticket, $user, ['status' => 'open']);

        $this->assertNull($updated->resolved_at);
    }

    public function test_add_comment_creates_comment_record(): void
    {
        $user   = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->service->addComment($ticket, $user, 'Kommentartext', false);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id'   => $ticket->id,
            'user_id'     => $user->id,
            'body'        => 'Kommentartext',
            'is_internal' => false,
        ]);
    }

    public function test_internal_comment_is_stored_correctly(): void
    {
        $user   = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->service->addComment($ticket, $user, 'Interne Notiz', true);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id'   => $ticket->id,
            'is_internal' => true,
        ]);
    }

    public function test_status_change_is_not_recorded_when_unchanged(): void
    {
        $user   = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $before = $ticket->history()->count();

        $this->service->update($ticket, $user, ['status' => 'open']);

        $this->assertEquals($before, $ticket->fresh()->history()->count());
    }
}
