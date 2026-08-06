<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorisierung läuft explizit im Controller über die Policy
        // (addAttachment), nicht hier — konsistent mit dem übrigen Projekt.
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
