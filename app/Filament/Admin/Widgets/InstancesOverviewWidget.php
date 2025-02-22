<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Instance;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use App\Services\EvolutionApiService;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\TableWidget as BaseWidget;

class InstancesOverviewWidget extends BaseWidget
{
    protected static ?string $heading = '';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Instance::query()->where('user_id', auth()->id())
            )
            ->columns([
                TextColumn::make('phone_number')
                    ->label('Número de teléfono'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'qr_ready', 'disconnected', 'refused', 'inactive' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'connected' => 'Conectado',
                        'qr_ready' => 'Pendiente De Conectar',
                        'disconnected', 'refused' => 'Desconectado',
                        'inactive' => 'Cuenta Inactiva',
                        default => 'Desconocido',
                    }),
            ])
            ->actions([
                Action::make('connect_qr')
                    ->label('Conectar QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->modalHeading('Escanea el código QR')
                    ->modalDescription('Usa WhatsApp en tu teléfono para escanear este código QR y conectar tu cuenta.')
                    ->modalContent(fn (Instance $record) => view('qr-modal', ['instance' => $record]))
                    ->visible(fn (Instance $record) => $record->status === 'qr_ready' || $record->status === 'disconnected' || $record->status === 'refused')
                    ->modalSubmitAction(false)
                    ->modalWidth('md'),
                Action::make('disconnect')
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
            ->paginated(false);
    }
}