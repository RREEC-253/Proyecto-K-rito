<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_DURATION_MINUTES = 15;

    public function __construct(
        private TokenService $tokenService,
        private AuditService $audit
    ) {}

    /**
     * Authenticate a user by company slug, identifier (username or email) and password.
     * Returns ['access_token', 'refresh_token', 'expires_in', 'user'] on success.
     * Throws exceptions for specific error states.
     */
    public function login(string $companySlug, string $identifier, string $password, Request $request): array
    {
        // 1. Resolve tenant by slug
        $company = Company::where('slug', $companySlug)->whereNull('deleted_at')->first();

        if (!$company) {
            $this->audit->log('auth', 'login_failed', request: $request);
            throw new \Exception('Credenciales inválidas', 401);
        }

        // 2. Check company status
        if (!$company->isActive()) {
            throw new \Exception('Empresa suspendida', 403);
        }

        // 3. Find user by email or username within the tenant (normalize to lowercase)
        $identifier = strtolower($identifier);
        $user = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                  ->orWhere('username', $identifier);
            })
            ->first();

        if (!$user) {
            $this->audit->log('auth', 'login_failed', companyId: $company->id, request: $request);
            throw new \Exception('Credenciales inválidas', 401);
        }

        // 4. Check user status
        if (!$user->status) {
            $this->audit->log('auth', 'login_failed', companyId: $company->id, userId: $user->id, request: $request);
            throw new \Exception('Credenciales inválidas', 401);
        }

        // 5. Check lock: if expired, reset counter before evaluating password
        if ($user->locked_until !== null && $user->locked_until->isPast()) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);
            $user->refresh();
        }

        // 6. Respond 429 if still locked
        if ($user->isLocked()) {
            $retryAfter = now()->diffInSeconds($user->locked_until);
            $this->audit->log('auth', 'login_blocked', companyId: $company->id, userId: $user->id, request: $request);
            throw new \RuntimeException("Cuenta bloqueada|{$retryAfter}", 429);
        }

        // 7. Verify password
        if (!Hash::check($password, $user->password)) {
            $this->handleFailedAttempt($user, $company->id, $request);
            throw new \Exception('Credenciales inválidas', 401);
        }

        // 8. Successful login: reset counter, update last_login
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login' => now(),
        ]);

        // 9. Create session (access + refresh token)
        $tokenData = $this->tokenService->createSession($user, $request->ip(), $request->userAgent());

        // 10. Audit successful login
        $this->audit->log('auth', 'login_success', companyId: $company->id, userId: $user->id, request: $request);

        return [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in' => $tokenData['expires_in'],
            'user' => $user,
        ];
    }

    /**
     * Handle a failed login attempt with lockForUpdate to prevent race conditions.
     */
    private function handleFailedAttempt(User $user, string $companyId, Request $request): void
    {
        \DB::transaction(function () use ($user, $companyId, $request) {
            $freshUser = User::withoutGlobalScopes()
                ->lockForUpdate()
                ->find($user->id);

            $attempts = $freshUser->failed_login_attempts + 1;
            $lockedUntil = null;

            if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
                $lockedUntil = now()->addMinutes(self::LOCK_DURATION_MINUTES);
                $this->audit->log('auth', 'account_locked', companyId: $companyId, userId: $user->id, request: $request);
            }

            $freshUser->update([
                'failed_login_attempts' => $attempts,
                'locked_until' => $lockedUntil,
            ]);
        });

        $this->audit->log('auth', 'login_failed', companyId: $companyId, userId: $user->id, request: $request);
    }

    /**
     * Refresh access token by validating and rotating the refresh token.
     */
    public function refresh(string $rawRefreshToken, Request $request): array
    {
        $session = $this->tokenService->findValidSession($rawRefreshToken);

        if (!$session) {
            throw new \Exception('Token inválido o expirado', 401);
        }

        $tokenData = $this->tokenService->rotateRefreshToken($session, $request->ip(), $request->userAgent());

        $this->audit->log('auth', 'token_refreshed', companyId: $session->user->company_id, userId: $session->user_id, request: $request);

        return [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in' => $tokenData['expires_in'],
        ];
    }

    /**
     * Logout: revoke current session.
     */
    public function logout(Session $session, Request $request): void
    {
        $this->tokenService->revokeSession($session);
        $this->audit->log('auth', 'logout', companyId: $session->user->company_id, userId: $session->user_id, request: $request);
    }

    /**
     * Logout all sessions for the authenticated user.
     */
    public function logoutAll(User $user, Request $request): void
    {
        $this->tokenService->revokeAllSessions($user);
        $this->audit->log('auth', 'logout_all', companyId: $user->company_id, userId: $user->id, request: $request);
    }

    /**
     * Generate and store a password reset token.
     */
    public function forgotPassword(string $companySlug, string $email, Request $request): void
    {
        $company = Company::where('slug', $companySlug)->whereNull('deleted_at')->first();

        // Always respond generically — don't reveal if email exists
        if (!$company) {
            return;
        }

        $user = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('email', strtolower($email))
            ->whereNull('deleted_at')
            ->first();

        if (!$user) {
            return;
        }

        [$rawToken] = $this->tokenService->generateUserToken($user, 'password_reset', 30);

        $this->audit->log('auth', 'password_reset_requested', companyId: $company->id, userId: $user->id, request: $request);

        // TODO: dispatch mail event with $rawToken
        // event(new PasswordResetRequested($user, $rawToken));
    }

    /**
     * Reset password using a valid token.
     */
    public function resetPassword(string $rawToken, string $newPassword, Request $request): void
    {
        $userToken = $this->tokenService->consumeUserToken($rawToken, 'password_reset');

        if (!$userToken) {
            throw new \Exception('Token inválido o expirado', 422);
        }

        $user = $userToken->user;

        $user->update(['password' => \Hash::make($newPassword)]);

        // Revoke all sessions after password change
        $this->tokenService->revokeAllSessions($user);

        $this->audit->log('auth', 'password_reset', companyId: $user->company_id, userId: $user->id, request: $request);
    }

    /**
     * Generate email verification token.
     */
    public function sendEmailVerification(User $user, Request $request): void
    {
        [$rawToken] = $this->tokenService->generateUserToken($user, 'email_verification', 60 * 24);

        $this->audit->log('auth', 'email_verification_sent', companyId: $user->company_id, userId: $user->id, request: $request);

        // TODO: dispatch mail event
        // event(new EmailVerificationRequested($user, $rawToken));
    }

    /**
     * Verify email with token.
     */
    public function verifyEmail(string $rawToken, Request $request): void
    {
        $userToken = $this->tokenService->consumeUserToken($rawToken, 'email_verification');

        if (!$userToken) {
            throw new \Exception('Token inválido o expirado', 422);
        }

        $userToken->user->update(['email_verified' => true]);

        $this->audit->log('auth', 'email_verified', companyId: $userToken->user->company_id, userId: $userToken->user_id, request: $request);
    }
}
