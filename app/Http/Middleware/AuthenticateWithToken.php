<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithToken
{
    public function __construct(private TokenService $tokenService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $payload = $this->tokenService->verifyAccessToken($bearerToken);

        if (!$payload) {
            return response()->json(['message' => 'Token inválido o expirado.'], 401);
        }

        $user = \App\Models\User::withoutGlobalScopes()->find($payload['sub']);

        if (!$user || !$user->status || $user->deleted_at) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Attach user to request attributes for downstream controllers
        $request->attributes->set('auth_user', $user);
        auth()->setUser($user);

        return $next($request);
    }
}
