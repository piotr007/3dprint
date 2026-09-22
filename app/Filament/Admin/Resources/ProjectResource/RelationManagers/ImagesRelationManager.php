<?php

namespace App\Filament\Admin\Resources\ProjectResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Group::make([
                Placeholder::make('current_thumb')
                    ->label('Current thumbnail')
                    ->content(fn (?Get $get, ?\App\Models\ProjectImage $record) => $record?->path_thumb ? view('filament.project-image-preview', [
                        'thumb' => '/storage/' . $record->path_thumb . '?v=' . $record->updated_at?->timestamp,
                        'large' => '/storage/' . $record->path_large,
                        'alt' => $record->alt,
                    ]) : 'No image'),
                FileUpload::make('path_large')
                    ->label('Upload new image')
                    ->image()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxSize(5120)
                    ->disk('public')
                    ->directory('projects/images')
                    ->formatStateUsing(fn () => [])
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText('Upload to replace the current image and regenerate large/thumb versions.'),
            ])->columnSpanFull(),
            Toggle::make('is_featured')->label('Featured image'),
            TextInput::make('alt')->maxLength(255),
            TextInput::make('sort_order')->numeric()->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->headerActions([
            CreateAction::make(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ])->columns([
            Tables\Columns\TextColumn::make('alt')->limit(30),
            Tables\Columns\TextColumn::make('sort_order')->sortable(),
        ])->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
