<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddKbArticleAddendumRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisierung läuft explizit im Controller über die Policy
        // (addAddendum), nicht hier — konsistent mit dem übrigen Projekt.
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
        ];
    }
}
