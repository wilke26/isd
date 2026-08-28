<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListKbArticlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:kb_categories,id'],
            'status' => ['sometimes', new Enum(ArticleStatus::class)],
            'tag' => ['sometimes', 'string', 'max:100'],
            'search' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
