<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Str;

/**
 * Creates a new school — a platform-admin operation.
 *
 * This is a *platform-level* action: it runs outside any tenant context and only
 * touches the `schools` table — never a `BelongsToSchool` model. Seating the
 * initial School Admin (a `school_user` write) and all school-owned data
 * (settings, academic sessions) happen afterwards, the former in the provisioning
 * controller and the latter inside the school's own tenant context.
 */
class SchoolProvisioner
{
    /**
     * @param  string|null  $slug  when null/blank, derived from the name and made unique
     */
    public function provision(string $name, ?string $slug = null): School
    {
        return School::create([
            'name' => trim($name),
            'slug' => $this->resolveSlug($slug, $name),
        ]);
    }

    private function resolveSlug(?string $slug, string $name): string
    {
        $base = Str::slug($slug !== null && trim($slug) !== '' ? $slug : $name);

        if ($base === '') {
            $base = 'school';
        }

        $candidate = $base;
        $suffix = 2;

        while (School::query()->where('slug', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
