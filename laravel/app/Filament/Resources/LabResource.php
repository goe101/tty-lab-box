<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabResource\Pages;
use App\Models\Lab;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class LabResource extends Resource
{
    protected static ?string $model = Lab::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')->required(),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                TextInput::make('duration')->numeric()->default(60)->required(),
                Toggle::make('published')->default(false),
                Textarea::make('description')->columnSpanFull(),
                Textarea::make('grader_script')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('duration')->sortable(),
                IconColumn::make('published')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([\Filament\Tables\Actions\EditAction::make()])
            ->bulkActions([\Filament\Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabs::route('/'),
            'create' => Pages\CreateLab::route('/create'),
            'edit' => Pages\EditLab::route('/{record}/edit'),
        ];
    }
}
