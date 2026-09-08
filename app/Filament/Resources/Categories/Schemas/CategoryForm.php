<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category information')
                    ->description('Name and describe this storefront collection.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('slug')
                            ->unique(ignoreRecord: true)
                            ->readOnly()
                            ->visibleOn('edit'),
                        Textarea::make('description')
                            ->rows(3)
                            ->default(null)
                            ->columnSpanFull(),
                    ]),

                Section::make('Category image')
                    ->description('Upload the image customers will use to recognize this category.')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image')
                            ->label('Image')
                            ->disk('r2')
                            ->directory('categories')
                            ->imageEditor()
                            ->preserveFilenames()
                            ->downloadable()
                            ->openable()
                            ->image()
                            ->imagePreviewHeight('220')
                            ->extraAttributes(['class' => 'clsu-image-upload'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Display settings')
                    ->description('Choose whether this category is visible and set its storefront order.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->required(),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),

            ]);
    }
}
