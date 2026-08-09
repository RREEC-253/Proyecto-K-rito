<?php

namespace App\Services;

use App\Models\Session;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Support\Str;

class TokenService
{
    private const ACCESS_TOKEN_TTL_MINUTES = 15;
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    /**
     * Generate an access token (signed JWT-like opaque token) for the user.
     * In MVP we use a signed string; replace with proper JWT if needed.
     */
    public function generateAccessToken(User $user): string
    {
        $payload = base64_encode(json_encode([
            'sub' => $user->id,
            'company' => $user->company_id,
            'exp' => now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES)->timestamp,
            'jti' => Str::uuid()->toString(),
        ]));

        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return $payload . '.' . $signature;
    }

    /**
     * Decode and verify an access token. Returns the payload array or null if invalid.
     */
    public function verifyAccessToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);

        if (!$data || $data['exp'] < now()->timestamp) {
            return null;
        }

        return $data;
    }

    /**
     * Create a new session with a refresh token for the user. Returns [access_token, refresh_token, session].
     */
    public function createSession(User $user, ?string $ip = null, ?string $userAgent = null): array
    {
        $refreshToken = Str::random(64);
        $refreshTokenHash = hash('sha256', $refreshToken);

        $session = Session::create([
            'user_id' => $user->id,
            'refresh_token_hash' => $refreshTokenHash,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_TTL_DAYS),
            'created_at' => now(),
        ]);

        $accessToken = $this->generateAccessToken($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => self::ACCESS_TOKEN_TTL_MINUTES * 60,
            'session' => $session,
        ];
    }

    /**
     * Rotate a refresh token: revoke current session and create a new one.
     */
    public function rotateRefreshToken(Session $session, ?string $ip = null, ?string $userAgent = null): array
    {
        $session->update(['revoked_at' => now()]);

        return $this->createSession($session->user, $ip, $userAgent);
    }

    /**
     * Revoke a single session.
     */
    public function revokeSession(Session $session): void
    {
        $session->update(['revoked_at' => now()]);
    }

    /**
     * Revoke all active sessions for a user.
     */
    public function revokeAllSessions(User $user): void
    {
        Session::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);
    }

    /**
     * Find a valid session by raw refresh token.
     */
    public function findValidSession(string $rawRefreshToken): ?Session
    {
        $hash = hash('sha256', $rawRefreshToken);

        return Session::where('refresh_token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Generate a secure user token (password reset, email verification, etc.)
     * Returns [raw_token, UserToken model].
     */
    public function generateUserToken(User $user, string $type, int $expiryMinutes = 30): array
    {
        // Invalidate previous tokens of same type
        UserToken::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        $userToken = UserToken::create([
            'user_id' => $user->id,
            'type' => $type,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addMinutes($expiryMinutes),
            'created_at' => now(),
        ]);

        return [$rawToken, $userToken];
    }

    /**
     * Validate and consume a user token.
     */
    public function consumeUserToken(string $rawToken, string $type): ?UserToken
    {
        $hash = hash('sha256', $rawToken);

        $userToken = UserToken::where('token_hash', $hash)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$userToken) {
            return null;
        }

        $userToken->update(['used_at' => now()]);

        return $userToken;
    }
}
