<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StepResource\Pages;
use App\Models\Step;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;

class StepResource extends Resource
{
    protected static ?string $model = Step::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('lab_id')
                    ->relationship('lab', 'title')
                    ->required(),
                TextInput::make('order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('title')->required(),
                Textarea::make('markdown')->columnSpanFull()->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lab.title')->sortable()->searchable(),
                TextColumn::make('order')->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('lab_id', 'asc')
            ->filters([])
            ->actions([\Filament\Tables\Actions\EditAction::make()])
            ->bulkActions([\Filament\Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSteps::route('/'),
            'create' => Pages\CreateStep::route('/create'),
            'edit' => Pages\EditStep::route('/{record}/edit'),
        ];
    }
}
