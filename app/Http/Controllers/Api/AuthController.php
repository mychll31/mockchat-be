<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Register a new user with email and password.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => UserResource::make($user)->resolve(),
        ], 201);
    }

    /**
     * Authenticate a user with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid email or password.'], 401);
        }

        // Revoke old tokens and issue a fresh one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => UserResource::make($user)->resolve(),
        ]);
    }

    /**
     * Redirects the browser to Google's OAuth consent screen.
     * Web: browser navigates here directly (no fetch — no CORS).
     * Mobile: pass ?redirect_scheme=mockchat to deep-link back after auth.
     */
    public function redirectUrl(Request $request)
    {
        $scheme = $request->query('redirect_scheme');

        $driver = Socialite::driver('google')->stateless();

        if ($scheme) {
            // Encode the mobile scheme into OAuth state so callback can redirect back
            $driver = $driver->with(['state' => urlencode($scheme)]);
        }

        return $driver->redirect();
    }

    /**
     * Handles the Google OAuth callback, upserts the user, and returns a Sanctum token.
     * When ?redirect_scheme=<scheme> is provided, redirects to the mobile deep link instead.
     */
    public function handleCallback(Request $request)
    {
        $code = $request->query('code');

        if (! $code) {
            return response()->json(['error' => 'Missing authorization code.'], 422);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Google authentication failed: ' . $e->getMessage()], 401);
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'avatar' => $googleUser->getAvatar(),
            ]
        );

        // Revoke old tokens and issue a fresh one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        // If a mobile redirect_scheme was encoded in the OAuth state, redirect via deep link
        $state = $request->query('state');
        $scheme = $state ? urldecode($state) : null;
        if ($scheme && preg_match('/^[a-z][a-z0-9+\-.]*$/', $scheme)) {
            return redirect()->away(
                $scheme . '://auth?token=' . urlencode($token)
                . '&user_id=' . $user->id
                . '&name=' . urlencode($user->name)
                . '&email=' . urlencode($user->email ?? '')
                . '&avatar=' . urlencode($user->avatar ?? '')
            );
        }

        // Web flow: redirect to SPA callback page with token in query string
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        // Role is intentionally omitted from the redirect URL to prevent tampering.
        // The frontend must fetch the role from the authenticated /auth/me endpoint.
        return redirect()->away(
            $frontendUrl . '/auth/callback?token=' . urlencode($token)
            . '&user_id=' . $user->id
            . '&name=' . urlencode($user->name)
            . '&email=' . urlencode($user->email ?? '')
            . '&avatar=' . urlencode($user->avatar ?? '')
        );
    }

    /**
     * Returns the current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['user' => UserResource::make($user)->resolve()]);
    }

    /**
     * Revoke current token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'logged_out']);
    }
}
