<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TicketPriority;
use App\Models\AssetAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

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

    /**
     * A requester may only link an asset that is currently assigned to them.
     * Staff may create tickets for any asset through the same endpoint.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            $assetId = $this->integer('asset_id');

            if (
                ! $assetId
                || ! $user
                || $user->hasRole('admin')
                || $user->hasRole('agent')
                || $validator->errors()->has('asset_id')
            ) {
                return;
            }

            $isAssigned = AssetAssignment::query()
                ->where('asset_id', $assetId)
                ->where('user_id', $user->id)
                ->whereNull('returned_at')
                ->exists();

            if (! $isAssigned) {
                $validator->errors()->add(
                    'asset_id',
                    'Das ausgewählte Asset ist dir nicht aktuell zugewiesen.',
                );
            }
        }];
    }
}
