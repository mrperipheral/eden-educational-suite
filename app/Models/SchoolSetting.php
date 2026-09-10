<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\SchoolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-school configuration (1:1 with {@see School}). School-owned: every query
 * is constrained to the active tenant by {@see BelongsToSchool}, and `school_id`
 * is stamped from the context on create — never from request input.
 *
 * `completed_at` is set the first time an administrator saves the settings form
 * and drives the onboarding checklist; it is not mass-assignable.
 */
class SchoolSetting extends Model
{
    /** @use HasFactory<SchoolSettingFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'timezone',
        'locale',
        'contact_email',
        'contact_phone',
    ];

    /**
     * Sensible defaults for the initial target market, fully overridable in the
     * settings form. Kept in sync with the migration defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Africa/Lagos',
        'locale' => 'en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function markReviewed(): void
    {
        if ($this->completed_at === null) {
            $this->completed_at = now();
            $this->save();
        }
    }
}
