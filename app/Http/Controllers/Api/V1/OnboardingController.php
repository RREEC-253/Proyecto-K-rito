<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboardingService) {}

    /**
     * POST /api/v1/onboarding/register
     */
    public function register(RegisterCompanyRequest $request): JsonResponse
    {
        try {
            $result = $this->onboardingService->register(
                companyData: $request->input('company'),
                adminData: $request->input('admin'),
                request: $request
            );

            return response()->json([
                'company' => new CompanyResource($result['company']),
                'user' => new UserResource($result['user']),
            ], 201);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'message' => 'El usuario o correo ya existe en esta empresa.',
                'errors' => ['identifier' => ['El nombre de usuario o correo ya está en uso.']],
            ], 422);
        }
    }
}
