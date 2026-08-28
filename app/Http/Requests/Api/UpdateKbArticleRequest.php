<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization runs explicitly in the controller via KbArticlePolicy.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            // Workflow state is changed only through submit/publish/archive.
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'category_id' => ['sometimes', 'nullable', 'exists:kb_categories,id'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ];
    }
}
