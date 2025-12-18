<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEvent;
use App\Services\Lenex\LenexImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Random\RandomException;
use RuntimeException;
use SimpleXMLElement;

readonly class LenexPreviewSupport
{
    public function __construct(
        private LenexImportService $lenex,
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
        $swimtimeStr = trim((string) ($resNode['swimtime'] ?? $resNode['SWIMTIME'] ?? ''));

        if ($swimtimeStr === '' && isset($resNode->SWIMTIME)) {
            $swimtimeStr = trim((string) $resNode->SWIMTIME);
        }

        $invalidReasons = [];

        /** @var ParaEvent|null $event */
        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;

        $this->addMissingEvent($invalidReasons, $event, $lenexEventId);
        if ($dbClub !== null) {
            $this->addMissingClub($invalidReasons, $dbClub);
        }

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

    public function findAthleteByName(?string $first, ?string $last, ?string $birthdate = null): ?ParaAthlete
    {
        $first = trim((string) $first);
        $last = trim((string) $last);

        if ($first === '' || $last === '') {
            return null;
        }

        $q = ParaAthlete::query()
            ->with('club')
            ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
            ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)]);

        $birthdate = trim((string) $birthdate);
        if ($birthdate !== '') {
            $q->whereDate('birthdate', $birthdate);
        }

        return $q->first();
    }

    /**
     * Liefert z.B. "S14", "SB6", "SM10" oder null.
     * DB first, fallback auf LENEX ATHLETE/HANDICAP (free/breast/medley).
     */
    public function athleteSportClassLabelForStroke(
        ?ParaAthlete $athlete,
        ?SimpleXMLElement $lenexAthNode,
        string $strokeCode
    ): ?string {
        $prefix = $this->strokeToClassPrefix($strokeCode);

        // 1) DB: sportclass_s / sportclass_sb / sportclass_sm
        $dbNum = null;
        if ($athlete) {
            $field = match ($prefix) {
                'SB' => ['sportclass_sb', 'Sportclass_sb'],
                'SM' => ['sportclass_sm', 'Sportclass_sm'],
                default => ['sportclass_s', 'Sportclass_s'],
            };

            foreach ($field as $f) {
                $val = $athlete->{$f} ?? null;
                if ($val !== null && $val !== '' && ctype_digit((string) $val)) {
                    $dbNum = (int) $val;
                    break;
                }
            }
        }

        if ($dbNum !== null) {
            return $prefix.$dbNum;
        }

        // 2) LENEX fallback: ATHLETE/HANDICAP free|breast|medley
        if ($lenexAthNode && ($lenexAthNode->HANDICAP ?? null) instanceof SimpleXMLElement) {
            $hc = $lenexAthNode->HANDICAP;

            $attr = match ($prefix) {
                'SB' => 'breast',
                'SM' => 'medley',
                default => 'free',
            };

            $val = trim((string) ($hc[$attr] ?? ''));
            if ($val !== '' && ctype_digit($val)) {
                return $prefix.((int) $val);
            }
        }

        return null;
    }

    public function strokeToClassPrefix(string $strokeCode): string
    {
        $strokeCode = strtoupper(trim($strokeCode));

        if (in_array($strokeCode, ['BREAST', 'BREASTSTROKE'], true)) {
            return 'SB';
        }

        if (in_array($strokeCode, ['MEDLEY', 'IM'], true)) {
            return 'SM';
        }

        // FREE, BACK, FLY -> S
        return 'S';
    }

    /**
     * @throws RandomException
     */
    public function storeUploadedLenex(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'lxf');
        $name = 'lenex_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;

        $relativePath = $file->storeAs('tmp/lenex', $name, 'local');

        if (!$relativePath || !Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException('Upload konnte nicht in storage/app/tmp/lenex gespeichert werden.');
        }

        return $relativePath;
    }
}
