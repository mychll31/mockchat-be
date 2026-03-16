<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
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
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
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
