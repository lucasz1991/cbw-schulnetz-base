<?php

namespace App\View\Components\Logo;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        $cacheKey = 'settings.logo_horizontal.v1';

        $src = Cache::remember($cacheKey, now()->addHours(1), function () {
            $val = Setting::getValue('base', 'logo_horizontal');

            if (!$val) {
                return null;
            }

            if (preg_match('~^https?://~i', $val)) {
                return $val;
            }

            if (Storage::disk('public')->exists($val)) {
                return Storage::disk('public')->url($val);
            }

            return asset($val);
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
