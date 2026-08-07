<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization runs explicitly in the controller via the policy
        // (create), not here — consistent with the rest of the project.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', new Enum(TicketPriority::class)],
            'category_id' => ['nullable', 'exists:ticket_categories,id'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
