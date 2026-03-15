<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserLlmSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmSettingsController extends Controller
{
    protected array $availableProviders = [
        'openai' => 'OpenAI (GPT-4o-mini)',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'groq' => 'Groq (Llama 3.1)',
        'ollama' => 'Ollama (Local)',
    ];

    public function providers(): JsonResponse
    {
        $providers = collect($this->availableProviders)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'configurable' => $key !== 'ollama',
        ])->values();

        return response()->json(['providers' => $providers]);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id', 1);

        $settings = UserLlmSetting::where('user_id', $userId)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'provider' => $s->provider,
                'model' => $s->model,
                'is_default' => $s->is_default,
                'has_api_key' => ! empty($s->api_key),
                'created_at' => $s->created_at,
                'updated_at' => $s->updated_at,
            ]);

        return response()->json(['settings' => $settings]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:openai,anthropic,gemini,groq,ollama',
            'api_key' => 'nullable|string|max:500',
            'model' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'user_id' => 'nullable|integer',
        ]);

        $userId = $request->input('user_id', 1);

        // If setting as default, unset other defaults for this user
        if ($request->boolean('is_default')) {
            UserLlmSetting::where('user_id', $userId)
                ->where('provider', '!=', $request->provider)
                ->update(['is_default' => false]);
        }

        $data = [
            'model' => $request->input('model'),
            'is_default' => $request->boolean('is_default'),
        ];

        // Only update api_key if provided (don't clear it on updates without a key)
        if ($request->filled('api_key')) {
            $data['api_key'] = $request->input('api_key');
        }

        $setting = UserLlmSetting::updateOrCreate(
            ['user_id' => $userId, 'provider' => $request->provider],
            $data
        );

        return response()->json([
            'setting' => [
                'id' => $setting->id,
                'provider' => $setting->provider,
                'model' => $setting->model,
                'is_default' => $setting->is_default,
                'has_api_key' => ! empty($setting->api_key),
            ],
        ], 201);
    }

    public function destroy(Request $request, string $provider): JsonResponse
    {
        $userId = (int) $request->query('user_id', 1);

        $deleted = UserLlmSetting::where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Setting not found'], 404);
        }

        return response()->json(['status' => 'deleted']);
    }
}
