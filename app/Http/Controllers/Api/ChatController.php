<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Contracts\ChatServiceInterface;
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
    ];

    public function __construct(ChatServiceInterface $chat)
    {
        $this->chat = $chat;
    }

    public function newConversation(): JsonResponse
    {
        try {
            $type = CustomerType::inRandomOrder()->first();
            if (!$type) {
                return response()->json(['error' => 'No customer types configured'], 500);
            }

            $names = $this->customerNames[$type->type_key] ?? ['Customer'];
            $customerName = $names[array_rand($names)];

            $conversation = Conversation::create([
                'customer_type_id' => $type->id,
                'customer_name' => $customerName,
                'status' => 'active',
            ]);

            $opener = $this->chat->getCustomerOpener($type->type_key, $customerName);

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

        $conversation = Conversation::with('customerType')
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

        $reply = $this->chat->getCustomerReply(
            $conversation->customerType->type_key,
            $conversation->customer_name,
            $history,
            $body
        );

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => $reply,
        ]);

        $agentBodies = $conversation->messages()->where('sender', 'agent')->orderBy('created_at')->pluck('body')->toArray();
        $suggestedStage = ChatStageDetection::fromAgentMessages($agentBodies);

        return response()->json([
            'agent_message' => $body,
            'customer_response' => $reply,
            'suggested_stage' => $suggestedStage,
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

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'customer_name' => $conversation->customer_name,
                'status' => $conversation->status,
                'customer_type' => $conversation->customerType->label,
                'type_key' => $conversation->customerType->type_key,
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
}
