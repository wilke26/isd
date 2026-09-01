<?php

declare(strict_types=1);

namespace App\Filament\Resources\Licenses\RelationManagers;

use App\Models\License;
use App\Models\LicenseAssignment;
use App\Services\LicenseService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Zuweisungen';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Benutzer')
                    ->placeholder('-'),
                TextColumn::make('asset.asset_tag')
                    ->label('Asset')
                    ->placeholder('-'),
                TextColumn::make('assigned_at')
                    ->label('Zugewiesen am')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('unassign')
                    ->label('Zuweisung aufheben')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(function (): bool {
                        $license = $this->getOwnerRecord();

                        return $license instanceof License
                            && (auth()->user()?->can('assign', $license) ?? false);
                    })
                    ->action(function (LicenseAssignment $record): void {
                        app(LicenseService::class)->unassign($record);

                        Notification::make()
                            ->success()
                            ->title('Lizenzzuweisung wurde aufgehoben.')
                            ->send();
                    }),
            ]);
    }
}
