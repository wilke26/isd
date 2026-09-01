<?php

declare(strict_types=1);

namespace App\Filament\Resources\Licenses\Pages;

use App\Filament\Resources\Licenses\LicenseResource;
use App\Services\LicenseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLicense extends CreateRecord
{
    protected static string $resource = LicenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(LicenseService::class)->create($data);
    }
}
