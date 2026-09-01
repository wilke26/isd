<?php

declare(strict_types=1);

namespace App\Filament\Resources\Licenses\Tables;

use App\Exceptions\LicenseAssignmentException;
use App\Models\Asset;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LicensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Bezeichnung')
                    ->searchable(),
                TextColumn::make('vendor')
                    ->label('Hersteller')
                    ->searchable(),
                TextColumn::make('product')
                    ->label('Produkt')
                    ->searchable(),
                TextColumn::make('assignments_count')
                    ->label('Belegt')
                    ->sortable(),
                TextColumn::make('seats_total')
                    ->label('Gesamt')
                    ->sortable(),
                TextColumn::make('available_seats')
                    ->label('Frei')
                    ->state(fn (License $record): int => $record->availableSeats()),
                TextColumn::make('expires_at')
                    ->label('Gültig bis')
                    ->date()
                    ->placeholder('Unbegrenzt')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('assign-user')
                    ->label('Benutzer zuweisen')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (License $record): bool => auth()->user()?->can('assign', $record) ?? false)
                    ->schema([
                        Select::make('user_id')
                            ->label('Benutzer')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (License $record, array $data): void {
                        self::runAssignment(function () use ($record, $data): void {
                            app(LicenseService::class)->assignToUser(
                                $record,
                                User::findOrFail($data['user_id']),
                            );
                        });
                    }),
                Action::make('assign-asset')
                    ->label('Asset zuweisen')
                    ->icon(Heroicon::OutlinedComputerDesktop)
                    ->visible(fn (License $record): bool => auth()->user()?->can('assign', $record) ?? false)
                    ->schema([
                        Select::make('asset_id')
                            ->label('Asset')
                            ->options(fn () => Asset::query()->orderBy('asset_tag')->pluck('asset_tag', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (License $record, array $data): void {
                        self::runAssignment(function () use ($record, $data): void {
                            app(LicenseService::class)->assignToAsset(
                                $record,
                                Asset::findOrFail($data['asset_id']),
                            );
                        });
                    }),
            ]);
    }

    private static function runAssignment(callable $assignment): void
    {
        try {
            $assignment();

            Notification::make()
                ->success()
                ->title('Lizenz wurde zugewiesen.')
                ->send();
        } catch (LicenseAssignmentException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();
        }
    }
}
