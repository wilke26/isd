<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_tag' => ['required', 'string', 'max:50', 'unique:assets,asset_tag'],
            'name' => ['required', 'string', 'max:255'],
            'asset_category_id' => ['required', 'exists:asset_categories,id'],
            'asset_status_id' => ['required', 'exists:asset_statuses,id'],
            'parent_asset_id' => ['nullable', 'exists:assets,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:purchased_at'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
