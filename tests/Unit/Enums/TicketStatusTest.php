<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public function test_open_can_transition_to_in_progress_or_resolved(): void
    {
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::InProgress));
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Resolved));
    }

    public function test_open_cannot_transition_directly_to_closed(): void
    {
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::Closed));
    }

    public function test_open_cannot_transition_directly_to_waiting_for_requester(): void
    {
        $this->assertFalse(TicketStatus::Open->canTransitionTo(TicketStatus::WaitingForRequester));
    }

    public function test_in_progress_can_transition_to_waiting_or_resolved(): void
    {
        $this->assertTrue(TicketStatus::InProgress->canTransitionTo(TicketStatus::WaitingForRequester));
        $this->assertTrue(TicketStatus::InProgress->canTransitionTo(TicketStatus::Resolved));
    }

    public function test_waiting_for_requester_can_transition_to_in_progress_or_resolved(): void
    {
        $this->assertTrue(TicketStatus::WaitingForRequester->canTransitionTo(TicketStatus::InProgress));
        $this->assertTrue(TicketStatus::WaitingForRequester->canTransitionTo(TicketStatus::Resolved));
    }

    public function test_waiting_for_requester_cannot_transition_to_open(): void
    {
        $this->assertFalse(TicketStatus::WaitingForRequester->canTransitionTo(TicketStatus::Open));
    }

    public function test_resolved_can_transition_to_in_progress_or_closed(): void
    {
        $this->assertTrue(TicketStatus::Resolved->canTransitionTo(TicketStatus::InProgress));
        $this->assertTrue(TicketStatus::Resolved->canTransitionTo(TicketStatus::Closed));
    }

    public function test_resolved_cannot_transition_to_open(): void
    {
        $this->assertFalse(TicketStatus::Resolved->canTransitionTo(TicketStatus::Open));
    }

    public function test_closed_is_final(): void
    {
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::Open));
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::InProgress));
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::WaitingForRequester));
        $this->assertFalse(TicketStatus::Closed->canTransitionTo(TicketStatus::Resolved));
    }

    public function test_transition_to_same_status_is_a_noop_not_an_error(): void
    {
        $this->assertTrue(TicketStatus::Open->canTransitionTo(TicketStatus::Open));
        $this->assertTrue(TicketStatus::Closed->canTransitionTo(TicketStatus::Closed));
    }
}
