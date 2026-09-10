<?php

/*
|--------------------------------------------------------------------------
| School settings — reference data
|--------------------------------------------------------------------------
|
| Curated option lists for the school-settings module (M6). Kept here rather
| than in a package or a database table: the lists are small, change rarely,
| and are safe to config-cache.
|
| Defaults lean toward the initial Nigerian market, but the app is NOT
| Nigeria-only — every list carries the common alternatives, and a school
| picks its own values.
|
*/

return [

    // ISO 4217 codes → display name. Used for the "Currency" setting (Fees).
    'currencies' => [
        'NGN' => 'Nigerian Naira (₦)',
        'USD' => 'US Dollar ($)',
        'GBP' => 'Pound Sterling (£)',
        'EUR' => 'Euro (€)',
        'GHS' => 'Ghanaian Cedi (₵)',
        'KES' => 'Kenyan Shilling (KSh)',
        'ZAR' => 'South African Rand (R)',
        'XOF' => 'West African CFA Franc (CFA)',
        'XAF' => 'Central African CFA Franc (FCFA)',
        'EGP' => 'Egyptian Pound (E£)',
        'TZS' => 'Tanzanian Shilling (TSh)',
        'UGX' => 'Ugandan Shilling (USh)',
        'RWF' => 'Rwandan Franc (FRw)',
        'CAD' => 'Canadian Dollar (C$)',
        'AUD' => 'Australian Dollar (A$)',
        'INR' => 'Indian Rupee (₹)',
    ],

    // ISO 3166-1 alpha-2 → display name. Used for the school "Country" setting.
    'countries' => [
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'KE' => 'Kenya',
        'ZA' => 'South Africa',
        'TZ' => 'Tanzania',
        'UG' => 'Uganda',
        'RW' => 'Rwanda',
        'EG' => 'Egypt',
        'CM' => 'Cameroon',
        'CI' => "Côte d'Ivoire",
        'SN' => 'Senegal',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'IN' => 'India',
        'AE' => 'United Arab Emirates',
    ],

    // A short, curated set of locales the UI/formatting can rely on.
    'locales' => [
        'en' => 'English',
        'en-GB' => 'English (United Kingdom)',
        'en-NG' => 'English (Nigeria)',
        'fr' => 'Français',
    ],

    'defaults' => [
        'country' => 'NG',
        'currency' => 'NGN',
        'locale' => 'en',
        'timezone' => 'Africa/Lagos',
        'date_format' => 'd/m/Y',
        'week_starts_on' => 1,          // Monday
        'academic_year_start_month' => 9, // September
    ],

];
