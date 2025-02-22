<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ResourceResource\Pages;
use App\Filament\Admin\Resources\ResourceResource\RelationManagers;
use App\Models\Resource as ResourceModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Forms\Set;


class ResourceResource extends Resource
{
    protected static ?string $model = ResourceModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationLabel = 'Recursos';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            Forms\Components\Hidden::make('user_id')
                ->default(fn () => auth()->id()),
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\Select::make('agent_id')
                    ->relationship('agent', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),
                    Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'link' => 'Link',
                        'image' => 'Image',
                        'video' => 'Video',
                        'document' => 'Document',
                        'instruction' => 'Instruction',
                    ])
                    ->required()
                    ->reactive(),
                Forms\Components\TextInput::make('url')
                    ->url()
                    ->required()
                    ->visible(fn (callable $get) => in_array($get('type'), ['link'])),
                Forms\Components\Toggle::make('auto_sync')
                    ->label('¿Descargar y sincronizar información de este sitio web?')
                    ->visible(fn (callable $get) => $get('type') === 'link')
                    ->reactive()
                    ->disabled(),
                Forms\Components\Select::make('sync_frequency')
                    ->options([
                        'daily' => '1 vez al día',
                        'weekly' => '1 vez a la semana',
                        'monthly' => '1 vez al mes',
                    ])
                    ->visible(fn (callable $get) => $get('type') === 'link' && $get('auto_sync')),
                Forms\Components\FileUpload::make('file')
                    ->directory('resources')
                    ->openable()
                    ->downloadable()
                    ->previewable(true)
                    ->visible(fn (callable $get) => in_array($get('type'), ['image', 'video', 'document'])),
                Forms\Components\Textarea::make('content')
                    ->visible(fn (callable $get) => $get('type') === 'instruction')
                    ->required(fn (callable $get) => $get('type') === 'instruction'),
                ]),
                // Forms\Components\Select::make('products')
                //     ->multiple()
                //     ->searchable()
                //     ->preload()
                //     ->relationship('products', 'name')
                //     ->required(),

                Forms\Components\Section::make()
                    ->schema([
                Forms\Components\Select::make('products')
                ->multiple()
                ->relationship('products', 'name')
                ->required()
                ->preload()
                ->searchable()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    $allProductIds = \App\Models\Product::pluck('id')->toArray();
                    $selectedProducts = $get('products') ?: [];
                    $set('select_all_products', count($selectedProducts) === count($allProductIds));
                })
                ->dehydrated(fn (Get $get) => $get('products') !== null),
                
                Forms\Components\Checkbox::make('select_all_products')
                ->label('Seleccionar todos los productos')
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                    if ($state) {
                        $set('products', \App\Models\Product::pluck('id')->toArray());
                    } else {
                        $set('products', []);
                    }
                }),
                ]),
            ])
            ->columns(1);
            //organizar en grid
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('type'),
            Tables\Columns\TextColumn::make('sync_status'),
            Tables\Columns\TextColumn::make('last_synced_at')
                ->dateTime(),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime(),
        ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResources::route('/'),
            'create' => Pages\CreateResource::route('/create'),
            'edit' => Pages\EditResource::route('/{record}/edit'),
        ];
    }
}
