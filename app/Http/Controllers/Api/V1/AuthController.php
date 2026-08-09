<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private TokenService $tokenService
    ) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                companySlug: $request->input('company_slug'),
                identifier: $request->input('identifier'),
                password: $request->input('password'),
                request: $request
            );

            return response()->json([
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'],
                'user' => new UserResource($result['user']),
            ]);
        } catch (\RuntimeException $e) {
            // Handle locked account (429)
            if ($e->getCode() === 429) {
                [$message, $retryAfter] = explode('|', $e->getMessage(), 2);
                return response()->json([
                    'message' => $message,
                    'retry_after' => (int) $retryAfter,
                ], 429);
            }
            return $this->handleAuthException($e);
        } catch (\Exception $e) {
            return $this->handleAuthException($e);
        }
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refresh(
                rawRefreshToken: $request->input('refresh_token'),
                request: $request
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 401);
        }
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('auth_session');

        if ($session) {
            $this->authService->logout($session, $request);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if ($user) {
            $this->authService->logoutAll($user, $request);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword(
            companySlug: $request->input('company_slug'),
            email: $request->input('email'),
            request: $request
        );

        // Always generic to avoid user enumeration
        return response()->json([
            'message' => 'Si el correo existe, se enviará un enlace de recuperación.',
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword(
                rawToken: $request->input('token'),
                newPassword: $request->input('password'),
                request: $request
            );

            return response()->json(['message' => 'Contraseña actualizada correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/auth/send-verification
     */
    public function sendVerification(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $this->authService->sendEmailVerification($user, $request);

        return response()->json(['message' => 'Correo de verificación enviado.']);
    }

    /**
     * POST /api/v1/auth/verify-email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        try {
            $this->authService->verifyEmail($request->input('token'), $request);
            return response()->json(['message' => 'Correo verificado correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function handleAuthException(\Exception $e): JsonResponse
    {
        $code = in_array($e->getCode(), [401, 403, 422, 429]) ? $e->getCode() : 401;
        return response()->json(['message' => $e->getMessage()], $code);
    }
}
