<?php

namespace App\Providers;

use App\Contracts\ChatServiceInterface;
use App\Services\AnthropicChatService;
use App\Services\GeminiChatService;
use App\Services\GroqChatService;
use App\Services\OllamaChatService;
use App\Services\OpenAIChatService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChatServiceInterface::class, function ($app) {
            $provider = config('services.chat.provider', 'groq');

            return match ($provider) {
                'openai' => new OpenAIChatService,
                'anthropic' => new AnthropicChatService,
                'gemini' => new GeminiChatService,
                'ollama' => new OllamaChatService,
                'groq' => new GroqChatService,
                default => new GroqChatService,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('chat-send', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('chat-new', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
