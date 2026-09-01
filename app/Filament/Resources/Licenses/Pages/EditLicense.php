<?php

declare(strict_types=1);

namespace App\Filament\Resources\Licenses\Pages;

use App\Exceptions\LicenseAssignmentException;
use App\Filament\Resources\Licenses\LicenseResource;
use App\Models\License;
use App\Services\LicenseService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLicense extends EditRecord
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof License);

        try {
            return app(LicenseService::class)->update($record, $data);
        } catch (LicenseAssignmentException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            $this->halt();

            return $record;
        }
    }
}
