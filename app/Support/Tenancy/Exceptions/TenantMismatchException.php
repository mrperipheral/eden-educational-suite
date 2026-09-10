<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown when code tries to attach a school-owned record to a school other than
 * the active tenant, or to change an existing record's `school_id`. School
 * ownership is immutable and always derived from the tenant context.
 *
 * Rendered as 403: it means someone (or some bug) tried to cross a tenant
 * boundary.
 */
class TenantMismatchException extends RuntimeException
{
    public function render(): never
    {
        abort(403, 'This action is not allowed for the current school.');
    }
}
