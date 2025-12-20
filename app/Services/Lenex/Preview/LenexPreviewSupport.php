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

        if (!$event) {
            $invalidReasons[] = "Event {$lenexEventId} nicht im Meeting vorhanden";
        }

        return [
            'resultId' => $resultId,
            'lenexEventId' => $lenexEventId,
            'swimtimeStr' => $swimtimeStr,
            'invalidReasons' => $invalidReasons,
            'event' => $event,
        ];
    }

    public function parseTimeToMs(?string $time): ?int
    {
        return $this->lenex->parseTimeToMs($time);
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

    /**
     * Numeric part for relay point rules (S20/S34/S49).
     */
    public function athleteSportClassNumberForStroke(
        ?ParaAthlete $athlete,
        ?SimpleXMLElement $lenexAthNode,
        string $strokeCode
    ): ?int {
        $label = $this->athleteSportClassLabelForStroke($athlete, $lenexAthNode, $strokeCode);
        if (!$label) {
            return null;
        }
        if (preg_match('/(\d+)/', $label, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Returns label like "S8"/"SB7"/"SM10" or null.
     * DB first (sportclass_s/sb/sm), fallback to LENEX HANDICAP (numeric).
     */
    public function athleteSportClassLabelForStroke(
        ?ParaAthlete $athlete,
        ?SimpleXMLElement $lenexAthNode,
        string $strokeCode
    ): ?string {
        $prefix = $this->strokeToClassPrefix($strokeCode);

        // 1) DB fields (your migration uses sportclass_s/sb/sm as strings, often already "S8")
        $dbVal = null;

        if ($athlete) {
            $field = match ($prefix) {
                'SB' => ['sportclass_sb'],
                'SM' => ['sportclass_sm'],
                default => ['sportclass_s'],
            };

            foreach ($field as $f) {
                $val = trim((string) ($athlete->{$f} ?? ''));
                if ($val !== '') {
                    $dbVal = $val;
                    break;
                }
            }
        }

        if ($dbVal) {
            // if stored as "7" => prefix it
            if (ctype_digit($dbVal)) {
                return $prefix.(int) $dbVal;
            }
            // if stored as "SB7"/"S8"/"SM10" => return as-is
            return $dbVal;
        }

        // 2) LENEX fallback (numeric)
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
        return 'S'; // FREE/BACK/FLY
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
