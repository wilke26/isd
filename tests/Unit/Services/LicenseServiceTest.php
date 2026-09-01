<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\LicenseAssignmentException;
use App\Models\Asset;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private LicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LicenseService;
    }

    public function test_assigning_a_user_consumes_a_seat_derived_from_assignments(): void
    {
        $license = License::factory()->create(['seats_total' => 1]);
        $user = User::factory()->create();

        $assignment = $this->service->assignToUser($license, $user);

        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertEquals(1, $license->seatsUsed());
        $this->assertEquals(0, $license->availableSeats());
        $this->assertFalse($license->hasAvailableSeats());
    }

    public function test_assigning_an_asset_uses_the_same_seat_pool(): void
    {
        $license = License::factory()->create(['seats_total' => 2]);
        $asset = Asset::factory()->create();

        $assignment = $this->service->assignToAsset($license, $asset);

        $this->assertEquals($asset->id, $assignment->asset_id);
        $this->assertNull($assignment->user_id);
        $this->assertEquals(1, $license->availableSeats());
    }

    public function test_seat_limit_prevents_over_allocation(): void
    {
        $license = License::factory()->create(['seats_total' => 1]);
        $this->service->assignToUser($license, User::factory()->create());

        $this->expectException(LicenseAssignmentException::class);
        $this->expectExceptionMessage('keine freien Sitzplätze');

        $this->service->assignToUser($license, User::factory()->create());
    }

    public function test_same_target_cannot_receive_a_license_twice(): void
    {
        $license = License::factory()->create(['seats_total' => 2]);
        $user = User::factory()->create();
        $this->service->assignToUser($license, $user);

        $this->expectException(LicenseAssignmentException::class);
        $this->expectExceptionMessage('besitzt die Lizenz bereits');

        $this->service->assignToUser($license, $user);
    }

    public function test_expired_license_cannot_be_assigned(): void
    {
        $license = License::factory()->expired()->create();

        $this->expectException(LicenseAssignmentException::class);
        $this->expectExceptionMessage('abgelaufene Lizenz');

        $this->service->assignToUser($license, User::factory()->create());
    }

    public function test_license_expiring_today_can_still_be_assigned(): void
    {
        $license = License::factory()->create(['expires_at' => today()]);

        $this->service->assignToUser($license, User::factory()->create());

        $this->assertEquals(1, $license->seatsUsed());
    }

    public function test_total_cannot_be_reduced_below_existing_assignments(): void
    {
        $license = License::factory()->create(['seats_total' => 3]);
        $this->service->assignToUser($license, User::factory()->create());
        $this->service->assignToAsset($license, Asset::factory()->create());

        $this->expectException(LicenseAssignmentException::class);
        $this->expectExceptionMessage('2 bestehenden Zuweisungen');

        $this->service->update($license, ['seats_total' => 1]);
    }

    public function test_unassigning_releases_a_seat(): void
    {
        $license = License::factory()->create(['seats_total' => 1]);
        $assignment = $this->service->assignToUser($license, User::factory()->create());

        $this->service->unassign($assignment);

        $this->assertEquals(0, $license->seatsUsed());
        $this->assertEquals(1, $license->availableSeats());
    }

    public function test_license_key_is_encrypted_at_rest(): void
    {
        $license = $this->service->create([
            'name' => 'Office Suite',
            'vendor' => 'Example Vendor',
            'product' => 'Office Pro',
            'license_key' => 'secret-license-key',
            'seats_total' => 5,
        ]);

        $rawKey = DB::table('licenses')->where('id', $license->id)->value('license_key');

        $this->assertNotSame('secret-license-key', $rawKey);
        $this->assertSame('secret-license-key', $license->fresh()->license_key);
    }

    public function test_license_requires_at_least_one_seat(): void
    {
        $this->expectException(LicenseAssignmentException::class);
        $this->expectExceptionMessage('mindestens einen Sitzplatz');

        $this->service->create([
            'name' => 'Invalid License',
            'vendor' => 'Example Vendor',
            'product' => 'Example Product',
            'seats_total' => 0,
        ]);
    }
}
