<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Admin: list all users.
     * Mentor: list students only.
     */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $query = User::query()->where('id', '!=', $me->id);

        if ($me->role === 'mentor') {
            $query->where('role', 'student');
        }

        $users = $query->orderBy('name')->get()->map(fn ($u) => UserResource::make($u)->resolve());

        return response()->json(['users' => $users]);
    }

    /**
     * Admin: update a user's role and/or enabled status.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role'    => 'nullable|in:admin,mentor,student',
            'enabled' => 'nullable|boolean',
        ]);

        $data = [];
        if ($request->has('role')) {
            $data['role'] = $request->input('role');
        }
        if ($request->has('enabled')) {
            $data['enabled'] = $request->boolean('enabled');
        }

        $user->update($data);

        return response()->json(['user' => UserResource::make($user->refresh())->resolve()]);
    }

    /**
     * Admin/Mentor: toggle a student's enabled status.
     */
    public function toggle(Request $request, User $user): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $user->update(['enabled' => ! $user->enabled]);

        return response()->json(['user' => UserResource::make($user->refresh())->resolve()]);
    }

    /**
     * Admin/Mentor: list a student's conversations.
     */
    public function conversations(Request $request, User $user): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $conversations = $user->conversations()
            ->with('customerType')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'customer_name'   => $c->customer_name,
                'customer_type'   => $c->customerType?->label,
                'status'          => $c->status,
                'mentor_feedback' => $c->mentor_feedback,
                'mentor_score'    => $c->mentor_score,
                'created_at'      => $c->created_at,
            ]);

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Admin/Mentor: get messages for one of a student's conversations.
     */
    public function conversationMessages(Request $request, User $user, int $conversationId): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $conversation = $user->conversations()->findOrFail($conversationId);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'sender'     => $m->sender,
                'body'       => $m->body,
                'created_at' => $m->created_at,
            ]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Admin/Mentor: list a student's products.
     */
    public function studentProducts(Request $request, User $user): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $products = $user->products()->orderBy('created_at', 'desc')->get();

        return response()->json(['products' => ProductResource::collection($products)]);
    }

    /**
     * Admin/Mentor: create a product for a student.
     */
    public function storeStudentProduct(Request $request, User $user): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'category'    => 'nullable|string|max:100',
            'image_url'   => 'nullable|url|max:500',
        ]);

        $product = $user->products()->create($validated);

        return response()->json(['product' => new ProductResource($product)], 201);
    }

    /**
     * Admin/Mentor: delete a student's product.
     */
    public function destroyStudentProduct(Request $request, User $user, int $productId): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $user->products()->findOrFail($productId)->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * Admin/Mentor: bulk-assign products to multiple students.
     * Copies each selected product to each selected student.
     */
    public function bulkAssignProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
        ]);

        $products = \App\Models\Product::whereIn('id', $validated['product_ids'])->get();
        $students = User::whereIn('id', $validated['student_ids'])->where('role', 'student')->get();

        $created = 0;
        foreach ($students as $student) {
            foreach ($products as $product) {
                // Skip if student already has a product with the same name
                if ($student->products()->where('name', $product->name)->exists()) {
                    continue;
                }
                $student->products()->create([
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'category' => $product->category,
                    'image_url' => $product->image_url,
                ]);
                $created++;
            }
        }

        return response()->json([
            'message' => $created . ' products assigned',
            'created' => $created,
        ]);
    }

    /**
     * Admin/Mentor: bulk-assign LLM settings to multiple students.
     * Copies the mentor's own configured LLM settings (by provider) to each selected student.
     */
    public function bulkAssignLlmSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_keys'  => 'required|array|min:1',
            'provider_keys.*' => 'string|in:openai,anthropic,gemini,groq,ollama',
            'student_ids'    => 'required|array|min:1',
            'student_ids.*'  => 'exists:users,id',
        ]);

        $mentor = $request->user();
        $mentorSettings = $mentor->llmSettings()
            ->whereIn('provider', $validated['provider_keys'])
            ->get();

        $students = User::whereIn('id', $validated['student_ids'])
            ->where('role', 'student')
            ->get();

        $created = 0;
        foreach ($students as $student) {
            foreach ($mentorSettings as $setting) {
                $student->llmSettings()->updateOrCreate(
                    ['provider' => $setting->provider],
                    [
                        'api_key'    => $setting->getRawOriginal('api_key'),
                        'model'      => $setting->model,
                        'is_default' => $setting->is_default,
                    ]
                );
                $created++;
            }
        }

        return response()->json([
            'message' => $created . ' LLM setting(s) assigned',
            'created' => $created,
        ]);
    }

    /**
     * Admin/Mentor: list a student's LLM settings.
     */
    public function studentLlmSettings(Request $request, User $user): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $settings = $user->llmSettings()->get()->map(fn ($s) => [
            'id'          => $s->id,
            'provider'    => $s->provider,
            'model'       => $s->model,
            'is_default'  => $s->is_default,
            'has_api_key' => ! empty($s->api_key),
        ]);

        return response()->json(['settings' => $settings]);
    }

    /**
     * Admin/Mentor: create/update a LLM setting for a student.
     */
    public function storeStudentLlmSetting(Request $request, User $user): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $request->validate([
            'provider'   => 'required|string|in:openai,anthropic,gemini,groq,ollama',
            'api_key'    => 'nullable|string|max:500',
            'model'      => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        if ($request->boolean('is_default')) {
            $user->llmSettings()
                ->where('provider', '!=', $request->provider)
                ->update(['is_default' => false]);
        }

        $data = [
            'model'      => $request->input('model'),
            'is_default' => $request->boolean('is_default'),
        ];

        if ($request->filled('api_key')) {
            $data['api_key'] = $request->input('api_key');
        }

        $setting = $user->llmSettings()->updateOrCreate(
            ['provider' => $request->provider],
            $data
        );

        return response()->json(['setting' => [
            'id'          => $setting->id,
            'provider'    => $setting->provider,
            'model'       => $setting->model,
            'is_default'  => $setting->is_default,
            'has_api_key' => ! empty($setting->api_key),
        ]], 201);
    }

    /**
     * Admin/Mentor: delete a student's LLM setting.
     */
    public function destroyStudentLlmSetting(Request $request, User $user, string $provider): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $deleted = $user->llmSettings()->where('provider', $provider)->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Setting not found'], 404);
        }

        return response()->json(['status' => 'deleted']);
    }

    /**
     * Admin/Mentor: save feedback and score for a student's conversation.
     */
    public function conversationFeedback(Request $request, User $user, $conversationId): JsonResponse
    {
        if ($err = $this->assertMentorCanAccess($request, $user)) {
            return $err;
        }

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'mentor_feedback' => 'nullable|string',
            'mentor_score'    => 'nullable|integer|min:0|max:100',
        ]);

        $conversation->update($validated);

        return response()->json(['conversation' => $conversation]);
    }

    /**
     * Returns 403 if the acting user is a mentor trying to act on a non-student.
     * Admins may act on anyone; this check is a no-op for them.
     */
    private function assertMentorCanAccess(Request $request, User $target): ?JsonResponse
    {
        if ($request->user()->role === 'mentor' && $target->role !== 'student') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return null;
    }
}
