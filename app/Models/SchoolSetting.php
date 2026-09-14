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
 *   - `school_id`        — the tenant key (BelongsToSchool trait)
 *   - `completed_at`     — onboarding review flag ({@see self::markReviewed()})
 *   - `logo_path`        — branding upload ({@see self::putLogo()} / {@see self::clearLogo()})
 *   - `cover_image_path` — branding upload ({@see self::putCover()} / {@see self::clearCover()})
 *
 * `paystack_secret_key` (M20, `docs/paystack.md`) is cast `encrypted` —
 * Laravel's native `Crypt` facade keyed by `APP_KEY`, no new infrastructure.
 * It is genuinely `$fillable` (unlike the columns above, it has no separate
 * lifecycle — it is edited the same way `brand_color` is): the write path is
 * already gated by `school.settings.update` like every other setting, and
 * the controller only overwrites it when a new, non-blank value is actually
 * submitted (see `SchoolSettingsController::updatePayments()`) so re-saving
 * the form never blanks a previously-stored key. It is never rendered back
 * into a form field, logged, or exposed in any response.
 */
class SchoolSetting extends Model
{
    /** @use HasFactory<SchoolSettingFactory> */
    use BelongsToSchool, HasFactory;

    /** The private disk that holds school logos (never web-served directly). */
    public const LOGO_DISK = 'local';

    /** The private disk that holds school cover/background images. */
    public const COVER_DISK = 'local';

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
        // Branding (colours + motto only — the images go through putLogo()/putCover())
        'brand_color',
        'accent_color',
        'motto',
        // Regional / formatting
        'timezone',
        'locale',
        'currency',
        'date_format',
        'week_starts_on',
        // Academic calendar boundary
        'academic_year_start_month',
        // Online payment (M20) — see the class docblock for why the secret
        // key is fillable despite being sensitive.
        'paystack_enabled',
        'paystack_public_key',
        'paystack_secret_key',
        'paystack_test_mode',
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
            'paystack_enabled' => 'boolean',
            'paystack_secret_key' => 'encrypted',
            'paystack_test_mode' => 'boolean',
        ];
    }

    // -- Online payment (M20) -----------------------------------------------

    /** Whether this school has both switched on and fully configured Paystack. */
    public function paystackReady(): bool
    {
        return $this->paystack_enabled
            && filled($this->paystack_public_key)
            && filled($this->paystack_secret_key);
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

    // -- Branding / cover image --------------------------------------------

    public function hasCover(): bool
    {
        return $this->cover_image_path !== null
            && Storage::disk(self::COVER_DISK)->exists($this->cover_image_path);
    }

    /**
     * Replace the school's cover/background image. Deletes any previous file.
     * `$path` must already be a stored path on the private COVER_DISK.
     */
    public function putCover(string $path): void
    {
        $previous = $this->cover_image_path;

        $this->cover_image_path = $path;
        $this->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::COVER_DISK)->delete($previous);
        }
    }

    public function clearCover(): void
    {
        $path = $this->cover_image_path;

        $this->cover_image_path = null;
        $this->save();

        if ($path !== null) {
            Storage::disk(self::COVER_DISK)->delete($path);
        }
    }

    // -- Branding / safe contrast -------------------------------------------

    /**
     * `#ffffff` or `#111827` (Tailwind gray-900) — whichever reads legibly on
     * top of the given 6-digit hex colour, by relative luminance (WCAG
     * approximation). Falls back to the app's default text colour when
     * `$hex` is empty or malformed, so a bad/missing custom colour never
     * produces invisible text.
     */
    public static function readableTextColor(?string $hex): string
    {
        if ($hex === null || ! preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m)) {
            return '#111827';
        }

        [$r, $g, $b] = array_map(
            fn (string $c): float => hexdec($c) / 255,
            str_split($m[1], 2),
        );

        // Relative luminance (sRGB, gamma-corrected) — standard WCAG formula.
        $linear = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);

        return $luminance > 0.5 ? '#111827' : '#ffffff';
    }
}
