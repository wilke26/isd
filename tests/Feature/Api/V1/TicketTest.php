<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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
        $user = $this->actingAsUser();
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

    public function test_ticket_list_rejects_unbounded_or_invalid_filters(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/tickets?per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/v1/tickets?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    // ─── Store ────────────────────────────────────────────────────

    public function test_authenticated_user_can_create_ticket(): void
    {
        $user = $this->actingAsUser();
        $category = TicketCategory::factory()->create();

        $response = $this->postJson('/api/v1/tickets', [
            'title' => 'Mein Laptop startet nicht',
            'description' => 'Seit dem Update startet der Laptop nicht mehr.',
            'priority' => 'high',
            'category_id' => $category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Mein Laptop startet nicht')
            ->assertJsonPath('data.status.value', 'open')
            ->assertJsonPath('data.requester.id', $user->id);

        $this->assertDatabaseHas('tickets', [
            'title' => 'Mein Laptop startet nicht',
            'requester_id' => $user->id,
            'status' => 'open',
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
            'title' => 'Test Ticket',
            'description' => 'Beschreibung',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $response->json('data.id'),
            'field' => 'status',
            'new_value' => 'open',
        ]);
    }

    public function test_requester_can_link_a_currently_assigned_asset(): void
    {
        $requester = $this->actingAsUser();
        $asset = Asset::factory()->create();
        AssetAssignment::create([
            'asset_id' => $asset->id,
            'user_id' => $requester->id,
            'assigned_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/tickets', [
            'title' => 'Problem mit meinem Laptop',
            'description' => 'Das Gerät startet nicht.',
            'asset_id' => $asset->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.asset.id', $asset->id);
        $this->assertDatabaseHas('tickets', [
            'id' => $response->json('data.id'),
            'asset_id' => $asset->id,
            'requester_id' => $requester->id,
        ]);
    }

    public function test_requester_cannot_link_an_unassigned_or_returned_asset(): void
    {
        $requester = $this->actingAsUser();
        $unassignedAsset = Asset::factory()->create();
        $returnedAsset = Asset::factory()->create();
        AssetAssignment::create([
            'asset_id' => $returnedAsset->id,
            'user_id' => $requester->id,
            'assigned_at' => now()->subDay(),
            'returned_at' => now(),
        ]);

        foreach ([$unassignedAsset, $returnedAsset] as $asset) {
            $this->postJson('/api/v1/tickets', [
                'title' => 'Unzulässige Asset-Verknüpfung',
                'description' => 'Dieses Gerät ist nicht aktuell zugewiesen.',
                'asset_id' => $asset->id,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('asset_id');
        }

        $this->assertDatabaseMissing('tickets', ['title' => 'Unzulässige Asset-Verknüpfung']);
    }

    public function test_staff_can_link_any_existing_asset(): void
    {
        $this->actingAsAgent();
        $asset = Asset::factory()->create();

        $this->postJson('/api/v1/tickets', [
            'title' => 'Asset durch Support verknüpft',
            'description' => 'Support ordnet das betroffene Gerät zu.',
            'asset_id' => $asset->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.asset.id', $asset->id);
    }

    // ─── Show ─────────────────────────────────────────────────────

    public function test_can_show_single_ticket_with_relations(): void
    {
        $this->actingAsAdmin();
        $ticket = Ticket::factory()->create();

        $this->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'title', 'status', 'priority', 'requester', 'comments', 'attachments', 'history'],
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
        $agent = $this->actingAsAgent();
        $ticket = Ticket::factory()->open()->create();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');

        $this->assertDatabaseHas('ticket_history', [
            'ticket_id' => $ticket->id,
            'field' => 'status',
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
            'id' => $ticket->id,
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

        // 'closed' is a valid enum value, but per the transition matrix,
        // open → closed is not allowed — must be rejected as a 409 Conflict.
        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertStatus(409);

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);
    }

    public function test_admin_is_also_bound_by_transition_matrix(): void
    {
        // Deliberately no special role for admins — otherwise two sets of
        // business rules would exist in parallel.
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
        $ticket = Ticket::factory()->create([
            'requester_id' => $requester->id,
            'status' => TicketStatus::WaitingForRequester,
        ]);

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body' => 'Hier ist die angeforderte Information.',
            'is_internal' => false,
        ])->assertCreated();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
    }

    // ─── Comments ───────────────────────────────────────────────────

    public function test_user_can_add_public_comment_to_own_ticket(): void
    {
        $user = $this->actingAsUser();
        $ticket = Ticket::factory()->create(['requester_id' => $user->id]);

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body' => 'Das Problem besteht weiterhin.',
            'is_internal' => false,
        ])->assertCreated();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'body' => 'Das Problem besteht weiterhin.',
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

    public function test_requester_can_upload_attachment_to_own_ticket(): void
    {
        Storage::fake('attachments');

        $requester = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        Sanctum::actingAs($requester);
        $file = UploadedFile::fake()->create('screenshot.png', 100, 'image/png');

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file]);

        $response->assertCreated();
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'filename' => 'screenshot.png',
            'disk' => 'attachments',
        ]);

        $attachment = TicketAttachment::where('ticket_id', $ticket->id)->sole();
        Storage::disk('attachments')->assertExists($attachment->path);
    }

    public function test_attachment_mime_type_is_detected_from_content_not_client_header(): void
    {
        Storage::fake('attachments');

        $requester = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);
        Sanctum::actingAs($requester);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'isd-upload-');
        $this->assertNotFalse($temporaryPath);
        file_put_contents($temporaryPath, 'Plain text incident evidence.');

        // A real UploadedFile (rather than Laravel's fake file) keeps the
        // client-provided header separate from Fileinfo's content detection.
        $file = new UploadedFile(
            $temporaryPath,
            'evidence.txt',
            'application/x-msdownload',
            null,
            true,
        );

        $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'text/plain');

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'filename' => 'evidence.txt',
            'mime_type' => 'text/plain',
        ]);
    }

    public function test_requester_cannot_upload_attachment_to_others_ticket(): void
    {
        Storage::fake('attachments');

        $ticket = Ticket::factory()->create();
        $this->actingAsUser();
        $file = UploadedFile::fake()->create('file.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file]);

        $response->assertNotFound();
    }

    public function test_staff_can_upload_attachment_to_any_ticket(): void
    {
        Storage::fake('attachments');

        $ticket = Ticket::factory()->create();
        $this->actingAsAgent();
        $file = UploadedFile::fake()->create('log.txt', 50, 'text/plain');

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file]);

        $response->assertCreated();
    }

    public function test_attachment_can_be_downloaded_by_ticket_viewer(): void
    {
        Storage::fake('attachments');

        $requester = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        Sanctum::actingAs($requester);
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $attachmentId = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])->json('data.id');

        $response = $this->get("/api/v1/tickets/{$ticket->id}/attachments/{$attachmentId}");

        $response->assertOk();
    }

    public function test_attachment_uses_and_records_the_configured_private_disk(): void
    {
        Storage::fake('archive');
        config(['filesystems.ticket_attachments_disk' => 'archive']);

        $requester = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);
        Sanctum::actingAs($requester);

        $attachmentId = $this->postJson(
            "/api/v1/tickets/{$ticket->id}/attachments",
            ['file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')],
        )->assertCreated()->json('data.id');

        $attachment = TicketAttachment::findOrFail($attachmentId);

        $this->assertSame('archive', $attachment->disk);
        Storage::disk('archive')->assertExists($attachment->path);
        $this->get("/api/v1/tickets/{$ticket->id}/attachments/{$attachmentId}")->assertOk();
    }

    public function test_uploader_can_delete_own_attachment(): void
    {
        Storage::fake('attachments');

        $requester = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        Sanctum::actingAs($requester);
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $attachmentId = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])->json('data.id');

        $response = $this->deleteJson("/api/v1/tickets/{$ticket->id}/attachments/{$attachmentId}");

        $response->assertOk();
        $this->assertDatabaseMissing('ticket_attachments', ['id' => $attachmentId]);
        Storage::disk('attachments')->assertDirectoryEmpty("ticket-attachments/{$ticket->id}");
    }

    public function test_other_requester_cannot_delete_attachment(): void
    {
        Storage::fake('attachments');

        $uploader = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $uploader->id]);

        Sanctum::actingAs($uploader);
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $attachmentId = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file])->json('data.id');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->deleteJson("/api/v1/tickets/{$ticket->id}/attachments/{$attachmentId}");

        $response->assertNotFound();
    }

    public function test_attachment_upload_rejects_invalid_file_type(): void
    {
        Storage::fake('attachments');

        $requester = $this->createUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        Sanctum::actingAs($requester);
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/attachments", ['file' => $file]);

        $response->assertUnprocessable();
    }
}
