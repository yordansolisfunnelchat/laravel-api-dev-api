<?php

namespace App\Filament\User\Resources;

use App\Filament\User\Resources\AgentResource\Pages;
use App\Filament\User\Resources\AgentResource\RelationManagers;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Instance;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;

class AgentResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
    
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Agentes';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                ->default(fn () => auth()->id()),
                Forms\Components\Hidden::make('instance_id')
                ->default(function () {
                    $userId = auth()->id();
                    $instance = Instance::where('user_id', $userId)->first();
                    return $instance ? $instance->id : null;
                })
                ->afterStateHydrated(function ($component, $state) {
                    if (!$state) {
                        $userId = auth()->id();
                        $instance = Instance::where('user_id', $userId)->first();
                        $component->state($instance ? $instance->id : null);
                    }
                })
                ->required()
                ->exists('instances', 'id'),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre Del Agente')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('custom_instructions')
                    ->label('Instrucciones Personalizadas')
                    ->helperText('Instrucciones personalizadas para el agente, extiendete todo lo que sea necesiario.')
                    ->columnSpanFull()
                    ->required()
                    ->rows(10),
                Forms\Components\Select::make('activation_mode')
                    ->label('¿Cuando se activara el agente?')
                    ->options([
                        'always' => 'Siempre (cualquier mensaje)',
                        'keywords' => 'Palabras Claves',
                    ])
                    ->reactive()
                    ->required(),
                Forms\Components\TagsInput::make('keywords')
                    ->label('Palabras Claves')
                    ->helperText('Escribe la palabra y enter.')
                    ->reactive()
                    ->visible(fn (Forms\Get $get) => $get('activation_mode') === 'keywords'),
                Forms\Components\Textarea::make('pause_condition')
                    ->label('¿En que momento pausar la conversación?')
                    ->helperText('Por ejemplo: Cuando el cliente pida el precio.')
                    ->required(fn (Forms\Get $get) => $get('pause_enabled'))
                    ->visible(fn (Forms\Get $get) => $get('pause_enabled'))
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('has_waiting_time')
                    ->label('Tiempo de espera')
                    ->helperText('Si está activado, el agente esperará antes de responder y saldra en "Escribiendo..."')
                    ->default(true),
                Forms\Components\Toggle::make('status')
                    ->label('Estado Del Agente')
                    ->default(false),
                Forms\Components\Toggle::make('use_whatsapp_history')
                    ->label('Usar historial de WhatsApp')
                    ->helperText('Si está activado, el agente usará el historial de conversaciones de WhatsApp para tener más contexto')
                    ->default(false),
                    Section::make('Configuración Avanzada')
                    ->description('Configuración avanzada del comportamiento de la IA')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Fieldset::make('Parámetros de la IA')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('ai_temperature')
                                    ->label('Temperatura')
                                    ->helperText('Controla la creatividad de las respuestas (0.1 a 1.0)')
                                    ->default(0.7)
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(0.1)
                                    ->maxValue(1.0)
                                    ->required()
                                    ->rules(['regex:/^[0-9](\.[0-9])?$/']),
                
                                Forms\Components\TextInput::make('ai_presence_penalty')
                                    ->label('Penalización de Presencia')
                                    ->helperText('Penaliza la repetición de temas (0.1 a 1.0)')
                                    ->default(0.7)
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(0.1)
                                    ->maxValue(1.0)
                                    ->required()
                                    ->rules(['regex:/^[0-9](\.[0-9])?$/']),
                
                                Forms\Components\TextInput::make('ai_frequency_penalty')
                                    ->label('Penalización de Frecuencia')
                                    ->helperText('Penaliza la repetición de palabras (0.1 a 1.0)')
                                    ->default(0.7)
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(0.1)
                                    ->maxValue(1.0)
                                    ->required()
                                    ->rules(['regex:/^[0-9](\.[0-9])?$/']),
                                    
                                Forms\Components\Toggle::make('pause_enabled')
                                    ->label('Habilitar Pausa Automática')
                                    ->helperText('Si está activado, el agente pausará la conversación según las condiciones especificadas')
                                    ->live()
                                    ->default(true),
                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre Del Agente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('custom_instructions')
                    ->label('Instrucciones Personalizadas')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\BooleanColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable(),
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
            RelationManagers\HttpFunctionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}