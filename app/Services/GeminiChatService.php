<?php

namespace App\Services;

use App\Contracts\ChatServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chat service using Google Gemini API.
 * Set GEMINI_API_KEY in .env.
 */
class GeminiChatService implements ChatServiceInterface
{
    protected string $apiKey;

    protected string $model = 'gemini-2.0-flash';

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(?string $apiKey = null)
    {
        $key = $apiKey ?? config('services.gemini.key') ?? '';
        $this->apiKey = is_string($key) ? $key : '';
    }

    public function getCustomerOpener(string $typeKey, string $customerName, ?string $productContext = null): string
    {
        $personality = $this->getPersonalityPrompt($typeKey);
        $productInfo = $productContext ? " The customer is inquiring about: {$productContext}." : '';
        $systemInstruction = "You are a Filipino customer in a chat with a sales/support agent. Your role: {$personality}.{$productInfo} Reply ONLY with the customer's first message in Tagalog (1-2 sentences). No quotes or labels.";
        $response = $this->chat($systemInstruction, [
            ['role' => 'user', 'parts' => [['text' => 'Start the conversation as the customer. Send only the opening message.']]],
        ]);
        return $this->trimResponse($response);
    }

    public function getCustomerReply(string $typeKey, string $customerName, array $messageHistory, string $agentMessage, ?string $productContext = null): string
    {
        $personality = $this->getPersonalityPrompt($typeKey);
        $productInfo = $productContext ? " The conversation is about: {$productContext}." : '';
        $stageInstructions = "The agent should follow this flow: 1) Greeting/Rapport 2) Probing 3) Empathize 4) Solution 5) Value 6) Offer/Close 7) Confirmation. Respond as the customer would naturally at this point in the conversation.";
        $systemInstruction = "You are a Filipino customer. Your name: {$customerName}. {$personality}.{$productInfo} {$stageInstructions}. Reply ONLY in Tagalog, 1-3 short sentences. Stay in character. No quotes or 'Customer:' prefix.";

        $contents = [];
        foreach ($messageHistory as $m) {
            $role = $m['sender'] === 'agent' ? 'user' : 'model';
            $contents[] = ['role' => $role, 'parts' => [['text' => $m['body']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $agentMessage]]];

        // Gemini requires alternating user/model and must start with user
        $contents = $this->normalizeContents($contents);

        $response = $this->chat($systemInstruction, $contents);
        return $this->trimResponse($response);
    }

    protected function getPersonalityPrompt(string $typeKey): string
    {
        return match ($typeKey) {
            'normal_buyer' => 'Friendly customer interested in buying (e.g. headphones, laptop, phone). Polite, asks about features and price.',
            'irate_returner' => 'Angry customer who received a defective product and wants a return/refund. Impatient but can calm down if the agent helps.',
            'irate_annoyed' => 'Very annoyed customer who feels the agent is wasting their time. Rude, sarcastic, has been transferred multiple times.',
            'confused' => 'Confused customer who is not sure what they need. Asks many clarifying questions, easily overwhelmed by options. Needs patient guidance.',
            'impatient' => 'Very impatient customer who wants everything done quickly. Gets frustrated by delays, asks "how long will this take?" frequently.',
            'friendly' => 'Extremely friendly and chatty customer. Shares personal stories, very appreciative, easy to build rapport with but may go off-topic.',
            'skeptical' => 'Skeptical customer who doubts product claims. Asks for proof, reviews, and guarantees. Needs convincing with facts, not sales talk.',
            'demanding' => 'Very demanding customer with high expectations. Expects VIP treatment, complains about minor issues, wants to speak to a manager.',
            'indecisive' => 'Indecisive customer who keeps going back and forth. Changes mind frequently, asks "which one is better?" repeatedly, needs reassurance.',
            'bargain_hunter' => 'Price-conscious customer always looking for discounts. Compares prices with competitors, asks about promos, bundles, and freebies.',
            'loyal' => 'Loyal returning customer who has bought multiple times. Expects recognition and loyalty perks. Friendly but expects premium service.',
            'first_time_buyer' => 'First-time online buyer who is nervous about the process. Asks about payment safety, delivery, returns policy. Needs hand-holding.',
            'silent' => 'Very quiet customer who gives minimal responses like "ok", "sige", "hmm". Hard to engage, agent must ask open-ended questions to draw them out.',
            default => 'Polite Filipino customer.',
        };
    }

    /**
     * Ensure contents alternate between user and model, starting with user.
     */
    protected function normalizeContents(array $contents): array
    {
        if (empty($contents)) {
            return [['role' => 'user', 'parts' => [['text' => 'Hello']]]];
        }

        $normalized = [];
        $lastRole = null;

        foreach ($contents as $content) {
            if ($content['role'] === $lastRole) {
                // Merge consecutive same-role messages
                $lastIndex = count($normalized) - 1;
                $existingText = $normalized[$lastIndex]['parts'][0]['text'] ?? '';
                $newText = $content['parts'][0]['text'] ?? '';
                $normalized[$lastIndex]['parts'] = [['text' => $existingText . "\n" . $newText]];
            } else {
                $normalized[] = $content;
                $lastRole = $content['role'];
            }
        }

        // Ensure first message is from user
        if ($normalized[0]['role'] !== 'user') {
            array_unshift($normalized, ['role' => 'user', 'parts' => [['text' => 'Hello']]]);
        }

        return $normalized;
    }

    protected function chat(string $systemInstruction, array $contents): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Gemini API key is missing. Set GEMINI_API_KEY in .env.');
            throw new \RuntimeException('[Gemini] Walang API key. I-set ang API key sa Settings page.');
        }

        try {
            $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(30)
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => 150,
                        'temperature' => 0.8,
                    ],
                ]);

            if (! $response->successful()) {
                $errorBody = $response->json();
                $errorMsg = $errorBody['error']['message'] ?? $response->body();
                $errorStatus = $errorBody['error']['status'] ?? 'unknown';
                Log::warning('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException("[Gemini] {$errorStatus}: {$errorMsg}");
            }

            $content = $response->json('candidates.0.content.parts.0.text');
            return is_string($content) ? $content : $this->fallbackResponse();
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Gemini request failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new \RuntimeException('[Gemini] Hindi ma-contact ang API: ' . $e->getMessage());
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
