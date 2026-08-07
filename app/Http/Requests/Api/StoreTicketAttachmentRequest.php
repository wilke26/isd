<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization runs explicitly in the controller via the policy
        // (addAttachment), not here — consistent with the rest of the project.
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:jpg,jpeg,png,gif,pdf,txt,log,doc,docx,xls,xlsx,zip',
            ],
        ];
    }
}
