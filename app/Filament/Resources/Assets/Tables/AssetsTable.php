<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use App\Models\User;
use App\Services\AssetService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_tag')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('parent.name')
                    ->label('Parent Asset')
                    ->placeholder('-'),
                TextColumn::make('currentAssignment.user.name')
                    ->label('Assigned To')
                    ->placeholder('-'),
                TextColumn::make('serial_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('manufacturer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchased_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warranty_until')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Eigene Aktionen statt Formularfelder — leiten auf
                // AssetService::assign()/unassign() um, damit die Sperre
                // gegen Parallelzugriffe und das automatische Beenden der
                // Vorzuweisung auch im Panel greifen, nicht nur über die API.
                Action::make('assign')
                    ->label('Zuweisen')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('gray')
                    ->visible(fn (Asset $record) => auth()->user()->can('assign', $record))
                    ->schema([
                        Select::make('user_id')
                            ->label('Benutzer')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        $user = User::findOrFail($data['user_id']);
                        app(AssetService::class)->assign($record, $user);

                        Notification::make()
                            ->success()
                            ->title("Asset {$record->asset_tag} wurde {$user->name} zugewiesen.")
                            ->send();
                    }),
                Action::make('unassign')
                    ->label('Freigeben')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Asset $record) => auth()->user()->can('assign', $record) && $record->currentAssignment !== null)
                    ->action(function (Asset $record): void {
                        app(AssetService::class)->unassign($record);

                        Notification::make()
                            ->success()
                            ->title("Zuweisung für {$record->asset_tag} aufgehoben.")
                            ->send();
                    }),
            ])
            ->toolbarActions([
                // Nur DeleteBulkAction: entspricht der bestehenden, admin-
                // beschränkten API-Fähigkeit (AssetPolicy::delete()).
                // ForceDeleteBulkAction/RestoreBulkAction absichtlich
                // entfernt — die API hatte dafür nie Endpunkte.
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
