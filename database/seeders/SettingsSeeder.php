<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'type' => 'base',
                'key' => 'company_name',
                'value' => 'cbw-weiterbildung',
            ],
            [
                'type' => 'base',
                'key' => 'contact_email',
                'value' => 'test@cbw-weiterbildung.de',
            ],
            [
                'type' => 'base',
                'key' => 'app_url',
                'value' => 'https://cbw-weiterbildung-schulnetz.shopspaze.com',
            ],
            [
                'type' => 'base',
                'key' => 'currency',
                'value' => 'euro',
            ],
            [
                'type' => 'base',
                'key' => 'maintenance_mode',
                'value' => false,
            ],
            [
                'type' => 'grapesjs',
                'key' => 'api_key',
                'value' => '383e561443ac4c5bbd775fb883f9ba8ec54273cccb864567a3eb5ff3bf42206f',
            ],
            [
                'type' => 'api',
                'key' => 'uvs_api_url',
                'value' => 'https:\/\/uvs.cbw-weiterbildung.de:50123',
            ],
            [
                'type' => 'api',
                'key' => 'uvs_api_key',
                'value' => '6Flw8C00p49gFbOLjZka2Hj85gbxTncibEJeGlQXoKyjyp2NtMCzr3zrMoeDDO0O',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                [
                    'type' => $setting['type'],
                    'key' => $setting['key'],
                ],
                [
                    'value' => $setting['value'],
                ]
            );
        }
    }
}
