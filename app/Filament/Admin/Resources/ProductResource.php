<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Filament\Admin\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\Hidden::make('user_id')
                ->default(fn () => auth()->id()),
            Forms\Components\Section::make('')
                ->columns(1)
                ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre Del Producto')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción Del Producto')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Producto por defecto')
                    ->default(false),
                Forms\Components\TextInput::make('price')
                    ->label('Precio')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('currency')
                    ->label('Moneda')
                    ->options([
                        'USD' => 'Dólar estadounidense',
                        'COP' => 'Peso Colombiano',
                        // Añade aquí más opciones según tus necesidades
                    ])
                    ->required(),
                Forms\Components\FileUpload::make('images')
                    ->label('Imágenes del producto')
                    ->multiple()
                    ->image()
                    ->openable()
                    ->downloadable()
                    ->previewable(true)
                    ->visibility('public')
                    ->directory('product-images'),
                Forms\Components\TextInput::make('external_link')
                    ->label('Link externo')
                    ->url()
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('discounts')
                    ->label('Descuentos')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->required(),
                        Forms\Components\TextInput::make('porcent')
                            ->label('Porcentaje')
                            ->required()
                            ->numeric()
                            ->suffix('%'),
                        Forms\Components\Textarea::make('usage_case')
                            ->label('En qué caso usar')
                            ->maxLength(255),
                    ])
                    ->collapsible()
                    ->createItemButtonLabel('Añadir descuento')
                    ->defaultItems(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
