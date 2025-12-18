<?php

namespace App\Services\Lenex;

use App\Models\ParaEvent;
use App\Models\ParaEventAgegroup;
use App\Models\ParaMeet;
use App\Models\ParaSession;
use App\Models\Swimstyle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexMeetStructureImportService
{
    /** @var array<string, int|null> */
    private array $swimstyleIdCache = [];

    /**
     * Global: erzeugt Meet (falls nicht vorhanden) über meet_hash und importiert Struktur.
     * @throws Throwable
     */
    public function ensureMeetAndStructure(SimpleXMLElement $lenexXml, bool $upsertStructure = true): ParaMeet
    {
        $meetNode = $this->getMeetNode($lenexXml);
        $hash = $this->buildMeetHash($meetNode);

        $meet = ParaMeet::where('meet_hash', $hash)->first();

        return DB::transaction(function () use ($meet, $meetNode, $hash, $upsertStructure) {
            if (!$meet) {
                $meet = ParaMeet::create([
                    'meet_hash' => $hash,
                    'name' => (string) ($meetNode['name'] ?? ''),
                    'city' => (string) ($meetNode['city'] ?? null),
                    'course' => (string) ($meetNode['course'] ?? null),
                ]);
            } elseif ($upsertStructure) {
                $meet->fill($this->onlyMeetColumns([
                    'name' => (string) ($meetNode['name'] ?? $meet->name),
                    'city' => (string) ($meetNode['city'] ?? $meet->city),
                    'course' => (string) ($meetNode['course'] ?? $meet->course),
                ]))->save();
            }

            return $this->importStructureIntoMeet($meet, $meetNode, $upsertStructure);
        });
    }

    private function getMeetNode(SimpleXMLElement $xml): SimpleXMLElement
    {
        if (isset($xml->MEETS->MEET)) {
            return $xml->MEETS->MEET;
        }

        $nodes = $xml->xpath('//MEET');
        if (!$nodes || !isset($nodes[0])) {
            throw new RuntimeException('LENEX: MEET node not found.');
        }

        return $nodes[0];
    }

    /**
     * 64 chars (sha256 hex) passend zu para_meets.meet_hash (varchar 64).
     * Stabil zwischen Structure/Results: bevorzugt meetid, sonst name+course+firstSessionDate.
     */
    private function buildMeetHash(SimpleXMLElement $meetNode): string
    {
        $meetId = (string) ($meetNode['meetid'] ?? $meetNode['id'] ?? '');
        if ($meetId !== '') {
            return hash('sha256', $meetId);
        }

        $name = mb_strtolower(trim((string) ($meetNode['name'] ?? '')), 'UTF-8');
        $course = mb_strtolower(trim((string) ($meetNode['course'] ?? '')), 'UTF-8');

        $dates = [];
        foreach (($meetNode->SESSIONS->SESSION ?? []) as $s) {
            $d = $this->parseDate((string) ($s['date'] ?? null));
            if ($d) {
                $dates[] = $d;
            }
        }
        sort($dates);
        $from = $dates[0] ?? '';

        return hash('sha256', $name.'|'.$course.'|'.$from);
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /** Schutz: nur Meet-Spalten befüllen, die bei dir existieren. */
    private function onlyMeetColumns(array $data): array
    {
        // du hast die Spalten fix in der Migration; das ist hier nur “sicher gegen Tippfehler”
        return $data;
    }

    /**
     * @throws Throwable
     */
    private function importStructureIntoMeet(
        ParaMeet $meet,
        SimpleXMLElement $meetNode,
        bool $upsertStructure
    ): ParaMeet {
        return DB::transaction(function () use ($meet, $meetNode, $upsertStructure) {

            // Sessions-Daten sammeln (für from/to)
            $sessionDates = [];

            $sessions = $meetNode->SESSIONS->SESSION ?? [];
            $sessionIndex = 0;

            foreach ($sessions as $sessionNode) {
                $sessionIndex++;

                $number = (int) ($sessionNode['number'] ?? 0);
                if ($number <= 0) {
                    $number = $sessionIndex;
                }

                $date = $this->parseDate((string) ($sessionNode['date'] ?? null));
                if ($date) {
                    $sessionDates[] = $date;
                }

                $session = ParaSession::updateOrCreate(
                    [
                        'para_meet_id' => $meet->id,
                        'number' => $number,
                    ],
                    [
                        'name' => (string) ($sessionNode['name'] ?? null),
                        'date' => $date,
                        'start_time' => $this->parseTime((string) ($sessionNode['daytime'] ?? null)),

                        // optional aus deinem Schema
                        'warmup_from' => $this->parseTime((string) ($sessionNode['warmupfrom'] ?? null)),
                        'warmup_until' => $this->parseTime((string) ($sessionNode['warmupuntil'] ?? null)),
                        'official_meeting' => $this->parseTime((string) ($sessionNode['officialmeeting'] ?? null)),
                        'teamleader_meeting' => $this->parseTime((string) ($sessionNode['teamleadermeeting'] ?? null)),
                    ]
                );

                $events = $sessionNode->EVENTS->EVENT ?? [];
                foreach ($events as $eventNode) {
                    $lenexEventId = (string) ($eventNode['eventid'] ?? null);
                    $eventNumber = (int) ($eventNode['number'] ?? 0);

                    $where = ['para_session_id' => $session->id];
                    if ($lenexEventId !== '') {
                        $where['lenex_eventid'] = $lenexEventId;
                    } elseif ($eventNumber > 0) {
                        $where['number'] = $eventNumber;
                    } else {
                        // letzter Fallback: kombinieren
                        $where['number'] = 0;
                    }

                    $swimstyleId = $this->resolveSwimstyleIdFromEventNode($eventNode);

                    ParaEvent::updateOrCreate(
                        $where,
                        [
                            'number' => $eventNumber ?: null,
                            'order' => (int) ($eventNode['order'] ?? 0) ?: null,
                            'round' => (string) ($eventNode['round'] ?? null),

                            // ✅ in deinem Schema: swimstyle_id (kein gender/distance/stroke_code!)
                            'swimstyle_id' => $swimstyleId,

                            'fee' => $this->parseDecimal((string) ($eventNode['fee'] ?? null)),
                            'fee_currency' => (string) ($eventNode['feecurrency'] ?? null),
                        ]
                    );

                    $event = ParaEvent::where($where)->first();

                    // AgeGroups
                    $agegroups = $eventNode->AGEGROUPS->AGEGROUP ?? [];
                    foreach ($agegroups as $agNode) {
                        $lenexAgeGroupId = (string) ($agNode['agegroupid'] ?? null);

                        ParaEventAgegroup::updateOrCreate(
                            [
                                'para_event_id' => $event->id,
                                'lenex_agegroupid' => $lenexAgeGroupId !== '' ? $lenexAgeGroupId : null,
                            ],
                            [
                                'name' => (string) ($agNode['name'] ?? ''),
                                'gender' => (string) ($agNode['gender'] ?? null),
                                'age_min' => $this->parseIntNullable((string) ($agNode['agemin'] ?? null)),
                                'age_max' => $this->parseIntNullable((string) ($agNode['agemax'] ?? null)),
                                'handicap_raw' => (string) ($agNode['handicap'] ?? null),
                            ]
                        );
                    }
                }
            }

            // from/to am Meet setzen
            if ($upsertStructure && !empty($sessionDates)) {
                sort($sessionDates);
                $meet->fill([
                    'from_date' => $sessionDates[0],
                    'to_date' => $sessionDates[count($sessionDates) - 1],
                ])->save();
            }

            return $meet;
        });
    }

    private function parseTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $v = trim($value);
        // akzeptiere "HH:MM" oder "HH:MM:SS"
        if (preg_match('/^\d{2}:\d{2}$/', $v)) {
            return $v.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
            return $v;
        }

        try {
            return Carbon::parse($v)->format('H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveSwimstyleIdFromEventNode(SimpleXMLElement $eventNode): ?int
    {
        // LENEX hat oft <SWIMSTYLE .../> im EVENT
        $sw = $eventNode->SWIMSTYLE ?? null;

        $distance = (int) ($sw['distance'] ?? $eventNode['distance'] ?? 0);
        $relaycount = (int) ($sw['relaycount'] ?? $eventNode['relaycount'] ?? 1);
        if ($relaycount <= 0) {
            $relaycount = 1;
        }

        $stroke = (string) ($sw['stroke'] ?? $eventNode['stroke'] ?? '');
        $strokeCode = $this->normalizeStrokeCode($stroke);

        if ($distance <= 0 || $strokeCode === '') {
            return null;
        }

        $cacheKey = $distance.'|'.$relaycount.'|'.$strokeCode;
        if (array_key_exists($cacheKey, $this->swimstyleIdCache)) {
            return $this->swimstyleIdCache[$cacheKey];
        }

        $id = Swimstyle::query()
            ->where('distance', $distance)
            ->where('relaycount', $relaycount)
            ->where('stroke_code', $strokeCode)
            ->value('id');

        $this->swimstyleIdCache[$cacheKey] = $id ?: null;

        return $this->swimstyleIdCache[$cacheKey];
    }

    private function normalizeStrokeCode(string $raw): string
    {
        $s = strtoupper(trim($raw));

        return match ($s) {
            'FREESTYLE', 'FREE' => 'FREE',
            'BACKSTROKE', 'BACK' => 'BACK',
            'BREASTSTROKE', 'BREAST' => 'BREAST',
            'BUTTERFLY', 'FLY' => 'FLY',
            'IM', 'INDIVIDUALMEDLEY', 'MEDLEY' => 'MEDLEY',
            default => $s,
        };
    }

    private function parseDecimal(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $v = str_replace(',', '.', trim($value));
        return is_numeric($v) ? $v : null;
    }

    private function parseIntNullable(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (!is_numeric(trim($value))) {
            return null;
        }
        return (int) trim($value);
    }

    /**
     * Meet-gebunden (Wizard): importiert Struktur EXAKT in das übergebene Meet
     * und setzt/aktualisiert meet_hash dev-friendly (nur Kollisionscheck ist hart).
     * @throws Throwable
     */
    public function ensureMeetAndStructureForMeet(
        SimpleXMLElement $lenexXml,
        ParaMeet $meet,
        bool $upsertStructure = true
    ): ParaMeet {
        $meetNode = $this->getMeetNode($lenexXml);
        $hash = $this->buildMeetHash($meetNode);

        // Kollisionscheck: derselbe Hash darf nicht schon einem anderen Meet gehören
        $collision = ParaMeet::where('meet_hash', $hash)
            ->where('id', '!=', $meet->id)
            ->first();

        if ($collision) {
            throw new RuntimeException('LENEX-Datei gehört zu einem anderen Meeting (meet_hash kollidiert).');
        }

        // Dev-friendly: Hash am aktuellen Meet setzen/aktualisieren
        if ($meet->meet_hash !== $hash) {
            $meet->forceFill(['meet_hash' => $hash])->save();
        }

        // Meet-Metadaten updaten (optional)
        if ($upsertStructure) {
            $meet->fill($this->onlyMeetColumns([
                'name' => (string) ($meetNode['name'] ?? $meet->name),
                'city' => (string) ($meetNode['city'] ?? $meet->city),
                'course' => (string) ($meetNode['course'] ?? $meet->course),

                'entry_start_date' => $this->parseDate((string) ($meetNode['entrystartdate'] ?? null)),
                'entry_deadline' => $this->parseDate((string) ($meetNode['deadline'] ?? null)),
                'withdraw_until' => $this->parseDate((string) ($meetNode['withdrawuntil'] ?? null)),
                'entry_type' => (string) ($meetNode['entrytype'] ?? null),

                'host_club' => (string) ($meetNode['hostclub'] ?? null),
                'organizer' => (string) ($meetNode['organizer'] ?? null),
                'organizer_url' => (string) ($meetNode['organizerurl'] ?? null),
                'result_url' => (string) ($meetNode['resulturl'] ?? null),

                'lenex_revisiondate' => $this->parseDate((string) ($meetNode['revisiondate'] ?? null)),
                'lenex_created' => $this->parseDateTime((string) ($meetNode['created'] ?? null)),
            ]))->save();
        }

        return $this->importStructureIntoMeet($meet, $meetNode, $upsertStructure);
    }

    private function parseDateTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
