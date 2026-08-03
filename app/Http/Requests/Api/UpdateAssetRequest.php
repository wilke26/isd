<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Die Route lautet PATCH /assets/{id} — der Routenparameter heißt "id".
        $assetId = $this->route('id');

        return [
            'asset_tag' => [
                'sometimes',
                'string',
                'max:50',
                // Beim Update das eigene Asset von der Unique-Prüfung ausnehmen,
                // sonst würde das unveränderte eigene Asset-Tag als Duplikat
                // abgelehnt.
                Rule::unique('assets', 'asset_tag')->ignore($assetId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'asset_category_id' => ['sometimes', 'exists:asset_categories,id'],
            'asset_status_id' => ['sometimes', 'exists:asset_statuses,id'],
            'parent_asset_id' => [
                'sometimes',
                'nullable',
                'exists:assets,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($assetId) {
                    if ($value !== null && (int) $value === (int) $assetId) {
                        $fail('Ein Asset kann nicht sein eigenes übergeordnetes Asset sein.');
                    }
                },
            ],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
            'warranty_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:purchased_at'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
