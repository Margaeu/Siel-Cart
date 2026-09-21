<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                    ->description('Name, describe, and add the image customers will use to recognize this storefront collection.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Group::make([
                            TextInput::make('name')
                                ->required(),
                            TextInput::make('slug')
                                ->unique(ignoreRecord: true)
                                ->readOnly()
                                ->visibleOn('edit'),
                            Grid::make([
                                'default' => 1,
                                'sm' => 2,
                            ])->schema([
                                Toggle::make('is_active')
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->helperText('Categories with lower sort-order numbers appear first.')
                                    ->required()
                                    ->numeric()
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
                            ->imagePreviewHeight('220')
                            ->extraAttributes(['class' => 'clsu-image-upload'])
                            ->helperText('Recommended size: Max 2MB.'),

                    ]),

            ]);
    }
}
