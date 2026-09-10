<?php

namespace App\Models;

use App\Enums\Module;
use App\Support\Modules\SchoolModules;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\SchoolModuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A school's explicit on/off preference for one {@see Module}.
 *
 * School-owned ({@see BelongsToSchool}): every query is constrained to the
 * active tenant, and `school_id` is stamped from the context — never from input.
 * A row exists only when a school has *overridden* a module's catalogue default;
 * resolution and writes go through {@see SchoolModules}, not
 * this model directly.
 *
 * `module` is stored as a plain string (not cast to the enum) so an unknown /
 * retired identifier in the table can never blow up a page load — the resolver
 * simply ignores rows it does not recognise.
 */
class SchoolModule extends Model
{
    /** @use HasFactory<SchoolModuleFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'module',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
