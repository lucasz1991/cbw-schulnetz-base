<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ParticipantContractAccess
{
    public const DEFAULT_OPEN_BEFORE_DAYS = 14;

    public const DEFAULT_CLOSE_AFTER_DAYS = 7;

    /**
     * Read the values directly from the shared database. Admin and Base use
     * separate file caches, so a cached read here could retain stale values.
     *
     * @return array{open_before_days: int, close_after_days: int}
     */
    public static function configuredDays(): array
    {
        return [
            'open_before_days' => max(0, (int) (Setting::getValueUncached(
                'course_registration',
                'open_before_start_days'
            ) ?? self::DEFAULT_OPEN_BEFORE_DAYS)),
            'close_after_days' => max(0, (int) (Setting::getValueUncached(
                'course_registration',
                'close_after_end_days'
            ) ?? self::DEFAULT_CLOSE_AFTER_DAYS)),
        ];
    }

    /**
     * Select the contract whose data should currently be visible in the
     * participant portal.
     *
     * Access windows only decide which contracts are eligible. Contracts that
     * have really begun take precedence over pre-opened future contracts; of
     * those, the contract with the latest start wins. This prevents an older
     * grace period from hiding a newer contract that has already begun.
     */
    public static function currentContract(
        array|Collection $contracts,
        int $openBeforeDays,
        int $closeAfterDays,
        ?Carbon $today = null
    ): ?array {
        $today = ($today ?? Carbon::today('Europe/Berlin'))->copy()->startOfDay();

        $candidates = collect($contracts)
            ->filter(fn ($contract) => is_array($contract))
            ->map(function (array $contract) use ($openBeforeDays, $closeAfterDays) {
                $window = self::windowForContract($contract, $openBeforeDays, $closeAfterDays);

                return $window ? ['contract' => $contract, ...$window] : null;
            })
            ->filter()
            ->values();

        $accessible = $candidates
            ->filter(fn (array $candidate) => $today->gte($candidate['access_from'])
                && $today->lte($candidate['access_until']))
            ->values();

        $selectedAccessible = $accessible
            ->sort(function (array $left, array $right) use ($today) {
                $leftHasStarted = $today->gte($left['start']);
                $rightHasStarted = $today->gte($right['start']);

                if ($leftHasStarted !== $rightHasStarted) {
                    return $leftHasStarted ? -1 : 1;
                }

                $start = $leftHasStarted
                    ? $right['start']->timestamp <=> $left['start']->timestamp
                    : $left['start']->timestamp <=> $right['start']->timestamp;

                if ($start !== 0) {
                    return $start;
                }

                $priority = self::selectionPriority($right['contract'])
                    <=> self::selectionPriority($left['contract']);

                if ($priority !== 0) {
                    return $priority;
                }

                return strcmp(self::identifier($left['contract']), self::identifier($right['contract']));
            })
            ->first();

        if ($selectedAccessible) {
            return $selectedAccessible['contract'];
        }

        $incompletePeriod = collect($contracts)
            ->filter(fn ($contract) => is_array($contract))
            ->filter(function (array $contract) use ($today, $openBeforeDays, $closeAfterDays) {
                $start = self::parseDate($contract['vertrag_beginn'] ?? $contract['beginn'] ?? null);
                $end = self::effectiveEnd($contract);

                if (! $start && $end) {
                    return $today->lte($end->copy()->addDays(max(0, $closeAfterDays))->endOfDay())
                        && (self::selectionPriority($contract) > 0
                            || filter_var($contract['is_active'] ?? false, FILTER_VALIDATE_BOOL));
                }

                if ($start && ! $end) {
                    return $today->gte($start->copy()->subDays(max(0, $openBeforeDays))->startOfDay())
                        && (self::selectionPriority($contract) > 0
                            || filter_var($contract['is_active'] ?? false, FILTER_VALIDATE_BOOL));
                }

                return false;
            })
            ->sort(function (array $left, array $right) {
                $priority = self::selectionPriority($right) <=> self::selectionPriority($left);

                if ($priority !== 0) {
                    return $priority;
                }

                $leftEnd = self::effectiveEnd($left)?->timestamp ?? PHP_INT_MAX;
                $rightEnd = self::effectiveEnd($right)?->timestamp ?? PHP_INT_MAX;

                return $leftEnd <=> $rightEnd;
            })
            ->first();

        if ($incompletePeriod) {
            return $incompletePeriod;
        }

        return collect($contracts)
            ->filter(fn ($contract) => is_array($contract))
            ->filter(fn (array $contract) => self::periodForContract($contract) === null)
            ->sort(function (array $left, array $right) {
                $priority = self::selectionPriority($right) <=> self::selectionPriority($left);

                if ($priority !== 0) {
                    return $priority;
                }

                return strcmp(self::identifier($left), self::identifier($right));
            })
            ->first();
    }

    /** @return array{start: Carbon, end: Carbon, access_from: Carbon, access_until: Carbon}|null */
    public static function windowForContract(
        array $contract,
        int $openBeforeDays,
        int $closeAfterDays
    ): ?array {
        $period = self::periodForContract($contract);

        if (! $period) {
            return null;
        }

        return [
            ...$period,
            'access_from' => $period['start']->copy()->subDays(max(0, $openBeforeDays))->startOfDay(),
            'access_until' => $period['end']->copy()->addDays(max(0, $closeAfterDays))->endOfDay(),
        ];
    }

    /** @return array{start: Carbon, end: Carbon}|null */
    public static function periodForContract(array $contract): ?array
    {
        $start = self::parseDate($contract['vertrag_beginn'] ?? $contract['beginn'] ?? null);
        $end = self::effectiveEnd($contract);

        if (! $start && $end) {
            $start = $end->copy();
        }

        if (! $end && $start) {
            $end = $start->copy();
        }

        return ($start && $end) ? compact('start', 'end') : null;
    }

    public static function effectiveEnd(array $contract): ?Carbon
    {
        $contractEnd = self::parseDate($contract['vertrag_ende'] ?? $contract['letzter_tag'] ?? null);
        $cancelledAt = self::parseDate($contract['kuendig_zum'] ?? null);

        if ($contractEnd && $cancelledAt) {
            return $contractEnd->lte($cancelledAt) ? $contractEnd : $cancelledAt;
        }

        return $contractEnd ?? $cancelledAt;
    }

    public static function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y/m/d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, 'Europe/Berlin')->startOfDay();
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        try {
            return Carbon::parse(str_replace('/', '-', $raw), 'Europe/Berlin')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function selectionPriority(array $contract): int
    {
        if (filter_var($contract['is_current'] ?? false, FILTER_VALIDATE_BOOL)) {
            return 2;
        }

        return filter_var($contract['is_selected'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0;
    }

    private static function identifier(array $contract): string
    {
        return trim((string) ($contract['teilnehmer_id'] ?? $contract['teilnehmer_nr'] ?? ''));
    }
}
