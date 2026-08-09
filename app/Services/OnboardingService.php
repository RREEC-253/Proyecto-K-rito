<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OnboardingService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Register a new company with its first admin user within a transaction.
     */
    public function register(array $companyData, array $adminData, ?\Illuminate\Http\Request $request = null): array
    {
        return DB::transaction(function () use ($companyData, $adminData, $request) {
            // 1. Generate unique slug from company name
            $slug = $this->generateUniqueSlug($companyData['name']);

            // 2. Create company
            $company = Company::create([
                'name' => $companyData['name'],
                'slug' => $slug,
                'business_name' => $companyData['business_name'] ?? null,
                'document_type' => $companyData['document_type'] ?? null,
                'document_number' => $companyData['document_number'] ?? null,
                'email' => isset($companyData['email']) ? strtolower($companyData['email']) : null,
                'phone' => $companyData['phone'] ?? null,
                'status' => 'active',
            ]);

            // 3. Create admin user (email and username normalized to lowercase)
            $user = User::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'username' => strtolower($adminData['username']),
                'email' => strtolower($adminData['email']),
                'password' => Hash::make($adminData['password']),
                'first_name' => $adminData['first_name'] ?? null,
                'last_name' => $adminData['last_name'] ?? null,
                'status' => true,
                'email_verified' => false,
            ]);

            // 4. Assign Owner/Admin role via user_roles
            $ownerRole = Role::withoutGlobalScopes()
                ->where('is_system', true)
                ->where('name', 'Owner')
                ->whereNull('company_id')
                ->first();

            if (!$ownerRole) {
                // Fallback: create a tenant-specific Owner role
                $ownerRole = Role::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => 'Owner',
                    'description' => 'Administrador principal de la empresa',
                    'is_system' => true,
                    'is_editable' => false,
                    'is_deletable' => false,
                ]);
            }

            $user->roles()->attach($ownerRole->id, ['created_at' => now()]);

            // 5. Audit log
            $this->audit->log(
                module: 'onboarding',
                action: 'company_registered',
                entity: 'companies',
                entityId: $company->id,
                newValues: ['company_name' => $company->name, 'admin_email' => $user->email],
                companyId: $company->id,
                userId: $user->id,
                request: $request
            );

            return compact('company', 'user');
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
