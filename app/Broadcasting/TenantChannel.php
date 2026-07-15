<?php

namespace App\Broadcasting;

use App\Models\Tenant;
use App\Models\User;

class TenantChannel
{
    /**
     * Authenticate the user's access to the channel.
     */
    public function join(User $user, string $tenantId): array|bool
    {
        // Check if user belongs to this tenant
        if ($user->tenant_id !== $tenantId) {
            return false;
        }

        return true;
    }
}
