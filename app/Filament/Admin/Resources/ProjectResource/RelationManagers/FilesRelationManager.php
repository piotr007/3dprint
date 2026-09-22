<?php

namespace App\Filament\Admin\Resources\ProjectResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\ProjectMedia;
use Illuminate\Http\UploadedFile;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->required()->maxLength(255),
            Select::make('file_type')
                ->required()
                ->options([
                    'stl' => 'stl',
                    'freecad' => 'freecad',
                    'py' => 'py',
                    'other' => 'other',
                ]),
            FileUpload::make('path')
                ->required()
                ->saveUploadedFileUsing(function (UploadedFile $file) {
                    return ProjectMedia::storeFile($file);
                })
                ->maxSize(20480),
            TextInput::make('sort_order')->numeric()->default(0),
        ])->columns(2);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['path'] instanceof UploadedFile) {
            $data['path'] = ProjectMedia::storeFile($data['path']);
        }

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table->headerActions([
            CreateAction::make(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ])->columns([
            Tables\Columns\TextColumn::make('label')->searchable(),
            Tables\Columns\TextColumn::make('file_type'),
            Tables\Columns\TextColumn::make('sort_order')->sortable(),
        ])->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
