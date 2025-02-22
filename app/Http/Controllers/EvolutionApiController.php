<?php

namespace App\Http\Controllers;

use App\Models\Instance;
use App\Services\EvolutionApiService;
use Illuminate\Http\Request;

class EvolutionApiController extends Controller
{
    protected $evolutionApiService;

    public function __construct(EvolutionApiService $evolutionApiService)
    {
        $this->evolutionApiService = $evolutionApiService;
    }

    public function connectInstance($instanceId)
    {
        $instance = Instance::findOrFail($instanceId);
        $success = $this->evolutionApiService->connectInstance($instance);

        if ($success) {
            return response()->json(['message' => 'Instance connected successfully']);
        }

        return response()->json(['message' => 'Failed to connect instance'], 500);
    }
}