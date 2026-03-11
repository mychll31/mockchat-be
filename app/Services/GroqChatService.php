<?php

namespace App\Services;

use App\Contracts\ChatServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chat service using Groq's free API with Llama 3.1.
 * Get a free API key at https://console.groq.com
 */
class GroqChatService implements ChatServiceInterface
{
    protected string $apiKey;

    protected string $model = 'llama-3.1-8b-instant';

    protected string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct(?string $apiKey = null)
    {
        $key = $apiKey ?? config('services.groq.key') ?? '';
        $this->apiKey = is_string($key) ? $key : '';
    }

    public function getCustomerOpener(string $typeKey, string $customerName): string
    {
        $personality = $this->getPersonalityPrompt($typeKey);
        $system = "You are a Filipino customer in a chat with a sales/support agent. Your role: {$personality}. Reply ONLY with the customer's first message in Tagalog (1-2 sentences). No quotes or labels.";
        $response = $this->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => 'Start the conversation as the customer. Send only the opening message.'],
        ]);
        return $this->trimResponse($response);
    }

    public function getCustomerReply(string $typeKey, string $customerName, array $messageHistory, string $agentMessage): string
    {
        $personality = $this->getPersonalityPrompt($typeKey);
        $stageInstructions = "The agent should follow this flow: 1) Greeting/Rapport 2) Probing 3) Empathize 4) Solution 5) Value 6) Offer/Close 7) Confirmation. Respond as the customer would naturally at this point in the conversation.";
        $system = "You are a Filipino customer. Your name: {$customerName}. {$personality}. {$stageInstructions}. Reply ONLY in Tagalog, 1-3 short sentences. Stay in character. No quotes or 'Customer:' prefix.";
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($messageHistory as $m) {
            $role = $m['sender'] === 'agent' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $m['body']];
        }
        $messages[] = ['role' => 'user', 'content' => $agentMessage];
        $response = $this->chat($messages);
        return $this->trimResponse($response);
    }

    protected function getPersonalityPrompt(string $typeKey): string
    {
        return match ($typeKey) {
            'normal_buyer' => 'Friendly customer interested in buying (e.g. headphones, laptop, phone). Polite, asks about features and price.',
            'irate_returner' => 'Angry customer who received a defective product and wants a return/refund. Impatient but can calm down if the agent helps.',
            'irate_annoyed' => 'Very annoyed customer who feels the agent is wasting their time. Rude, sarcastic, has been transferred multiple times.',
            default => 'Polite Filipino customer.',
        };
    }

    protected function chat(array $messages): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Groq API key is missing. Set GROQ_API_KEY in .env (free at https://console.groq.com).');
            return $this->fallbackResponse();
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => 150,
                    'temperature' => 0.8,
                ]);

            if (! $response->successful()) {
                Log::warning('Groq API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->fallbackResponse();
            }

            $content = $response->json('choices.0.message.content');
            return is_string($content) ? $content : $this->fallbackResponse();
        } catch (\Throwable $e) {
            Log::error('Groq request failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return $this->fallbackResponse();
        }
    }

    protected function trimResponse(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^["\']|["\']$/u', '', $s);
        $s = preg_replace('/^(Customer|Agent):\s*/iu', '', $s);
        return trim($s) ?: 'Salamat. Ano pa ang pwedeng gawin?';
    }

    protected function fallbackResponse(): string
    {
        return 'Salamat sa mensahe mo. Puwede mo bang dagdagan ang detalye?';
    }
}
