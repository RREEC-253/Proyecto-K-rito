<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'status' => $this->status,
            'email_verified' => $this->email_verified,
            'must_change_password' => $this->must_change_password,
            'last_login' => $this->last_login?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
