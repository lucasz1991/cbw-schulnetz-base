<?php

namespace App\View\Components\Logo;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use App\Models\Setting;

class SiteLogoHorizontal extends Component
{
    public function __construct(
        public ?string $alt = null,
        public ?string $class = null,
        public bool $linkToHome = true,
    ) {}

    public function render(): View|Closure|string
    {
        $cacheKey = 'settings.logo_horizontal.v3';
        $baseUrl = rtrim((string) (Setting::where('key', 'base_api_url')->value('value') ?: config('app.url') ?: url('/')), '/');

        $src = Cache::remember($cacheKey, now()->addHours(1), function () use ($baseUrl) {
            $val = Setting::getValue('base', 'logo_horizontal');

            if (!$val) {
                return null;
            }

            if (preg_match('~^https?://~i', $val)) {
                return $val;
            }

            $normalized = ltrim($val, '/');

            if (str_starts_with($normalized, 'storage/')) {
                return $baseUrl . '/' . $normalized;
            }

            // Settings speichern i.d.R. den relativen Public-Disk-Pfad (z.B. settings/branding/...)
            return $baseUrl . '/storage/' . $normalized;
        });

        $alt = $this->alt ?: config('app.name', 'Logo');

        return view('components.logo.site-logo-horizontal', [
            'src'        => $src,
            'alt'        => $alt,
            'class'      => $this->class,
            'linkToHome' => $this->linkToHome,
        ]);
    }
}
