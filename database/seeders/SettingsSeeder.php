<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $u = 'https://finnskerrisdaleladies.com/wp-content/uploads/2015/02/';
        $settings = [
            // general
            ['site_name', 'Finns Kerrisdale Ladies', 'general'],
            ['tagline', 'The best kept secret on the west side', 'general'],
            ['logo_url', $u.'logo.png', 'general'],
            ['favicon_url', null, 'general'],
            ['copyright_text', 'Copyright © 2015 Finns Kerrisdale Ladies. All Rights Reserved.', 'general'], // as on the live site; use {year} for the current year
            ['posts_per_page', 10, 'general'],

            // contact
            ['address', '5401 West Boulevard, Vancouver, BC, V6M 3W5', 'contact'],
            ['address_street', '5401 West Boulevard', 'contact'],
            ['address_city', 'Vancouver', 'contact'],
            ['address_region', 'BC', 'contact'],
            ['address_postal_code', 'V6M 3W5', 'contact'],
            ['address_country', 'CA', 'contact'],
            ['phone', '(604) 266-8358', 'contact'],
            ['email', 'fkl@finnskerrisdaleladies.com', 'contact'],
            ['hours', [
                ['days' => 'Tue – Sat', 'time' => '10 – 5:30'],
                ['days' => 'Sun – Mon', 'time' => 'Closed'],
            ], 'contact'],
            ['hours_note', '(except for the last Monday of the month)', 'contact'],
            ['map_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2605.0651494297977!2d-123.15568900000001!3d49.237254!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x54867379f873d3f1%3A0x4eaa62fdc08499bc!2sFinns+Kerrisdale+Ladies!5e0!3m2!1sen!2sca!4v1423171481636', 'contact'],
            ['opening_hours_schema', [
                ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '10:00', 'closes' => '17:30'],
            ], 'contact'],
            ['social_links', [], 'contact'],

            // forms
            ['subscribe_title', 'Email Subscription', 'forms'],
            ['subscribe_text', 'Enter your email address to subscribe to our blog and receive notifications of new posts by email.', 'forms'],
            ['subscribe_button', 'Sign me up!', 'forms'],
            ['contact_form_enabled', false, 'forms'],

            // seo
            ['seo_default_description', 'Finns Kerrisdale Ladies – European labels, timeless designs and personal service on West Boulevard, Vancouver. The best kept secret on the west side.', 'seo'],
            ['og_default_image', $u.'banner_home_all.jpg', 'seo'],
            ['google_site_verification', '', 'seo'],
        ];

        foreach ($settings as [$key, $value, $group]) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group, 'is_public' => true]);
        }

        // private settings (never sent to the public API)
        Setting::firstOrCreate(['key' => 'notification_email'], ['value' => 'fkl@finnskerrisdaleladies.com', 'group' => 'forms', 'is_public' => false]);
        app(\App\Services\SettingsService::class)->flush();
    }
}
