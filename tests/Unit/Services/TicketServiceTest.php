<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketStatusTransitionException;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TicketService;
    }

    public function test_attachment_row_is_retained_when_remote_deletion_returns_false(): void
    {
        $ticket = Ticket::factory()->create();
        $uploader = User::factory()->create();
        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $uploader->id,
            'filename' => 'evidence.pdf',
            'disk' => 's3',
            'path' => "ticket-attachments/{$ticket->id}/random-name.pdf",
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        $remoteDisk = Mockery::mock(FilesystemAdapter::class);
        $remoteDisk->shouldReceive('delete')
            ->once()
            ->with($attachment->path)
            ->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($remoteDisk);

        try {
            $this->service->deleteAttachment($attachment);
            $this->fail('A failed remote deletion must abort database deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Der Ticket-Anhang konnte nicht aus dem Speicher gelöscht werden.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('ticket_attachments', ['id' => $attachment->id]);
    }

    public function test_create_sets_requester_and_open_status(): void
    {
        $user = User::factory()->create();

        $ticket = $this->service->create($user, [
            'title' => 'Test Ticket',
            'description' => 'Beschreibung',
        ]);

        $this->assertEquals('Test Ticket', $ticket->title);
        $this->assertEquals($user->id, $ticket->requester_id);
        $this->assertEquals(TicketStatus::Open, $ticket->status);
    }

    public function test_create_records_initial_history_entry(): void
    {
        $user = User::factory()->create();
        $ticket = $this->service->create($user, [
            'title' => 'Test',
            'description' => 'Beschreibung',
        ]);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field' => 'status',
            'old_value' => null,
            'new_value' => 'open',
        ]);
    }

    public function test_update_records_status_change_in_history(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $this->service->update($ticket, $user, ['status' => 'in_progress']);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field' => 'status',
            'old_value' => 'open',
            'new_value' => 'in_progress',
        ]);
    }

    public function test_resolving_ticket_sets_resolved_at(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->inProgress()->create();

        $updated = $this->service->update($ticket, $user, ['status' => 'resolved']);

        $this->assertNotNull($updated->resolved_at);
        $this->assertEquals(TicketStatus::Resolved, $updated->status);
    }

    public function test_reopening_resolved_ticket_clears_resolved_at(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->resolved()->create();

        // Per the transition matrix, reopening goes through "in_progress",
        // not directly back to "open".
        $updated = $this->service->update($ticket, $user, ['status' => 'in_progress']);

        $this->assertNull($updated->resolved_at);
    }

    public function test_closing_resolved_ticket_preserves_resolved_at(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->resolved()->create();
        $resolvedAt = $ticket->resolved_at;

        $updated = $this->service->update($ticket, $user, ['status' => 'closed']);

        $this->assertNotNull($updated->resolved_at);
        $this->assertEquals($resolvedAt->timestamp, $updated->resolved_at->timestamp);
        $this->assertNotNull($updated->closed_at);
    }

    /**
     * Regression: repeatedly sending the same status (e.g. together with a
     * priority change) must NOT reset resolved_at to "now" again — that
     * would falsify the real historical resolution time.
     */
    public function test_resending_same_status_does_not_reset_resolved_at(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->resolved()->create();
        $originalResolvedAt = $ticket->resolved_at;

        $this->travel(5)->minutes();

        $updated = $this->service->update($ticket, $user, [
            'status' => 'resolved',
            'priority' => 'high',
        ]);

        $this->assertEquals($originalResolvedAt->timestamp, $updated->resolved_at->timestamp);
    }

    public function test_add_comment_creates_comment_record(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->service->addComment($ticket, $user, 'Kommentartext', false);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'Kommentartext',
            'is_internal' => false,
        ]);
    }

    public function test_internal_comment_is_stored_correctly(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->service->addComment($ticket, $user, 'Interne Notiz', true);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
    }

    public function test_status_change_is_not_recorded_when_unchanged(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $before = $ticket->history()->count();

        $this->service->update($ticket, $user, ['status' => 'open']);

        $this->assertEquals($before, $ticket->fresh()->history()->count());
    }

    /**
     * Regression: assignee_id is nullable — isset() incorrectly treats an
     * explicit null value as "not sent". Clearing an assignment must still
     * show up in the audit trail.
     */
    public function test_unassigning_ticket_records_history(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $ticket = Ticket::factory()->create(['assignee_id' => $assignee->id]);

        $this->service->update($ticket, $user, ['assignee_id' => null]);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field' => 'assignee_id',
            'old_value' => (string) $assignee->id,
            'new_value' => null,
        ]);

        $this->assertNull($ticket->fresh()->assignee_id);
    }

    public function test_reassigning_from_null_records_history(): void
    {
        $user = User::factory()->create();
        $assignee = User::factory()->create();
        $ticket = Ticket::factory()->create(['assignee_id' => null]);

        $this->service->update($ticket, $user, ['assignee_id' => $assignee->id]);

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field' => 'assignee_id',
            'old_value' => null,
            'new_value' => (string) $assignee->id,
        ]);
    }

    public function test_resending_same_null_assignee_does_not_record_history(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['assignee_id' => null]);

        $before = $ticket->history()->count();

        $this->service->update($ticket, $user, ['assignee_id' => null]);

        $this->assertEquals($before, $ticket->fresh()->history()->count());
    }

    // ─── Transition matrix ──────────────────────────────────────────

    public function test_update_throws_exception_for_invalid_transition(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $this->expectException(InvalidTicketStatusTransitionException::class);

        $this->service->update($ticket, $user, ['status' => 'closed']);
    }

    public function test_invalid_transition_does_not_change_ticket_status(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        try {
            $this->service->update($ticket, $user, ['status' => 'closed']);
        } catch (InvalidTicketStatusTransitionException) {
            // expected
        }

        $this->assertEquals(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_requester_public_comment_on_waiting_ticket_reopens_it(): void
    {
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'requester_id' => $requester->id,
            'status' => TicketStatus::WaitingForRequester,
        ]);

        $this->service->addComment($ticket, $requester, 'Hier die angeforderte Info.', false);

        $this->assertEquals(TicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field' => 'status',
            'old_value' => 'waiting_for_requester',
            'new_value' => 'in_progress',
        ]);
    }

    public function test_internal_comment_does_not_trigger_status_change(): void
    {
        $agent = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => TicketStatus::WaitingForRequester]);

        $this->service->addComment($ticket, $agent, 'Interne Notiz', true);

        $this->assertEquals(TicketStatus::WaitingForRequester, $ticket->fresh()->status);
    }

    public function test_agent_public_comment_does_not_trigger_status_change(): void
    {
        // Only the requester themselves triggers the automatic transition —
        // an agent comment on someone else's ticket does not.
        $agent = User::factory()->create();
        $ticket = Ticket::factory()->create(['status' => TicketStatus::WaitingForRequester]);

        $this->service->addComment($ticket, $agent, 'Rückfrage beantwortet?', false);

        $this->assertEquals(TicketStatus::WaitingForRequester, $ticket->fresh()->status);
    }
}
