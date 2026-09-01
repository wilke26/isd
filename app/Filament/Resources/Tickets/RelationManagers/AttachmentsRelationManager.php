<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Attachments tab on the ticket detail page in the Filament panel: upload,
 * download and delete go through TicketService, not through Filament's
 * standard CRUD, so the same logic applies as in the REST API.
 */
class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public function form(Schema $schema): Schema
    {
        // Only needed for Filament's internal form validation, the actual
        // upload goes through the custom action below (see headerActions),
        // not through Filament's standard CreateAction — so that
        // TicketService::addAttachment() is used instead of raw Eloquent
        // create() logic.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Hochgeladen von'),
                TextColumn::make('humanReadableSize')
                    ->label('Größe'),
                TextColumn::make('created_at')
                    ->label('Hochgeladen am')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Datei hochladen')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn () => auth()->user()->can('addAttachment', $this->getOwnerRecord()))
                    ->schema([
                        FileUpload::make('file')
                            ->label('Datei')
                            // Only temporary during the upload — the actual,
                            // permanent storage location is determined by
                            // TicketService::addAttachment(), which stores
                            // the file again (under a random name, not the
                            // original).
                            ->disk('local')
                            ->directory('ticket-attachments-tmp')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $tmpPath = $data['file'];
                        $absoluteTmpPath = Storage::disk('local')->path($tmpPath);

                        $uploadedFile = new UploadedFile(
                            $absoluteTmpPath,
                            basename($tmpPath),
                            Storage::disk('local')->mimeType($tmpPath),
                            null,
                            true,
                        );

                        // getOwnerRecord() is generically typed with the
                        // base Model class; but this RelationManager is
                        // bound exclusively to the Ticket resource.
                        $ticket = $this->getOwnerRecord();
                        assert($ticket instanceof Ticket);

                        app(TicketService::class)->addAttachment(
                            $ticket,
                            auth()->user(),
                            $uploadedFile,
                        );

                        Storage::disk('local')->delete($tmpPath);

                        Notification::make()->success()->title('Datei hochgeladen.')->send();
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Herunterladen')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (TicketAttachment $record) => Storage::disk($record->disk)
                        ->download($record->path, $record->filename)),
                DeleteAction::make()
                    ->visible(fn (TicketAttachment $record) => auth()->user()->can('deleteAttachment', $record))
                    ->action(function (TicketAttachment $record): void {
                        app(TicketService::class)->deleteAttachment($record);
                    }),
            ]);
    }
}
