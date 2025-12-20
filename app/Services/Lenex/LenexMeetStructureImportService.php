<?php

namespace App\Services\Lenex;

use App\Models\ParaEvent;
use App\Models\ParaEventAgegroup;
use App\Models\ParaMeet;
use App\Models\ParaSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexMeetStructureImportService
{
    public function __construct(
        private readonly LenexImportService $lenex,
        private readonly SwimstyleResolver $swimstyleResolver,
        private readonly NationResolver $nationResolver,
    ) {
    }

    /**
     * Importiert Struktur in ein EXISTIERENDES Meet (Wizard).
     * Wir validieren über meet_hash (gleiches Konzept wie LenexImportService::importMeetStructure()).
     */
    public function ensureMeetAndStructureForMeet(
        SimpleXMLElement $root,
        ParaMeet $meet,
        bool $upsertStructure = true
    ): ParaMeet {
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden.');
        }

        [$fromDate, $toDate] = $this->extractFromToDates($meetNode);
        $expectedHash = $this->buildMeetHash($meetNode, $fromDate, $toDate);

        if (!empty($meet->meet_hash) && $meet->meet_hash !== $expectedHash) {
            throw new RuntimeException('Dieses LENEX gehört zu einem anderen Meeting (meet_hash mismatch).');
        }

        if (empty($meet->meet_hash)) {
            $meet->meet_hash = $expectedHash;
        }

        // optional: original meetid speichern (falls vorhanden) – ohne harte Validierung
        $meetId = trim((string) ($meetNode['meetid'] ?? $meetNode['id'] ?? ''));
        if ($meetId !== '' && empty($meet->lenex_meet_key)) {
            $meet->lenex_meet_key = $meetId;
        }

        if ($upsertStructure) {
            $this->importStructureIntoMeet($root, $meet);
        } else {
            $meet->save();
        }

        return $meet->fresh();
    }

    private function extractFromToDates(SimpleXMLElement $meetNode): array
    {
        $dates = [];
        foreach (($meetNode->SESSIONS->SESSION ?? []) as $s) {
            $d = $this->parseDate((string) ($s['date'] ?? null));
            if ($d) {
                $dates[] = $d;
            }
        }
        sort($dates);
        $from = $dates[0] ?? null;
        $to = $dates ? end($dates) : null;
        return [$from, $to];
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function buildMeetHash(SimpleXMLElement $meetNode, ?string $fromDate, ?string $toDate): string
    {
        $name = (string) ($meetNode['name'] ?? '');
        $city = (string) ($meetNode['city'] ?? '');
        return sha1($name.'|'.$city.'|'.$fromDate.'|'.$toDate);
    }

    private function importStructureIntoMeet(SimpleXMLElement $root, ParaMeet $meet): void
    {
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden.');
        }

        DB::transaction(function () use ($root, $meetNode, $meet) {

            [$fromDate, $toDate] = $this->extractFromToDates($meetNode);

            $nation = $this->nationResolver->fromLenexCode((string) ($meetNode['nation'] ?? ''));

            $meet->fill([
                'name' => (string) ($meetNode['name'] ?? $meet->name),
                'city' => (string) ($meetNode['city'] ?? $meet->city),
                'nation_id' => $nation?->id,
                'from_date' => $fromDate,
                'to_date' => $toDate,

                'entry_start_date' => $this->parseDate((string) ($meetNode['entrystartdate'] ?? null)),
                'entry_deadline' => $this->parseDate((string) ($meetNode['deadline'] ?? null)),
                'withdraw_until' => $this->parseDate((string) ($meetNode['withdrawuntil'] ?? null)),
                'entry_type' => (string) ($meetNode['entrytype'] ?? null),
                'course' => (string) ($meetNode['course'] ?? null),
                'host_club' => (string) ($meetNode['hostclub'] ?? null),
                'organizer' => (string) ($meetNode['organizer'] ?? null),
                'organizer_url' => (string) ($meetNode['organizer.url'] ?? null),
                'result_url' => (string) ($meetNode['result.url'] ?? null),

                'lenex_revisiondate' => $this->parseDate((string) ($root['revisiondate'] ?? null)),
                'lenex_created' => !empty($root['created']) ? Carbon::parse((string) $root['created']) : null,
            ]);
            $meet->save();

            foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
                /** @var SimpleXMLElement $sessionNode */
                $sessionNo = (int) ($sessionNode['number'] ?? 0);

                $session = ParaSession::updateOrCreate(
                    [
                        'para_meet_id' => $meet->id,
                        'number' => $sessionNo > 0 ? $sessionNo : null,
                    ],
                    [
                        'name' => (string) ($sessionNode['name'] ?? null),
                        'date' => $this->parseDate((string) ($sessionNode['date'] ?? null)),
                        'start_time' => $this->parseTime((string) ($sessionNode['daytime'] ?? null)),

                        'warmup_from' => $this->parseTime((string) ($sessionNode['warmupfrom'] ?? null)),
                        'warmup_until' => $this->parseTime((string) ($sessionNode['warmupuntil'] ?? null)),
                        'official_meeting' => $this->parseTime((string) ($sessionNode['officialmeeting'] ?? null)),
                        'teamleader_meeting' => $this->parseTime((string) ($sessionNode['teamleadermeeting'] ?? null)),
                    ]
                );

                foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                    /** @var SimpleXMLElement $eventNode */
                    $lenexEventId = trim((string) ($eventNode['eventid'] ?? ''));
                    $swimstyleNode = $eventNode->SWIMSTYLE ?? null;

                    $swimstyle = $this->swimstyleResolver->resolveFromLenex(
                        $swimstyleNode instanceof SimpleXMLElement ? $swimstyleNode : null
                    );

                    $event = ParaEvent::updateOrCreate(
                        [
                            'para_session_id' => $session->id,
                            'lenex_eventid' => $lenexEventId !== '' ? $lenexEventId : null,
                        ],
                        [
                            'number' => (int) ($eventNode['number'] ?? 0) ?: null,
                            'order' => (int) ($eventNode['order'] ?? 0) ?: null,
                            'round' => (string) ($eventNode['round'] ?? null),
                            'swimstyle_id' => $swimstyle?->id,
                        ]
                    );

                    // Agegroups: upsert (nicht "delete all", damit Wizard wiederholbar bleibt)
                    foreach (($eventNode->AGEGROUPS->AGEGROUP ?? []) as $ageNode) {
                        /** @var SimpleXMLElement $ageNode */
                        $lenexAgId = trim((string) ($ageNode['agegroupid'] ?? ''));

                        ParaEventAgegroup::updateOrCreate(
                            [
                                'para_event_id' => $event->id,
                                'lenex_agegroupid' => $lenexAgId !== '' ? $lenexAgId : null,
                                'name' => (string) ($ageNode['name'] ?? ''),
                            ],
                            [
                                'gender' => (string) ($ageNode['gender'] ?? null),
                                'age_min' => $this->parseIntOrNull($ageNode['agemin'] ?? null),
                                'age_max' => $this->parseIntOrNull($ageNode['agemax'] ?? null),
                                'handicap_raw' => (string) ($ageNode['handicap'] ?? null),
                            ]
                        );
                    }
                }
            }
        });
    }

    private function parseTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // akzeptiere "HH:MM" oder "HH:MM:SS"
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            return $value;
        }
        return null;
    }

    private function parseIntOrNull($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (int) $v;
    }
}
