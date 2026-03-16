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
     * Returns the Google OAuth redirect URL (for web frontend) or redirects the browser
     * directly to Google (for mobile — when redirect_scheme is present).
     */
    public function redirectUrl(Request $request)
    {
        $scheme = $request->query('redirect_scheme');

        if ($scheme) {
            // Mobile flow: store the scheme in the state so the callback can redirect back
            // We pass it via `with()` (extra query params that survive the redirect)
            return Socialite::driver('google')
                ->stateless()
                ->with(['state' => urlencode($scheme)])
                ->redirect();
        }

        // Web flow: just return the URL for the SPA to redirect
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
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

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
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
