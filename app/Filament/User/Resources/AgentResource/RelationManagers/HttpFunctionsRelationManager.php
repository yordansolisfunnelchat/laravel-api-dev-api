<?php

namespace App\Filament\User\Resources\AgentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HttpFunctionsRelationManager extends RelationManager
{
    protected static string $relationship = 'functions';

    protected static ?string $title = 'Funciones HTTP';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
                    ->schema([
                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => auth()->id()),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Función')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(65535)
                            ->helperText('Describe qué hace esta función y cuándo debe ser utilizada'),
                    ]),

                Forms\Components\Section::make('Configuración HTTP')
                    ->schema([
                        Forms\Components\Select::make('http_method')
                            ->label('Método HTTP')
                            ->required()
                            ->options([
                                'GET' => 'GET',
                                'POST' => 'POST',
                                'PUT' => 'PUT',
                                'PATCH' => 'PATCH',
                                'DELETE' => 'DELETE',
                            ]),
                        Forms\Components\TextInput::make('endpoint')
                            ->label('URL del Endpoint')
                            ->required()
                            ->maxLength(255)
                            ->helperText('La URL completa del endpoint a llamar'),
                        Forms\Components\Select::make('authentication_type')
                            ->label('Tipo de Autenticación')
                            ->options([
                                'none' => 'Sin Autenticación',
                                'basic' => 'Basic Auth',
                                'bearer' => 'Bearer Token',
                                'api_key' => 'API Key',
                            ])
                            ->live(),

                        // Campos condicionales para autenticación
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('authentication_config.username')
                                    ->label('Usuario')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('authentication_type') === 'basic'),
                                Forms\Components\TextInput::make('authentication_config.password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('authentication_type') === 'basic'),
                            ]),

                        Forms\Components\TextInput::make('authentication_config.token')
                            ->label('Token')
                            ->required()
                            ->visible(fn (Forms\Get $get): bool => $get('authentication_type') === 'bearer'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('authentication_config.key')
                                    ->label('Nombre del Header')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('authentication_type') === 'api_key'),
                                Forms\Components\TextInput::make('authentication_config.value')
                                    ->label('Valor del API Key')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('authentication_type') === 'api_key'),
                            ]),
                    ]),

                Forms\Components\Section::make('Configuración de Parámetros')
                    ->schema([
                        Forms\Components\Toggle::make('has_query_params')
                            ->label('¿Tiene parámetros de consulta (query)?')
                            ->live(),
                        
                        Forms\Components\Repeater::make('query_parameters')
                            ->label('Parámetros de Consulta')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\Select::make('value_type')
                                    ->label('Tipo de Valor')
                                    ->options([
                                        'manual' => 'Valor Fijo',
                                        'system' => 'Variable del Sistema',
                                        'ai' => 'IA'
                                    ])
                                    ->live()
                                    ->required(),
                                // Campo para valor fijo
                                Forms\Components\TextInput::make('fixed_value')
                                    ->label('Valor Fijo')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('value_type') === 'manual'),
                                // Campo para variables del sistema
                                Forms\Components\Select::make('system_variable')
                                    ->label('Variable del Sistema')
                                    ->options([
                                        'conversation_id' => 'ID de Conversación',
                                        'customer_phone' => 'Número de WhatsApp del Cliente',
                                        'instance_id' => 'ID de Instancia',
                                        'instance_name' => 'Nombre de Instancia',
                                        'user_id' => 'ID de Usuario'
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('value_type') === 'system')
                                    ->required(fn (Forms\Get $get): bool => $get('value_type') === 'system'),
                                // Campo para IA
                                Forms\Components\Textarea::make('description')
                                    ->label('Descripción para la IA')
                                    ->helperText('Describe qué valor debe proporcionar la IA')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('value_type') === 'ai'),
                            ])
                            ->columns(2)
                            ->visible(fn (Forms\Get $get): bool => $get('has_query_params')),

                        Forms\Components\Toggle::make('has_body_params')
                            ->label('¿Tiene parámetros en el cuerpo (body)?')
                            ->live(),
                        
                        Forms\Components\Repeater::make('body_parameters')
                            ->label('Parámetros del Cuerpo')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\Select::make('value_type')
                                    ->label('Tipo de Valor')
                                    ->options([
                                        'manual' => 'Valor Fijo',
                                        'system' => 'Variable del Sistema',
                                        'ai' => 'IA'
                                    ])
                                    ->live()
                                    ->required(),
                                // Campo para valor fijo
                                Forms\Components\TextInput::make('fixed_value')
                                    ->label('Valor Fijo')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('value_type') === 'manual'),
                                // Campo para variables del sistema
                                Forms\Components\Select::make('system_variable')
                                    ->label('Variable del Sistema')
                                    ->options([
                                        'conversation_id' => 'ID de Conversación',
                                        'customer_phone' => 'Número de WhatsApp del Cliente',
                                        'instance_id' => 'ID de Instancia',
                                        'instance_name' => 'Nombre de Instancia',
                                        'user_id' => 'ID de Usuario'
                                    ])
                                    ->visible(fn (Forms\Get $get): bool => $get('value_type') === 'system')
                                    ->required(fn (Forms\Get $get): bool => $get('value_type') === 'system'),
                                // Campo para IA
                                Forms\Components\Textarea::make('description')
                                    ->label('Descripción para la IA')
                                    ->helperText('Describe qué valor debe proporcionar la IA')
                                    ->required()
                                    ->visible(fn (Forms\Get $get): bool => $get('value_type') === 'ai'),
                            ])
                            ->columns(2)
                            ->visible(fn (Forms\Get $get): bool => $get('has_body_params')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('http_method')
                    ->label('Método')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GET' => 'success',
                        'POST' => 'warning',
                        'PUT' => 'info',
                        'PATCH' => 'info',
                        'DELETE' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('endpoint')
                    ->label('Endpoint')
                    ->limit(30),
                Tables\Columns\IconColumn::make('has_query_params')
                    ->label('Query Params')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_body_params')
                    ->label('Body Params')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar Función HTTP')
                    ->modalWidth('4xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth('4xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}