<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Instance;
use App\Services\EvolutionApiService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    protected $evolutionApiService;

    public function __construct(EvolutionApiService $evolutionApiService)
    {
        $this->evolutionApiService = $evolutionApiService;
    }

    public function created(User $user): void
    {
        //Log::info('UserObserver: User created event triggered', ['user_id' => $user->id]);

        try {
            $instance = Instance::create([
                'user_id' => $user->id,
                'name' => 'instance_' . $user->id,
                'phone_number' => '',
                'status' => 'connecting',
            ]);

            // Log::info('UserObserver: Instance created in database', [
            //     'instance_id' => $instance->id,
            //     'user_id' => $user->id,
            // ]);

            $created = $this->evolutionApiService->createInstance($instance);

            if ($created) {
                $connected = $this->evolutionApiService->connectInstance($instance);
                if ($connected) {
                    // Log::info('UserObserver: Instance connected and QR code received', [
                    //     'instance_id' => $instance->id,
                    //     'user_id' => $user->id,
                    // ]);
                } else {
                    // Log::warning('UserObserver: Failed to connect instance', [
                    //     'instance_id' => $instance->id,
                    //     'user_id' => $user->id,
                    // ]);
                }
            } else {
                // Log::warning('UserObserver: Failed to create instance in Evolution API', [
                //     'instance_id' => $instance->id,
                //     'user_id' => $user->id,
                // ]);
            }
        } catch (\Exception $e) {
            Log::error('UserObserver: Exception while creating and connecting instance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}