<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Rules\ImageWithinPixelBudget;
use App\Support\OptimizedImageStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Banner Image')
                    ->description('This image is displayed full-width in the homepage carousel.')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Banner Image or Clip')
                            ->disk('r2')
                            ->visibility('public')
                            ->directory('banners')
                            // Named explicitly instead of ->image(), which is nothing but
                            // acceptedFileTypes(['image/*']) — and that matches
                            // image/svg+xml. An SVG uploaded through it was stored on R2
                            // verbatim (OptimizedImageStorage skips vector/animated
                            // formats, since GD cannot touch vector paths), so a carousel
                            // slide could be arbitrary markup served from the bucket.
                            //
                            // GIF and MP4 are here because the carousel takes short
                            // promotional clips, not just stills. Both are passed through
                            // untouched by OptimizedImageStorage (see its SKIP_EXTENSIONS)
                            // and both are rendered by Banner::MEDIA_* branching in
                            // resources/views/components/storefront/banner-slide.blade.php.
                            // Anything added here needs a matching branch there, or the
                            // slide renders an <img> pointing at a file no browser will
                            // draw.
                            ->acceptedFileTypes([
                                'image/png',
                                'image/jpeg',
                                'image/webp',
                                'image/gif',
                                'video/mp4',
                            ])
                            ->required()
                            // Not the binding limit: config/livewire.php caps every
                            // temporary upload at max:10240 and that rule runs first, so
                            // raising this alone would change nothing. Change both together.
                            ->maxSize(10240)
                            // File size does not bound a decode: an 8000x6000 photo is
                            // ~1.2 MB and wants ~202 MB of bitmap. See the rule.
                            ->rules([new ImageWithinPixelBudget])
                            // Both editor and preview are image-only in Filament; a video
                            // gets a generic file row instead, which is why the aspect-ratio
                            // guidance below matters more for clips than for stills.
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['16:9', '21:9', null])
                            ->orientImagesFromExif(false)
                            ->imagePreviewHeight('250')
                            ->extraAttributes(['class' => 'clsu-image-upload'])
                            ->helperText('PNG, JPG, WebP, GIF or MP4. Recommended: wide landscape 16:9 (e.g. 1920x600). Maximum file size: 10 MB — keep clips to a few seconds and compress before uploading. Clips play muted and on loop.')
                            ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                                return OptimizedImageStorage::store(
                                    $file,
                                    $component->getDiskName(),
                                    $component->getDirectory(),
                                    $component->getUploadedFileNameForStorage($file),
                                    maxWidth: 1920,
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                Section::make('Display')
                    ->description('Control whether this banner is visible and where it appears in the carousel.')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->helperText('Lower numbers appear first in the carousel.')
                            ->required()
                            ->numeric()
                            // `sort_order` is an unsignedInteger column, so a negative
                            // value is not a validation failure but a SQL one: MySQL in
                            // strict mode answers "Out of range value for column
                            // 'sort_order'" (SQLSTATE 22003) and the admin gets a 500
                            // over a typo. sqlite stores -5 happily, so the test suite
                            // would never have caught it either.
                            ->minValue(0)
                            ->type('text')
                            ->default(0),
                    ])->columns(2),
            ]);
    }
}
