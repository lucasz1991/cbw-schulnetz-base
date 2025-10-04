<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\PagebuilderProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\WebPages\CurrentPageService;

class PagebuilderModule extends Component
{
    public string $page;        // "primärer" Slug (Top-Kandidat)
    public string $position;
    public $modules;

    public function __construct(
        CurrentPageService $currentPage,
        ?string $page = null,
        string $position = 'page'
    ) {
        $this->position = $position;

        // Kandidaten aus explizitem $page (falls gesetzt) ODER aus Request
        $candidates = $currentPage->candidates(null, $page);
        $this->page = $candidates[0] ?? 'start';

        $now = Carbon::now();
        $locale = app()->getLocale();

        // Cache-Key inkl. Kandidaten (damit Query & Cache konsistent sind)
        $cacheKey = sprintf(
            'pagebuilder_modules_%s_%s_%s',
            sha1(implode('|', $candidates)),
            $position,
            $locale
        );

        $this->modules = Cache::remember($cacheKey, 60, function () use ($candidates, $position, $now, $locale) {
            return PagebuilderProject::query()
                // Position (JSON enthält den String)
                ->whereJsonContains('position', $position)
                // Status
                ->whereIn('status', [1, 3])
                // Veröffentlichungsfenster
                ->where(function ($q) use ($now) {
                    $q->whereNull('published_from')->orWhere('published_from', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('published_until')->orWhere('published_until', '>=', $now);
                })
                // Sprache
                ->where(function ($q) use ($locale) {
                    $q->where('lang', $locale)
                      ->orWhereNull('lang')
                      ->orWhere('lang', '');
                })
                // Seite: irgendein Kandidat ODER "all"
                ->where(function ($q) use ($candidates) {
                    $q->whereJsonContains('page', 'all');
                    foreach ($candidates as $slug) {
                        $q->orWhereJsonContains('page', $slug);
                    }
                })
                ->orderBy('order_id', 'asc')
                ->get();
        });
    }

    public function render()
    {
        return view('components.pagebuilder-module');
    }
}
