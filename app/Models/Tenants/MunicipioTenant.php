<?php

declare(strict_types=1);

namespace App\Models\Tenants;

use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Tenant Stancl = município/base operacional (partição administrativa, não SaaS isolado).
 *
 * @see docs/migracao/arquitetura-geral.md
 */
class MunicipioTenant extends Tenant
{
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'code', 'created_at', 'updated_at', 'data'];
    }
}
