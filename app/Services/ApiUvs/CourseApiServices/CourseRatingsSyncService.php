<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Models\Course;
use App\Models\CourseRating;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CourseRatingsSyncService
{
    protected ApiUvsService $api;

    protected const ENDPOINT_SYNC = '/api/course/ratings/syncdata';

    protected const RATING_FIELDS = [
        'kb_1', 'kb_2', 'kb_3',
        'sa_1', 'sa_2', 'sa_3',
        'il_1', 'il_2', 'il_3',
        'do_1', 'do_2', 'do_3',
    ];

    public function __construct(ApiUvsService $api)
    {
        $this->api = $api;
    }

    /**
     * Push der lokal gespeicherten Kursbewertungen als Batch in Richtung UVS-Analyse.
     */
    public function syncToRemote(Course $course): bool
    {
        if (! $course->termin_id || ! $course->klassen_id) {
            Log::warning('CourseRatingsSyncService.syncToRemote: fehlende termin_id/klassen_id.', [
                'course_id'  => $course->id,
                'termin_id'  => $course->termin_id,
                'klassen_id' => $course->klassen_id,
            ]);

            return false;
        }

        $mitarbeiterId = $this->resolveMitarbeiterId($course);
        if (! $mitarbeiterId) {
            Log::warning('CourseRatingsSyncService.syncToRemote: mitarbeiter_id nicht ermittelbar.', [
                'course_id'  => $course->id,
                'termin_id'  => $course->termin_id,
                'klassen_id' => $course->klassen_id,
            ]);

            return false;
        }

        $ratingsPayload = $this->mapRatingsToBatchPayload($course);
        if (empty($ratingsPayload)) {
            Log::info('CourseRatingsSyncService.syncToRemote: keine auswertbaren Ratings vorhanden.', [
                'course_id' => $course->id,
            ]);

            return true;
        }

        $payload = [
            'termin_id'      => (string) $course->termin_id,
            'klassen_id'     => (string) $course->klassen_id,
            'mitarbeiter_id' => (string) $mitarbeiterId,
            'ratings'        => $ratingsPayload,
        ];

        $response = $this->api->request('POST', self::ENDPOINT_SYNC, $payload, []);

        if (! empty($response['ok'])) {
            Log::info('CourseRatingsSyncService.syncToRemote: Sync OK.', [
                'course_id'      => $course->id,
                'ratings_count'  => count($ratingsPayload),
                'mitarbeiter_id' => $mitarbeiterId,
            ]);

            return true;
        }

        Log::error('CourseRatingsSyncService.syncToRemote: UVS-Response nicht ok.', [
            'course_id'      => $course->id,
            'mitarbeiter_id' => $mitarbeiterId,
            'response'       => $response,
        ]);

        return false;
    }

    /**
     * Aggregiert alle lokal gespeicherten Ratings in API-kompatibles Batch-Format.
     */
    protected function mapRatingsToBatchPayload(Course $course): array
    {
        $rows = CourseRating::query()
            ->with(['user.person:id,user_id,teilnehmer_id'])
            ->where('course_id', $course->id)
            ->where('skip_course_rating', false)
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $deduped = $this->dedupeRatingsByParticipant($rows);
        $payload = [];

        foreach ($deduped as $rating) {
            foreach (self::RATING_FIELDS as $field) {
                $value = $this->normalizeRatingValue($rating->{$field});
                if ($value === null) {
                    continue;
                }

                $payload[] = [
                    'bew_id'        => $field,
                    'bew_note'      => $value,
                    'teilnehmer_id' => $this->resolveTeilnehmerIdForRating($rating),
                ];
            }
        }

        return $payload;
    }

    /**
     * Pro Teilnehmer/User nur die letzte Bewertung verwenden.
     */
    protected function dedupeRatingsByParticipant(Collection $rows): Collection
    {
        return $rows
            ->groupBy(function (CourseRating $rating) {
                if (! is_null($rating->participant_id)) {
                    return 'p:' . (string) $rating->participant_id;
                }

                return 'u:' . (string) $rating->user_id;
            })
            ->map(fn (Collection $group) => $group->first())
            ->values();
    }

    protected function normalizeRatingValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $note = (int) $value;
        if ($note < 1 || $note > 5) {
            return null;
        }

        return $note;
    }

    protected function resolveTeilnehmerIdForRating(CourseRating $rating): ?string
    {
        if ($rating->is_anonymous) {
            return null;
        }

        $teilnehmerId = $rating->user?->person?->teilnehmer_id;
        if (! $teilnehmerId) {
            return null;
        }

        return (string) $teilnehmerId;
    }

    protected function resolveMitarbeiterId(Course $course): ?string
    {
        $tutorProgramData = $course->tutor?->programdata;
        $fromTutorProgram = data_get($tutorProgramData, 'tutor.mitarbeiter_id');
        if (is_scalar($fromTutorProgram) && trim((string) $fromTutorProgram) !== '') {
            return trim((string) $fromTutorProgram);
        }
        return null;
    }
}
