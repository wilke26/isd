<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'assignee_id'  => null,
            'asset_id'     => null,
            'category_id'  => TicketCategory::factory(),
            'title'        => fake()->sentence(6),
            'description'  => fake()->paragraph(3),
            'status'       => TicketStatus::Open,
            'priority'     => TicketPriority::Medium,
            'due_at'       => fake()->dateTimeBetween('now', '+2 weeks'),
            'resolved_at'  => null,
            'closed_at'    => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => TicketStatus::Open]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TicketStatus::InProgress]);
    }

    public function resolved(): static
    {
        return $this->state([
            'status'      => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function critical(): static
    {
        return $this->state(['priority' => TicketPriority::Critical]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assignee_id' => $user->id]);
    }
}
