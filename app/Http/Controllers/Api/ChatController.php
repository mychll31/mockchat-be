<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Models\Product;
use App\Models\UserLlmSetting;
use App\Contracts\ChatServiceInterface;
use App\Services\AnthropicChatService;
use App\Services\GeminiChatService;
use App\Services\GroqChatService;
use App\Services\OllamaChatService;
use App\Services\OpenAIChatService;
use App\Services\ChatStageDetection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected ChatServiceInterface $chat;

    protected array $customerNames = [
        'normal_buyer' => ['Sarah M.', 'James K.', 'Emily R.', 'David L.', 'Lisa T.', 'Mike P.'],
        'irate_returner' => ['Karen W.', 'Robert S.', 'Patricia H.', 'Mark D.', 'Angela C.', 'Tom B.'],
        'irate_annoyed' => ['Gary N.', 'Brenda F.', 'Steve Q.', 'Diane J.', 'Kevin O.', 'Nancy E.'],
        'confused' => ['Ana G.', 'Rico M.', 'Celia P.', 'Jun B.', 'Mara S.', 'Ben T.'],
        'impatient' => ['Carlos R.', 'Tina L.', 'Eddie V.', 'Rose D.', 'Alex N.', 'Joy H.'],
        'friendly' => ['Maria C.', 'Ramon A.', 'Liza F.', 'Danny O.', 'Grace W.', 'Paolo J.'],
        'skeptical' => ['Andres K.', 'Nina R.', 'Oscar M.', 'Beth S.', 'Ray T.', 'Alma D.'],
        'demanding' => ['Victoria L.', 'Francis G.', 'Cristina H.', 'Marco P.', 'Irene B.', 'Leo V.'],
        'indecisive' => ['Jenny M.', 'Rodel C.', 'Tess A.', 'Noel F.', 'Bea K.', 'Sam Q.'],
        'bargain_hunter' => ['Manny D.', 'Luz R.', 'Tony S.', 'Cora P.', 'Rudy L.', 'Pia M.'],
        'loyal' => ['Elena J.', 'Roberto N.', 'Susie T.', 'Joel A.', 'Aida G.', 'Dante F.'],
        'first_time_buyer' => ['Jasmine B.', 'Gerald H.', 'Cherry V.', 'Dennis K.', 'Mila S.', 'Ian R.'],
        'silent' => ['Pete C.', 'Nora D.', 'Vic M.', 'Faye L.', 'Art P.', 'Gina T.'],
    ];

    public function __construct(ChatServiceInterface $chat)
    {
        $this->chat = $chat;
    }

    public function newConversation(Request $request): JsonResponse
    {
        try {
            $type = CustomerType::inRandomOrder()->first();
            if (!$type) {
                return response()->json(['error' => 'No customer types configured'], 500);
            }

            $names = $this->customerNames[$type->type_key] ?? ['Customer'];
            $customerName = $names[array_rand($names)];

            $productId = $request->input('product_id');
            $productContext = null;
            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $productContext = "{$product->name} - {$product->description} (Price: PHP " . number_format((float) $product->price, 2) . ")";
                }
            }

            $conversation = Conversation::create([
                'user_id' => $request->user()?->id,
                'customer_type_id' => $type->id,
                'product_id' => $productId,
                'customer_name' => $customerName,
                'status' => 'active',
            ]);

            $chatService = $this->resolveChatService($request);
            $opener = $chatService->getCustomerOpener($type->type_key, $customerName, $productContext);

            Message::create([
                'conversation_id' => $conversation->id,
                'sender' => 'customer',
                'body' => $opener,
            ]);

            return response()->json([
                'conversation_id' => $conversation->id,
                'customer_name' => $customerName,
                'customer_type' => $type->label,
                'type_key' => $type->type_key,
                'opener' => $opener,
                'product_id' => $productId,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer',
            'message' => 'required|string|max:2000',
        ]);

        $conversation = Conversation::with(['customerType', 'product'])
            ->where('id', $request->conversation_id)
            ->where('status', 'active')
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found or already closed'], 404);
        }

        $body = trim($request->message);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'body' => $body,
        ]);

        $history = $conversation->messages()->orderBy('created_at')->get()
            ->map(fn ($m) => ['sender' => $m->sender, 'body' => $m->body])
            ->toArray();

        $productContext = null;
        if ($conversation->product) {
            $product = $conversation->product;
            $productContext = "{$product->name} - {$product->description} (Price: PHP " . number_format((float) $product->price, 2) . ")";
        }

        try {
            $chatService = $this->resolveChatService($request);
            $reply = $chatService->getCustomerReply(
                $conversation->customerType->type_key,
                $conversation->customer_name,
                $history,
                $body,
                $productContext
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => $reply,
        ]);

        $agentBodies = $conversation->messages()->where('sender', 'agent')->orderBy('created_at')->pluck('body')->toArray();
        $suggestedStage = ChatStageDetection::fromAgentMessages($agentBodies);

        $autoEnded = false;
        if ($suggestedStage >= 7) {
            $conversation->update(['status' => 'closed']);
            $autoEnded = true;
        }

        return response()->json([
            'agent_message' => $body,
            'customer_response' => $reply,
            'suggested_stage' => $suggestedStage,
            'auto_ended' => $autoEnded,
        ]);
    }

    public function getMessages(Request $request): JsonResponse
    {
        $conversationId = (int) $request->query('conversation_id');
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get(['id', 'sender', 'body', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    public function getConversation(Request $request): JsonResponse
    {
        $conversationId = (int) $request->query('conversation_id');
        $conversation = Conversation::with('customerType')
            ->find($conversationId);

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $agentBodies = $conversation->messages()->where('sender', 'agent')->orderBy('created_at')->pluck('body')->toArray();
        $suggestedStage = ChatStageDetection::fromAgentMessages($agentBodies);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'customer_name' => $conversation->customer_name,
                'status' => $conversation->status,
                'customer_type' => $conversation->customerType->label,
                'type_key' => $conversation->customerType->type_key,
                'product_id' => $conversation->product_id,
                'suggested_stage' => $suggestedStage,
            ],
        ]);
    }

    public function endConversation(Request $request): JsonResponse
    {
        $request->validate(['conversation_id' => 'required|integer']);

        Conversation::where('id', $request->conversation_id)->update(['status' => 'closed']);

        return response()->json(['status' => 'closed']);
    }

    public function getStats(): JsonResponse
    {
        $total = Conversation::count();
        $active = Conversation::where('status', 'active')->count();
        $agentMessages = Message::where('sender', 'agent')->count();
        $byType = Conversation::join('customer_types', 'conversations.customer_type_id', '=', 'customer_types.id')
            ->selectRaw('customer_types.label as label, count(conversations.id) as count')
            ->groupBy('customer_types.label')
            ->get();

        return response()->json([
            'total_conversations' => $total,
            'active_conversations' => $active,
            'total_agent_messages' => $agentMessages,
            'by_type' => $byType,
        ]);
    }

    public function getMyConversations(Request $request): JsonResponse
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->with('customerType:id,label,type_key')
            ->withCount('messages')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($conv) {
                return [
                    'id' => $conv->id,
                    'customer_name' => $conv->customer_name,
                    'customer_type' => $conv->customerType?->label ?? $conv->customerType?->type_key ?? 'Unknown',
                    'status' => $conv->status,
                    'mentor_feedback' => $conv->mentor_feedback,
                    'mentor_score' => $conv->mentor_score,
                    'message_count' => $conv->messages_count,
                    'created_at' => $conv->created_at,
                ];
            });

        return response()->json($conversations);
    }

    /**
     * Resolve the chat service, optionally overriding with a request-level provider.
     */
    protected function resolveChatService(Request $request): ChatServiceInterface
    {
        $provider = $request->input('provider');

        if (!$provider) {
            return $this->chat;
        }

        $userId = $request->user()->id;

        // Look up user's stored API key for the requested provider
        $setting = UserLlmSetting::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();

        $apiKey = $setting?->api_key;

        return match ($provider) {
            'openai' => new OpenAIChatService($apiKey),
            'anthropic' => new AnthropicChatService($apiKey),
            'gemini' => new GeminiChatService($apiKey),
            'groq' => new GroqChatService($apiKey),
            'ollama' => new OllamaChatService($setting?->model),
            default => $this->chat,
        };
    }
}
