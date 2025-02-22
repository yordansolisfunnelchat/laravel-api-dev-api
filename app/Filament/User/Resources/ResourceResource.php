<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\ResourceResource\Pages;
use App\Filament\User\Resources\ResourceResource\RelationManagers;
use App\Models\Resource as ResourceModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Product;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Colors\Color;
use Filament\Notifications\Notification;

class ResourceResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
    
    protected static ?string $model = ResourceModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationLabel = 'Recursos (deshabilitado)';

    protected static ?string $navigationGroup = 'Por eliminar';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Alert::make()
                            ->danger()
                            ->persistent()
                            ->content('IMPORTANTE: Los recursos subidos en esta sección no serán utilizados por el asistente en sus respuestas. Por favor, suba los archivos en el apartado de "Adjuntos" para que el asistente los pueda utilizar. Esta sección será eliminada el 17 de Marzo de 2025.')
                            ->icon('heroicon-o-exclamation-triangle'),
                        
                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => auth()->id()),
                        Forms\Components\Select::make('agent_id')
                            ->relationship('agent', 'name', function (Builder $query) {
                                return $query->whereHas('instance', function (Builder $instanceQuery) {
                                    $instanceQuery->where('user_id', auth()->id());
                                });
                            })
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
                    Forms\Components\Section::make()
                        ->schema([
                            Forms\Components\Select::make('products')
                                ->multiple()
                                ->relationship('products', 'name', function (Builder $query) {
                                    return $query->where('user_id', auth()->id());
                                })
                                ->required()
                                ->preload()
                                ->searchable()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $userProductIds = Product::where('user_id', auth()->id())->pluck('id')->toArray();
                                    $selectedProducts = $get('products') ?: [];
                                    $set('select_all_products', count($selectedProducts) === count($userProductIds));
                                })
                                ->dehydrated(fn (Get $get) => $get('products') !== null),
                            
                            Forms\Components\Checkbox::make('select_all_products')
                                ->label('Seleccionar todos los productos')
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    if ($state) {
                                        $set('products', Product::where('user_id', auth()->id())->pluck('id')->toArray());
                                    } else {
                                        $set('products', []);
                                    }
                                }),
                        ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\Action::make('warning')
                    ->label('⚠️ Aviso Importante')
                    ->color(Color::Red)
                    ->tooltip('Información sobre la deprecación de esta sección')
                    ->action(function () {
                        Notification::make()
                            ->danger()
                            ->title('Sección en Deprecación')
                            ->body('Los recursos subidos en esta sección no serán utilizados por el asistente en sus respuestas. Por favor, suba los archivos en el apartado de "Adjuntos". Esta sección será eliminada el 17 de Marzo de 2025.')
                            ->persistent()
                            ->send();
                    })
            ])
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
                Tables\Actions\DeleteAction::make(),
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResources::route('/'),
        ];
    }
}