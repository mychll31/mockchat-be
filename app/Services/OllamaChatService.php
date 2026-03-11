<?php

namespace App\Services;

use App\Contracts\ChatServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chat service using local Ollama (Llama 3 / 3.1).
 * Run: ollama run llama3.1 (or llama3) then set CHAT_PROVIDER=ollama.
 */
class OllamaChatService implements ChatServiceInterface
{
    protected string $baseUrl;

    protected string $model = 'llama3.1';

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.ollama.url', 'http://localhost:11434'), '/');
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
        try {
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.8,
                        'num_predict' => 150,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Ollama API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->fallbackResponse();
            }

            $content = $response->json('message.content');
            return is_string($content) ? $content : $this->fallbackResponse();
        } catch (\Throwable $e) {
            Log::error('Ollama request failed', [
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
