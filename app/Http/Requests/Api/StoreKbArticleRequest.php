<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization runs explicitly in the controller via the policy
        // (create resp. update — this class is used for both), not here —
        // consistent with the rest of the project.
        return true;
    }

    public function rules(): array
    {
        $isStaff = $this->user()?->hasRole('admin') || $this->user()?->hasRole('agent');

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'status' => $isStaff
                ? ['sometimes', new Enum(ArticleStatus::class)]
                : ['prohibited'],
            'category_id' => ['nullable', 'exists:kb_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ];
    }
}
