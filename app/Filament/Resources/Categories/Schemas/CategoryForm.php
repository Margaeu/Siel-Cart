<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Rules\CategoryNameMakesASlug;
use App\Support\OptimizedImageStorage;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Text fields and the image sit side by side in one section
                // rather than two stacked sections, so the whole form fits
                // with far less scrolling. Below the lg breakpoint the two
                // columns collapse and the image drops under the text fields.
                Section::make('Category information')
                    ->description('Name the collection and add the image customers will use to recognize it.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Group::make([
                            // Duplicates are caught by RejectsDuplicateCategory
                            // on the Create and Edit pages, which also converts
                            // the database unique violation two simultaneous
                            // submissions can still cause.
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->rule(new CategoryNameMakesASlug),
                            // No slug field, for the same reason ProductForm has
                            // none: it is generated from the name and never follows
                            // a rename, so on edit it was a read-only box of jargon.
                            // The View page (CategoryInfolist) shows it for anyone
                            // who needs the category's public address.
                            Grid::make([
                                'default' => 1,
                                'sm' => 2,
                            ])->schema([
                                // Same rule as Category's updating hook, checked here
                                // so the message shows under the toggle instead of
                                // surfacing as an error the form cannot place.
                                Toggle::make('is_active')
                                    ->required()
                                    ->rule(static fn (?Category $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                        if ($record && ! $value && ($reason = $record->deactivationBlockedReason())) {
                                            $fail($reason);
                                        }
                                    }),
                                TextInput::make('sort_order')
                                    ->helperText('Categories with lower sort-order numbers appear first.')
                                    ->required()
                                    ->numeric()
                                    ->type('text')
                                    ->default(0),
                            ]),
                        ]),

                        FileUpload::make('image')
                            ->label('Image (Optional)')
                            ->disk('r2')
                            ->directory('categories')
                            ->imageEditor()
                            ->preserveFilenames()
                            ->downloadable()
                            ->openable()
                            ->image()
                            ->maxSize(10240)
                            ->imagePreviewHeight('220')
                            ->extraAttributes(['class' => 'clsu-image-upload'])
                            ->helperText('Maximum file size: 10 MB.')
                            ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                                return OptimizedImageStorage::store(
                                    $file,
                                    $component->getDiskName(),
                                    $component->getDirectory(),
                                    $component->getUploadedFileNameForStorage($file),
                                    maxWidth: 800,
                                );
                            }),

                    ]),

            ]);
    }
}
