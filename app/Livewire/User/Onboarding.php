<?php

namespace App\Livewire\User;

use App\Models\OnboardingVideo;
use App\Models\OnboardingVideoView;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Livewire\Component;

class Onboarding extends Component
{
    public ?int $selectedVideoId = null;
    public int $startAtSeconds = 0;

    public function mount(): void
    {
        $items = $this->filterVideos(
            OnboardingVideo::query()
                ->active()
                ->currentlyValid()
                ->orderBy('sort_order')
                ->get()
        );

        $this->selectedVideoId = $items->value('id');

        $this->hydrateStartTime();
    }

    public function updatedSelectedVideoId(): void
    {
        $this->hydrateStartTime();

        // Wenn PDF ausgewählt wird: "gesehen" sofort setzen (1. Öffnen)
        $selected = OnboardingVideo::query()->find($this->selectedVideoId);
        if ($selected && $this->isPdf($selected)) {
            $this->markCompleted((int) $selected->id);
        }
    }

    protected function hydrateStartTime(): void
    {
        $this->startAtSeconds = 0;

        $userId = $this->resolveUserId();
        if (!$userId || !$this->selectedVideoId) return;

        $view = OnboardingVideoView::query()
            ->where('user_id', $userId)
            ->where('onboarding_video_id', $this->selectedVideoId)
            ->first();

        if ($view && !$view->is_completed) {
            $this->startAtSeconds = (int) $view->progress_seconds;
        }
    }

    public function saveProgress(int $videoId, int $seconds, int $duration): void
    {
        $userId = $this->resolveUserId();
        if (!$userId) return;

        $seconds = max(0, $seconds);
        $duration = max(0, $duration);

        $view = OnboardingVideoView::query()->firstOrCreate(
            ['user_id' => $userId, 'onboarding_video_id' => $videoId],
            ['progress_seconds' => 0, 'is_completed' => false]
        );

        // nur vorwärts speichern
        $view->updateProgress($seconds);

        if ($duration > 0 && $view->progress_seconds >= max(0, $duration - 1)) {
            // auf duration hochziehen, damit Anzeige "voll" ist
            $view->updateProgress($duration);

            if (!$view->is_completed) {
                $view->markCompleted();
            }
        }

    }

public function markCompleted(int $videoId, int $duration = 0): void
{
    $userId = $this->resolveUserId();
    if (!$userId) return;

    $duration = max(0, $duration);

    $view = OnboardingVideoView::query()->firstOrCreate(
        ['user_id' => $userId, 'onboarding_video_id' => $videoId],
        ['progress_seconds' => 0, 'is_completed' => false]
    );

    // Progress auf "voll" setzen (damit UI 20/20 zeigt)
    if ($duration > 0) {
        $view->updateProgress($duration);
    }

    if (!$view->is_completed) {
        $view->markCompleted();
    }
}


    protected function resolveUserId(): ?int
    {
        return Auth::id();
    }

    protected function resolveFileUrl(OnboardingVideo $item): ?string
    {
        $file = $item->videoFile ?? null;
        return $file ? $file->getEphemeralPublicUrl() : null;
    }

    protected function isPdf(OnboardingVideo $item): bool
    {
        // Annahme: euer File hat mime_type oder original_name / extension.
        // Passe das an eure Felder an, falls nötig.
        $file = $item->videoFile ?? null;
        $mime = strtolower((string)($file->mime_type ?? ''));
        $name = strtolower((string)($file->original_name ?? $file->name ?? ''));

        return str_contains($mime, 'pdf') || str_ends_with($name, '.pdf');
    }

    public function render()
    {
        $userId = $this->resolveUserId();

        $items = $this->filterVideos(
            OnboardingVideo::query()
                ->active()
                ->currentlyValid()
                ->orderBy('sort_order')
                ->get()
        );

        $viewsById = collect();
        if ($userId) {
            $viewsById = OnboardingVideoView::query()
                ->where('user_id', $userId)
                ->whereIn('onboarding_video_id', $items->pluck('id'))
                ->get()
                ->keyBy('onboarding_video_id');
        }

        $list = $items->map(function (OnboardingVideo $v) use ($viewsById) {
            /** @var OnboardingVideoView|null $view */
            $view = $viewsById->get($v->id);

            $duration = (int)($v->duration_seconds ?? 0);
            $watched  = (int)($view?->progress_seconds ?? 0);

            $percent = $duration > 0 ? min(100, (int)round(($watched / $duration) * 100)) : 0;

            $isPdf = $this->isPdf($v);

            return [
                'id' => (int)$v->id,
                'title' => (string)$v->title,
                'duration_seconds' => $duration,
                'file_url' => $this->resolveFileUrl($v),
                'is_pdf' => $isPdf,
                'progress' => [
                    'exists' => (bool)$view,
                    'watched_seconds' => $watched,
                    'percent' => $percent,
                    'is_completed' => (bool)($view?->is_completed ?? false),
                ],
            ];
        });

        $selected = $list->firstWhere('id', (int)$this->selectedVideoId);

        return view('livewire.user.onboarding', [
            'videos' => $list,
            'selected' => $selected,
        ])->layout('layouts.app');
    }

    protected function filterVideos(Collection $items): Collection
    {
        $isEducation = Auth::user()?->person?->isEducation();

        return $items->filter(function (OnboardingVideo $video) use ($isEducation) {
            $type = $video->setting('type');

            if ($type === null || $type === '') {
                return true;
            }

            return match ($type) {
                'umschulung'    => $isEducation === false,
                'weiterbildung' => $isEducation === true,
                default         => false,
            };
        })->values();
    }
}
