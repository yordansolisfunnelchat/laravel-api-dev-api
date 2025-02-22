<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InstanceResource\Pages;
use App\Filament\Admin\Resources\InstanceResource\RelationManagers;
use App\Models\Instance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\EvolutionApiService;
use Filament\Forms\Components\ViewField;

class InstanceResource extends Resource
{

    protected static ?string $model = Instance::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Instancias';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('phone_number')
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        'initializing' => 'Initializing',
                        'qr_ready' => 'QR Ready',
                        'connected' => 'Connected',
                        'disconnected' => 'Disconnected',
                    ])
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre de Instancia en Evolution')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Número de Teléfono')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->searchable()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'qr_ready', 'disconnected' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'connected' => 'Conectado',
                        'qr_ready' => 'Pendiente De Conectar',
                        'disconnected' => 'Desconectado',
                        default => 'Desconocido',
                    })
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('connect_qr')
                    ->label('Conectar QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->modalHeading('Escanea el código QR')
                    ->modalDescription('Usa WhatsApp en tu teléfono para escanear este código QR y conectar tu cuenta.')
                    ->modalContent(fn (Instance $record) => view('qr-modal', ['instance' => $record]))
                    ->visible(fn (Instance $record) => $record->status === 'qr_ready' || $record->status === 'disconnected')
                    ->modalSubmitAction(false)
                    ->modalWidth('md'),
            
                Tables\Actions\Action::make('disconnect')
                ->label('Desconectar Número')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->action(function (Instance $record, EvolutionApiService $evolutionApiService) {
                    $success = $evolutionApiService->disconnectInstance($record);
                    if ($success) {
                        $record->status = 'disconnected';
                        $record->save();
                    }
                })
                ->requiresConfirmation()
                ->visible(fn (Instance $record) => $record->status === 'connected'),
            ])
            ->filters([
                //
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
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
            'index' => Pages\ListInstances::route('/'),
            //'create' => Pages\CreateInstance::route('/create'),
            'edit' => Pages\EditInstance::route('/{record}/edit'),
        ];
    }
}
