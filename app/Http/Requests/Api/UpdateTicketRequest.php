<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization runs explicitly in the controller via the policy
        // (update), not here — consistent with the rest of the project.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', new Enum(TicketStatus::class)],
            'priority' => ['sometimes', new Enum(TicketPriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'category_id' => ['sometimes', 'nullable', 'exists:ticket_categories,id'],
            'asset_id' => ['sometimes', 'nullable', 'exists:assets,id'],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
