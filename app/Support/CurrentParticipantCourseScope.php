<?php

namespace App\Support;

use App\Models\Person;

class CurrentParticipantCourseScope
{
    public static function identifiersFor(
        ?Person $person,
        ?int $openBeforeDays = null,
        ?int $closeAfterDays = null
    ): array {
        if (! $person) {
            return [
                'teilnehmer_id' => null,
                'tn_baustein_ids' => [],
                'klassen_ids' => [],
                'baustein_ids' => [],
                'restrict_to_none' => false,
            ];
        }

        $programData = is_array($person->programdata) ? $person->programdata : [];
        $statusData = is_array($person->statusdata) ? $person->statusdata : [];
        $activeContract = $person->currentParticipantContract($openBeforeDays, $closeAfterDays);
        $hasKnownContracts = collect(data_get($statusData, 'vertraege', []))
            ->contains(fn ($contract) => is_array($contract));

        if (! $activeContract && $hasKnownContracts) {
            return [
                'teilnehmer_id' => null,
                'tn_baustein_ids' => [],
                'klassen_ids' => [],
                'baustein_ids' => [],
                'restrict_to_none' => true,
            ];
        }

        $programData = self::programDataForContract($programData, $activeContract);

        $teilnehmerId = self::firstFilled([
            data_get($activeContract, 'teilnehmer_id'),
            data_get($statusData, 'teilnehmer_id'),
            data_get($programData, 'teilnehmer_id'),
            $person->teilnehmer_id,
        ]);

        $blocks = collect(data_get($programData, 'tn_baust', []))
            ->filter(fn ($block) => is_array($block));

        return [
            'teilnehmer_id' => $teilnehmerId,
            'tn_baustein_ids' => self::cleanIdentifierList($blocks->pluck('tn_baustein_id')->all()),
            'klassen_ids' => self::cleanIdentifierList($blocks->pluck('klassen_id')->all()),
            'baustein_ids' => self::cleanIdentifierList($blocks->pluck('baustein_id')->all()),
            'restrict_to_none' => false,
        ];
    }

    public static function hasCurrentContractFilter(array $identifiers): bool
    {
        return ! empty($identifiers['teilnehmer_id'])
            || ! empty($identifiers['tn_baustein_ids'])
            || ! empty($identifiers['klassen_ids']);
    }

    public static function applyForPerson($query, Person $person, string $pivotAlias = 'cpe', ?string $courseTable = 'courses'): void
    {
        $query->where($pivotAlias.'.person_id', $person->id);

        $identifiers = self::identifiersFor($person);
        if (! self::hasCurrentContractFilter($identifiers)) {
            if (! empty($identifiers['restrict_to_none'])) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $query->where(function ($current) use ($identifiers, $pivotAlias, $courseTable) {
            $hasParticipantId = ! empty($identifiers['teilnehmer_id']);
            $hasProgramFallback = ! empty($identifiers['tn_baustein_ids']) || ! empty($identifiers['klassen_ids']);

            if ($hasParticipantId) {
                $current->where($pivotAlias.'.teilnehmer_id', $identifiers['teilnehmer_id']);
            }

            if (! $hasProgramFallback) {
                return;
            }

            $legacyProgramMatch = function ($legacy) use ($identifiers, $pivotAlias, $courseTable, $hasParticipantId) {
                if ($hasParticipantId) {
                    $legacy->where(function ($unknownParticipantId) use ($pivotAlias) {
                        $unknownParticipantId
                            ->whereNull($pivotAlias.'.teilnehmer_id')
                            ->orWhere($pivotAlias.'.teilnehmer_id', '');
                    });
                }

                $legacy->where(function ($programMatch) use ($identifiers, $pivotAlias, $courseTable) {
                    $added = false;

                    if (! empty($identifiers['tn_baustein_ids'])) {
                        $programMatch->whereIn($pivotAlias.'.tn_baustein_id', $identifiers['tn_baustein_ids']);
                        $added = true;
                    }

                    if (! empty($identifiers['klassen_ids'])) {
                        if ($added) {
                            $programMatch->orWhereIn($pivotAlias.'.klassen_id', $identifiers['klassen_ids']);
                        } else {
                            $programMatch->whereIn($pivotAlias.'.klassen_id', $identifiers['klassen_ids']);
                            $added = true;
                        }

                        if ($courseTable) {
                            $programMatch->orWhereIn($courseTable.'.klassen_id', $identifiers['klassen_ids']);
                        }
                    }
                });
            };

            if ($hasParticipantId) {
                $current->orWhere($legacyProgramMatch);
            } else {
                $current->where($legacyProgramMatch);
            }
        });
    }

    protected static function programDataForContract(array $programData, ?array $contract): array
    {
        if (empty($programData) || ! $contract) {
            return $programData;
        }

        foreach (['teilnehmer_id', 'teilnehmer_nr'] as $identifier) {
            $contractIdentifier = self::firstFilled([$contract[$identifier] ?? null]);
            $programIdentifier = self::firstFilled([$programData[$identifier] ?? null]);

            if (
                $contractIdentifier !== null
                && $programIdentifier !== null
                && $contractIdentifier !== $programIdentifier
            ) {
                return [];
            }
        }

        return $programData;
    }

    protected static function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected static function cleanIdentifierList(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => trim((string) ($value ?? '')))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
