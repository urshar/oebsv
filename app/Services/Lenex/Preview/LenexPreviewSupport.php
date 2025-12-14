<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEvent;
use App\Services\Lenex\LenexImportService;
use SimpleXMLElement;

class LenexPreviewSupport
{
    public function __construct(
        private readonly LenexImportService $lenex,
    ) {
    }

    /**
     * Einheitlicher "Result Context" für Athlete-Results und Relay-Results.
     * Entfernt Duplicate-Code: resultid/eventid/swimtime lesen + event lookup + initiale warnings.
     */
    public function initResultContext(
        SimpleXMLElement $resNode,
        array $eventByLenexId,
        ?ParaClub $dbClub = null
    ): array {
        $resultId = (string) ($resNode['resultid'] ?? '');
        $lenexEventId = (string) ($resNode['eventid'] ?? '');
        $swimtimeStr = trim((string) ($resNode['swimtime'] ?? ''));

        $invalidReasons = [];

        /** @var ParaEvent|null $event */
        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;

        $this->addMissingEvent($invalidReasons, $event, $lenexEventId);
        $this->addMissingClub($invalidReasons, $dbClub);

        return [
            'resultId' => $resultId,
            'lenexEventId' => $lenexEventId,
            'swimtimeStr' => $swimtimeStr,
            'invalidReasons' => $invalidReasons,
            'event' => $event,
        ];
    }

    public function addMissingEvent(array &$reasons, ?ParaEvent $event, string $lenexEventId): void
    {
        if (!$event) {
            $reasons[] = "Event {$lenexEventId} nicht im Meeting vorhanden";
        }
    }

    public function addMissingClub(array &$reasons, ?ParaClub $club): void
    {
        if (!$club) {
            $reasons[] = 'Verein im System nicht gefunden (para_clubs)';
        }
    }

    public function athleteInClub(?ParaAthlete $athlete, ?ParaClub $club): bool
    {
        if (!$athlete || !$club) {
            return true;
        }
        if (!$athlete->para_club_id || !$club->id) {
            return true;
        }

        return (int) $athlete->para_club_id === (int) $club->id;
    }

    public function addAthleteDbReasons(
        array &$reasons,
        ?ParaAthlete $athlete,
        bool $inClub,
        string $first,
        string $last,
        string $lenexAthleteId,
        string $clubName
    ): void {
        if (!$athlete) {
            $reasons[] = "Athlet {$last}, {$first} ({$lenexAthleteId}) nicht in para_athletes gefunden";
            return;
        }

        if (!$inClub) {
            $reasons[] = "Athlet {$last}, {$first} gehört nicht zu Verein {$clubName}";
        }
    }

    public function parseTimeToMs(?string $time): ?int
    {
        return $this->lenex->parseTimeToMs($time);
    }
}
