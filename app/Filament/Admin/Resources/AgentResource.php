<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AgentResource\Pages;
use App\Filament\Admin\Resources\AgentResource\RelationManagers;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class AgentResource extends Resource
{
    
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
                Forms\Components\Select::make('instance_id')
                    ->relationship('instance', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre Del Agente')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('custom_instructions')
                    ->label('Instrucciones Personalizadas')
                    ->helperText('Instrucciones personalizadas para el agente.')
                    ->columnSpanFull()
                    ->required()
                    ->rows(10),
                Forms\Components\Textarea::make('pause_condition')
                    ->label('Condición de Pausa')
                    ->helperText('Condición que activará la pausa del agente.')
                    ->columnSpanFull()
                    ->rows(3),
                Forms\Components\Toggle::make('has_waiting_time')
                    ->label('Tiene tiempo de espera')
                    ->default(true),
                Forms\Components\Toggle::make('status')
                    ->label('Estado Del Agente')
                    ->default(false),
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
                    ->limit(50)
                    ->tooltip(function (Model $record): string {
                        return $record->custom_instructions;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('pause_condition')
                    ->label('Condición de Pausa')
                    ->limit(50)
                    ->tooltip(function (Model $record): string {
                        return $record->pause_condition ?? 'Sin condición';
                    }),
                Tables\Columns\BooleanColumn::make('status')
                    ->label('Estado')
                    ->sortable(),
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
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}