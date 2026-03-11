<?php

namespace App\Providers;

use App\Contracts\ChatServiceInterface;
use App\Services\GroqChatService;
use App\Services\OllamaChatService;
use App\Services\OpenAIChatService;
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
        //
    }
}
