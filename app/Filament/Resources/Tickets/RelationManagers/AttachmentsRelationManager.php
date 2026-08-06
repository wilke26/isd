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

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public function form(Schema $schema): Schema
    {
        // Wird nur für Filaments interne Formular-Validierung benötigt,
        // der tatsächliche Upload läuft über die eigene Action unten
        // (siehe headerActions), nicht über Filaments Standard-CreateAction
        // — damit TicketService::addAttachment() genutzt wird, statt
        // roher Eloquent-create()-Logik.
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
                            // Nur temporär während des Uploads — der
                            // eigentliche, dauerhafte Speicherort wird von
                            // TicketService::addAttachment() bestimmt, das
                            // die Datei erneut (unter einem zufälligen
                            // Namen, nicht dem Original) ablegt.
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

                        // getOwnerRecord() ist generisch mit der Basisklasse
                        // Model typisiert; dieser RelationManager ist aber
                        // ausschließlich an die Ticket-Resource gebunden.
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
                    ->action(fn (TicketAttachment $record) => Storage::disk('local')
                        ->download($record->path, $record->filename)),
                DeleteAction::make()
                    ->visible(fn (TicketAttachment $record) => auth()->user()->can('deleteAttachment', $record))
                    ->action(function (TicketAttachment $record): void {
                        app(TicketService::class)->deleteAttachment($record);
                    }),
            ]);
    }
}
