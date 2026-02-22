<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NodeResource\Pages;
use App\Models\Node;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('lab_id')
                    ->relationship('lab', 'title')
                    ->required(),
                TextInput::make('node_name')->required(),
                TextInput::make('image')->default('images:rockylinux/9')->required(),
                TextInput::make('cpu')->numeric()->default(1)->required(),
                TextInput::make('mem_mb')->numeric()->default(1024)->required(),
                TextInput::make('disk_gb')->numeric()->default(10)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lab.title')->sortable()->searchable(),
                TextColumn::make('node_name')->searchable(),
                TextColumn::make('image'),
                TextColumn::make('cpu')->sortable(),
                TextColumn::make('mem_mb')->sortable(),
            ])
            ->filters([])
            ->actions([\Filament\Tables\Actions\EditAction::make()])
            ->bulkActions([\Filament\Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNodes::route('/'),
            'create' => Pages\CreateNode::route('/create'),
            'edit' => Pages\EditNode::route('/{record}/edit'),
        ];
    }
}
