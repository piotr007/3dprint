<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project details')->schema([
                TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('short_description')->required()->rows(3),
                Toggle::make('is_published')->default(false),
                DateTimePicker::make('published_at')->visible(fn (Get $get) => (bool) $get('is_published')),
            ])->columns(2),
            Section::make('Description')->schema([
                Textarea::make('description')->required()->rows(14),
            ])->columnSpanFull(),
            Section::make('Print notes')->schema([
                Textarea::make('print_notes')->rows(10),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->searchable()->sortable(),
            Tables\Columns\IconColumn::make('is_published')->boolean(),
            Tables\Columns\TextColumn::make('published_at')->dateTime(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ProjectResource\RelationManagers\ImagesRelationManager::class,
            ProjectResource\RelationManagers\FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
