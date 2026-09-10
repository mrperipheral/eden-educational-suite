<?php

namespace App\Models;

use App\Enums\DateFormat;
use App\Enums\Weekday;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\SchoolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Per-school configuration (1:1 with {@see School}). School-owned: every query
 * is constrained to the active tenant by {@see BelongsToSchool}, and `school_id`
 * is stamped from the context on create — never from request input.
 *
 * System-controlled columns kept OUT of `$fillable` and written only by their
 * dedicated handler:
 *   - `school_id`   — the tenant key (BelongsToSchool trait)
 *   - `completed_at`— onboarding review flag ({@see self::markReviewed()})
 *   - `logo_path`   — branding upload ({@see self::putLogo()} / {@see self::clearLogo()})
 */
class SchoolSetting extends Model
{
    /** @use HasFactory<SchoolSettingFactory> */
    use BelongsToSchool, HasFactory;

    /** The private disk that holds school logos (never web-served directly). */
    public const LOGO_DISK = 'local';

    /** @var list<string> */
    protected $fillable = [
        // Profile
        'contact_email',
        'contact_phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'website_url',
        // Branding (colour only — the file goes through putLogo())
        'brand_color',
        // Regional / formatting
        'timezone',
        'locale',
        'currency',
        'date_format',
        'week_starts_on',
        // Academic calendar boundary
        'academic_year_start_month',
    ];

    /**
     * Defaults for a brand-new row — kept in sync with the migration and
     * `config('school-settings.defaults')`.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'Africa/Lagos',
        'locale' => 'en',
        'country' => 'NG',
        'currency' => 'NGN',
        'date_format' => 'd/m/Y',
        'week_starts_on' => 1,
        'academic_year_start_month' => 9,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'date_format' => DateFormat::class,
            'week_starts_on' => Weekday::class,
            'academic_year_start_month' => 'integer',
        ];
    }

    // -- Onboarding ---------------------------------------------------------

    public function markReviewed(): void
    {
        if ($this->completed_at === null) {
            $this->completed_at = now();
            $this->save();
        }
    }

    // -- Branding / logo --------------------------------------------------

    public function hasLogo(): bool
    {
        return $this->logo_path !== null
            && Storage::disk(self::LOGO_DISK)->exists($this->logo_path);
    }

    /**
     * Replace the school's logo. Deletes any previous file. `$path` must already
     * be a stored path on the private LOGO_DISK.
     */
    public function putLogo(string $path): void
    {
        $previous = $this->logo_path;

        $this->logo_path = $path;
        $this->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::LOGO_DISK)->delete($previous);
        }
    }

    public function clearLogo(): void
    {
        $path = $this->logo_path;

        $this->logo_path = null;
        $this->save();

        if ($path !== null) {
            Storage::disk(self::LOGO_DISK)->delete($path);
        }
    }
}
