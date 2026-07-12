<?php

namespace App\Services\ReportBook;

use Illuminate\Support\Carbon;

/**
 * Fortlaufende Nachweis-Nummerierung des Berichtshefts aus dem Qualiprogramm
 * (persons.programdata).
 *
 * Berichtszeitraeume sind ISO-Kalenderwochen, die von Bausteinen mit
 * Klassenzuordnung (klassen_id) ODER von Ferien-Bausteinen (kurzbez 'FERI')
 * belegt werden. Die Wochen werden chronologisch sortiert und lueckenlos
 * 1-basiert durchnummeriert; Ferienwochen zaehlen als regulaere
 * Berichtszeitraeume und verschieben damit die Nummern der Folgewochen
 * (Produktentscheidung, siehe MODEL_COORDINATION.md).
 *
 * Bewusst ohne DB-Zugriff gehalten, damit die Logik ohne Datenbank testbar ist.
 */
class ReportWeekTimeline
{
    /**
     * @param  array|null  $programdata  Inhalt von persons.programdata
     * @return array<string,int> Map ISO-Wochenschluessel ("2026-12") => Nachweis-Nr (1-basiert)
     */
    public static function weekMap(?array $programdata): array
    {
        $weeks = [];

        foreach ((array) data_get($programdata, 'tn_baust', []) as $block) {
            if (! is_array($block) || ! self::isReportBlock($block)) {
                continue;
            }

            $start = self::parseDate($block['beginn_baustein'] ?? null);
            $end   = self::parseDate($block['ende_baustein'] ?? null);

            if (! $start || ! $end || $end->lt($start)) {
                continue;
            }

            foreach (self::weekKeysForRange($start, $end) as $key) {
                $weeks[$key] = true;
            }
        }

        $keys = array_keys($weeks);
        sort($keys, SORT_STRING); // "JJJJ-WW" mit 0-Padding sortiert lexikografisch korrekt

        $map = [];
        foreach ($keys as $index => $key) {
            $map[$key] = $index + 1;
        }

        return $map;
    }

    /**
     * Nachweis-Nr fuer ein Datum, null wenn die Woche kein Berichtszeitraum ist.
     *
     * @param  array<string,int>  $weekMap
     */
    public static function numberForDate(Carbon $date, array $weekMap): ?int
    {
        return $weekMap[self::weekKey($date)] ?? null;
    }

    /** ISO-Wochenschluessel eines Datums, z. B. "2026-12". */
    public static function weekKey(Carbon $date): string
    {
        return $date->isoWeekYear() . '-' . str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Berichtsheft-relevante Bausteine: alles mit Klassenzuordnung (Kurse,
     * Pruefungswochen) sowie Ferienbausteine (kurzbez 'FERI', klassen_id ist
     * dort immer null). PRAK u. ae. ohne klassen_id bleiben aussen vor.
     */
    protected static function isReportBlock(array $block): bool
    {
        $klassenId = trim((string) ($block['klassen_id'] ?? ''));
        if ($klassenId !== '') {
            return true;
        }

        return mb_strtoupper(trim((string) ($block['kurzbez'] ?? ''))) === 'FERI';
    }

    /**
     * ISO-Wochen im Bereich [start, ende], die mindestens einen Werktag
     * (Mo-Fr) innerhalb des Bereichs enthalten.
     *
     * @return list<string>
     */
    protected static function weekKeysForRange(Carbon $start, Carbon $end): array
    {
        $keys = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            if ($cursor->isoWeekday() <= 5) {
                $keys[self::weekKey($cursor)] = true;
            }
            $cursor->addDay();
        }

        return array_keys($keys);
    }

    /** UVS liefert Datumswerte als "YYYY/MM/DD". */
    protected static function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $raw = str_replace('\\/', '/', $raw);

        try {
            return Carbon::createFromFormat('Y/m/d', $raw)->startOfDay();
        } catch (\Throwable) {
            // weiter mit generischem Parse
        }

        try {
            return Carbon::parse(str_replace('/', '-', $raw))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
