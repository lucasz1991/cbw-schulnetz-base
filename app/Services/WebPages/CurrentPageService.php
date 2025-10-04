<?php

namespace App\Services\WebPages;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\WebPage;

class CurrentPageService
{
    public function findWebPage(?Request $request = null): ?WebPage
    {
        $request    = $request ?? request();
        $candidates = $this->resolveCandidates($request);

        if (empty($candidates)) {
            return null;
        }

        $pages = WebPage::query()
            ->whereIn('slug', $candidates)
            ->get();

        if ($pages->isEmpty()) {
            return null;
        }

        $rank = array_flip($candidates);

        return $pages
            ->sortBy(fn (WebPage $p) => $rank[$p->slug])
            ->first();
    }

    public function resolveSlug(?Request $request = null): string
    {
        $request    = $request ?? request();
        $candidates = $this->resolveCandidates($request);
        return $candidates[0] ?? 'start';
    }

    /**
     * NEU: Liefert die priorisierte Kandidatenliste öffentlich.
     * Optional kannst du einen eigenen Pfad/Slug übergeben (z. B. wenn der
     * Component-Caller eine "page" vorgibt); sonst wird der aktuelle Request genutzt.
     */
    public function candidates(?Request $request = null, ?string $overridePathOrSlug = null): array
    {
        if ($overridePathOrSlug !== null && $overridePathOrSlug !== '') {
            $segments = $this->normalizeSegments($overridePathOrSlug);
            return $this->buildCandidatesFromSegments($segments);
        }

        $request = $request ?? request();
        return $this->resolveCandidates($request);
    }

    /**
     * Intern: Erzeugt Kandidaten aus dem aktuellen Request.
     */
    protected function resolveCandidates(Request $request): array
    {
        if ($param = $this->routeParamSlug($request)) {
            $segments = $this->normalizeSegments($param);
        } else {
            $segments = $this->segmentsFromPath($request);
        }

        if (empty($segments)) {
            return ['start'];
        }

        return $this->buildCandidatesFromSegments($segments);
    }

    /**
     * Extrahierte, wiederverwendbare Kandidaten-Erzeugung aus Segmenten.
     */
    protected function buildCandidatesFromSegments(array $segments): array
    {
        if (empty($segments)) {
            return ['start'];
        }

        $candidates = [];

        // a/b/c -> ['a/b/c', 'a/b', 'a']
        for ($len = count($segments); $len >= 1; $len--) {
            $candidates[] = Str::slug(implode('/', array_slice($segments, 0, $len)), '-');
        }

        // einzelne Segmente rückwärts (c, b, a), falls noch nicht vorhanden
        for ($i = count($segments) - 1; $i >= 0; $i--) {
            $single = Str::slug($segments[$i], '-');
            if (!in_array($single, $candidates, true)) {
                $candidates[] = $single;
            }
        }

        if (!in_array('start', $candidates, true)) {
            $candidates[] = 'start';
        }

        return $candidates;
    }

    protected function routeParamSlug(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) return null;

        $param = $route->parameter('slug') ?? $route->parameter('page');
        $param = is_string($param) ? trim($param) : null;

        return $param === '' ? null : $param;
    }

    protected function normalizeSegments(string $pathOrSlug): array
    {
        $pathOrSlug = trim($pathOrSlug, '/');
        if ($pathOrSlug === '') return [];

        $segments = array_values(array_filter(explode('/', $pathOrSlug), fn ($s) => $s !== ''));

        $locales = config('app.locales', ['de', 'en']);
        if (!empty($segments) && in_array($segments[0], $locales, true)) {
            array_shift($segments);
        }

        if (!empty($segments)) {
            $last = end($segments);
            if ($this->looksLikeId($last)) {
                array_pop($segments);
            }
        }

        return array_map('trim', $segments);
    }

    protected function segmentsFromPath(Request $request): array
    {
        $path = trim($request->path(), '/');
        if ($path === '' || $path === '/') return ['start'];
        return $this->normalizeSegments($path);
    }

    protected function looksLikeId(string $segment): bool
    {
        return is_numeric($segment) || Str::isUuid($segment) || strlen($segment) > 25;
    }
}
