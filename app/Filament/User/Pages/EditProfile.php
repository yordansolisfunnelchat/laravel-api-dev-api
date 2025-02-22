<?php

namespace App\Filament\User\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                PhoneInput::make('phone')
                    ->label('Número de WhatsApp')
                    ->helperText('El número de WhatsApp para notificaciones de la app.')
                    ->required()
                    ->defaultCountry('CO')
                    ->dehydrateStateUsing(fn ($state) => ltrim($state, '+')),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}