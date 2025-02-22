<?php

namespace App\Http\Controllers;

// use App\Models\TestInstance;
use App\Models\Instance;
use Illuminate\Http\Request;

class TestInstanceController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $validatedData['user_id'] = auth()->id();

        // $instance = TestInstance::create($validatedData);
        $instance = Instance::create($validatedData);


        return response()->json([
            'status' => 'success',
            'data' => $instance,
        ], 201);
    }

    public function index()
    {
        $instances = Instance::where('user_id', auth()->id())->get();

        return response()->json([
            'status' => 'success',
            'data' => $instances,
        ]);
    }
}
