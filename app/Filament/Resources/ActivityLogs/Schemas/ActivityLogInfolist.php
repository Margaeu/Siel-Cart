<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use App\Support\ActivityLogPresenter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only detail page for one activity. Values are formatted and masked by
 * ActivityLogPresenter, the same as on the timeline; raw JSON only appears in
 * the collapsed "Other metadata" fallback, and even there credentials are
 * replaced before anything is printed.
 */
class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('summary')
                        ->hiddenLabel()
                        ->view('filament.activity-logs.summary'),
                ]),

            Section::make('Activity')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'xl' => 3,
                ])
                ->schema([
                    TextEntry::make('event')
                        ->label('Event')
                        ->badge()
                        ->formatStateUsing(fn (Activity $record): string => self::present($record)->eventLabel())
                        ->icon(fn (Activity $record): Heroicon => self::present($record)->eventIcon())
                        ->color(fn (Activity $record): string|array => self::present($record)->eventColor())
                        ->placeholder('—'),

                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('—')
                        ->columnSpan([
                            'sm' => 1,
                            'xl' => 2,
                        ]),

                    TextEntry::make('actor')
                        ->label('Performed by')
                        ->state(fn (Activity $record): string => self::present($record)->actorName())
                        ->weight('semibold'),

                    TextEntry::make('actor_email')
                        ->label('Actor email')
                        ->state(fn (Activity $record): ?string => self::present($record)->actorEmail())
                        ->copyable()
                        ->hidden(fn (Activity $record): bool => blank(self::present($record)->actorEmail())),

                    TextEntry::make('attempted_email')
                        ->label('Email attempted')
                        ->state(fn (Activity $record): ?string => self::present($record)->attemptedEmail())
                        ->hidden(fn (Activity $record): bool => blank(self::present($record)->attemptedEmail())),

                    TextEntry::make('subject')
                        ->label('Affected record')
                        ->state(fn (Activity $record): ?string => self::present($record)->subjectLabel())
                        ->helperText(fn (Activity $record): ?string => self::present($record)->subjectTitle())
                        ->placeholder('Not tied to a record'),

                    TextEntry::make('log_name')
                        ->label('Log')
                        ->formatStateUsing(fn (?string $state): string => str($state)->headline()->toString())
                        ->placeholder('—'),

                    TextEntry::make('created_at')
                        ->label('Recorded at')
                        ->dateTime('M j, Y g:i:s A')
                        ->timezone(config('app.timezone')),

                    TextEntry::make('recorded_ago')
                        ->label('Recorded')
                        ->state(fn (Activity $record): ?string => $record->created_at?->diffForHumans()),
                ]),

            Section::make('Request details')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->hidden(fn (Activity $record): bool => blank(self::present($record)->ipAddress())
                    && blank(self::present($record)->userAgent())
                    && blank(self::present($record)->batchUuid()))
                ->schema([
                    TextEntry::make('ip_address')
                        ->label('IP address')
                        ->state(fn (Activity $record): ?string => self::present($record)->ipAddress())
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('batch_uuid')
                        ->label('Batch UUID')
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->placeholder('—'),

                    TextEntry::make('user_agent')
                        ->label('User agent')
                        ->state(fn (Activity $record): ?string => self::present($record)->userAgent())
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make(fn (Activity $record): string => match (self::present($record)->changeMode()) {
                'new' => 'Recorded values',
                'old' => 'Values before deletion',
                default => 'Changes',
            })
                ->description(fn (Activity $record): string => match (self::present($record)->changeMode()) {
                    'new' => 'The values the record was saved with.',
                    'old' => 'What the record held when it was deleted.',
                    default => 'Only the fields that changed are listed.',
                })
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columnSpanFull()
                ->hidden(fn (Activity $record): bool => ! self::present($record)->hasChanges())
                ->schema([
                    ViewEntry::make('changes')
                        ->hiddenLabel()
                        ->view('filament.activity-logs.changes-table'),
                ]),

            Section::make('Other metadata')
                ->description('Additional details stored with this activity. Credentials are never shown.')
                ->icon(Heroicon::OutlinedCodeBracket)
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->hidden(fn (Activity $record): bool => self::present($record)->metadata() === [])
                ->schema([
                    ViewEntry::make('metadata')
                        ->hiddenLabel()
                        ->view('filament.activity-logs.metadata'),
                ]),
        ]);
    }

    private static function present(Activity $record): ActivityLogPresenter
    {
        return ActivityLogPresenter::for($record);
    }
}
