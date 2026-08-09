<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user') ?? auth()->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $company = $user->company;

        if (!$company || !$company->isActive()) {
            return response()->json([
                'message' => 'Empresa suspendida o inactiva.',
            ], 403);
        }

        return $next($request);
    }
}
