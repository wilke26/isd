<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', new Enum(TicketStatus::class)],
            'priority' => ['sometimes', new Enum(TicketPriority::class)],
            'assignee_id' => ['sometimes', 'integer', 'exists:users,id'],
            'search' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
