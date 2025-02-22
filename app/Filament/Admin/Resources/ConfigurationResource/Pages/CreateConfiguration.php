<?php

namespace App\Filament\Admin\Resources\ConfigurationResource\Pages;

use App\Filament\Admin\Resources\ConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateConfiguration extends CreateRecord
{
    protected static string $resource = ConfigurationResource::class;
}
