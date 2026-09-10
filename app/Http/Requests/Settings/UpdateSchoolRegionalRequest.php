<?php

namespace App\Http\Requests\Settings;

use App\Enums\DateFormat;
use App\Enums\Permission;
use App\Enums\Weekday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Regional & formatting settings: timezone, language, currency, how dates
 * display, which day a week starts on, and which calendar month the school's
 * academic year begins (read by Academic Management — this milestone stores it,
 * it does not build sessions/terms).
 */
class UpdateSchoolRegionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::SchoolSettingsUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'locale' => ['required', 'string', Rule::in(array_keys(config('school-settings.locales')))],
            'currency' => ['required', 'string', Rule::in(array_keys(config('school-settings.currencies')))],
            'date_format' => ['required', new Enum(DateFormat::class)],
            'week_starts_on' => ['required', new Enum(Weekday::class)],
            'academic_year_start_month' => ['required', 'integer', 'between:1,12'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => mb_strtoupper(trim((string) $this->input('currency')))]);
        }

        // Selects submit strings; the int-backed Weekday enum wants an int.
        if ($this->has('week_starts_on') && is_numeric($this->input('week_starts_on'))) {
            $this->merge(['week_starts_on' => (int) $this->input('week_starts_on')]);
        }
    }
}
