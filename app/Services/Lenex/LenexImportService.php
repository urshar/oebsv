<?php

namespace App\Services\Lenex;

use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaEvent;
use App\Models\ParaEventAgegroup;
use App\Models\ParaMeet;
use App\Models\ParaSession;
use App\Models\Subregion;
use App\Models\Swimstyle;
use App\Services\AgegroupResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class LenexImportService
{
    public function __construct(
        protected NationResolver $nationResolver,
        protected ClubResolver $clubResolver,
        protected AthleteResolver $athleteResolver,
        protected SwimstyleResolver $swimstyleResolver,
        protected AgegroupResolver $agegroupResolver,
    ) {
    }

    /**
     * Einstiegspunkt: Meeting-Struktur aus Datei importieren.
     * @throws Throwable
     */
    public function importMeetStructureFromPath(string $path): ParaMeet
    {
        $xmlString = $this->readLenexXml($path);
        $root = simplexml_load_string($xmlString);

        if (!$root instanceof SimpleXMLElement) {
            throw new RuntimeException('LENEX XML konnte nicht geparst werden.');
        }

        return DB::transaction(function () use ($root) {
            return $this->importMeetStructure($root);
        });
    }

    /**
     * XML aus .xml/.lef oder .lxf/.zip lesen.
     */
    public function readLenexXml(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException("Datei nicht gefunden: {$path}");
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Plain XML / LEF
        if (in_array($ext, ['xml', 'lef'], true)) {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException("Datei konnte nicht gelesen werden: {$path}");
            }
            return $content;
        }

        // LXF or ZIP → zip öffnen und erste .lef/.xml extrahieren
        if (in_array($ext, ['lxf', 'zip'], true)) {
            $zip = new ZipArchive();
            $res = $zip->open($path, ZipArchive::RDONLY);

            if ($res !== true) {
                // Fallback: manche .lxf sind in Wahrheit plain XML
                $raw = @file_get_contents($path);
                if ($raw !== false && stripos($raw, '<lenex') !== false) {
                    return $raw;
                }

                throw new RuntimeException(
                    "LXF/ZIP-Datei konnte nicht geöffnet werden (ZipArchive Code {$res}): {$path}"
                );
            }

            $xml = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!$name) {
                    continue;
                }

                $lowerName = strtolower($name);
                if (str_ends_with($lowerName, '.lef') || str_ends_with($lowerName, '.xml')) {
                    $xml = $zip->getFromIndex($i);
                    break;
                }
            }

            $zip->close();

            if ($xml === null) {
                throw new RuntimeException('Keine .lef/.xml in der LXF/ZIP-Datei gefunden.');
            }

            return $xml;
        }


        throw new RuntimeException("Nicht unterstützte LENEX-Erweiterung: {$ext}");
    }

    /**
     * Meeting-Struktur (MEET, SESSIONS, EVENTS, AGEGROUPS) importieren.
     */
    protected function importMeetStructure(SimpleXMLElement $root): ParaMeet
    {
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden.');
        }

        // Nation via IOC/ISO-Code aus @nation (z.B. AUT)
        $nation = $this->nationResolver->fromLenexCode((string) $meetNode['nation']);

        // Sessions durchlaufen, um from/to Datum zu bestimmen
        $dates = [];
        foreach ($meetNode->SESSIONS->SESSION ?? [] as $sessionNode) {
            if (!empty($sessionNode['date'])) {
                $dates[] = (string) $sessionNode['date'];
            }
        }
        sort($dates);
        $fromDate = $dates[0] ?? null;
        $toDate = $dates ? end($dates) : null;

        $name = (string) $meetNode['name'];
        $city = (string) $meetNode['city'];

        // Hash zur Dublettenvermeidung (gleiche Veranstaltung)
        $hash = sha1($name.'|'.$city.'|'.$fromDate.'|'.$toDate);

        $meet = ParaMeet::updateOrCreate(
            ['meet_hash' => $hash],
            [
                'name' => $name,
                'city' => $city,
                'nation_id' => $nation?->id,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'entry_start_date' => (string) ($meetNode['entrystartdate'] ?? null),
                'entry_deadline' => (string) ($meetNode['deadline'] ?? null),
                'withdraw_until' => (string) ($meetNode['withdrawuntil'] ?? null),
                'entry_type' => (string) ($meetNode['entrytype'] ?? null),
                'course' => (string) ($meetNode['course'] ?? null),
                'host_club' => (string) ($meetNode['hostclub'] ?? null),
                'organizer' => (string) ($meetNode['organizer'] ?? null),
                'organizer_url' => (string) ($meetNode['organizer.url'] ?? null),
                'result_url' => (string) ($meetNode['result.url'] ?? null),
                'lenex_revisiondate' => (string) ($root['revisiondate'] ?? null),
                'lenex_created' => (string) ($root['created'] ?? null),
            ]
        );

        // Sessions
        foreach ($meetNode->SESSIONS->SESSION ?? [] as $sessionNode) {
            $session = ParaSession::updateOrCreate(
                [
                    'para_meet_id' => $meet->id,
                    'number' => (int) ($sessionNode['number'] ?? 0),
                ],
                [
                    'date' => (string) ($sessionNode['date'] ?? null),
                    'start_time' => (string) ($sessionNode['daytime'] ?? null),
                    'warmup_from' => (string) ($sessionNode['warmupfrom'] ?? null),
                    'warmup_until' => (string) ($sessionNode['warmupuntil'] ?? null),
                    'official_meeting' => (string) ($sessionNode['officialmeeting'] ?? null),
                    'teamleader_meeting' => (string) ($sessionNode['teamleadermeeting'] ?? null),
                ]
            );

            // Events dieser Session
            foreach ($sessionNode->EVENTS->EVENT ?? [] as $eventNode) {
                $swimstyleNode = $eventNode->SWIMSTYLE ?? null;
                $feeNode = $eventNode->FEE ?? null;

                $swimstyleModel = $this->swimstyleResolver->resolveFromLenex(
                    $swimstyleNode instanceof SimpleXMLElement ? $swimstyleNode : null
                );

                // Fallback: falls Resolver nichts findet, direkt aus DB (swimstyles) matchen
                if (!$swimstyleModel && $swimstyleNode instanceof SimpleXMLElement) {
                    $distance = (int) ($swimstyleNode['distance'] ?? 0);
                    $relaycount = (int) ($swimstyleNode['relaycount'] ?? 1);
                    $strokeCode = strtoupper(trim((string) ($swimstyleNode['stroke'] ?? '')));

                    if ($relaycount <= 0) {
                        $relaycount = 1;
                    }

                    if ($distance > 0 && $strokeCode !== '') {
                        $swimstyleModel = Swimstyle::query()
                            ->where('distance', $distance)
                            ->where('relaycount', $relaycount)
                            ->where(function ($q) use ($strokeCode) {
                                $q->where('stroke_code', $strokeCode)
                                    ->orWhere('stroke', $strokeCode);
                            })
                            ->first();
                    }
                }

                $event = ParaEvent::updateOrCreate(
                    [
                        'para_session_id' => $session->id,
                        'lenex_eventid' => (string) ($eventNode['eventid'] ?? null),
                    ],
                    [
                        'number' => (int) ($eventNode['number'] ?? 0),
                        'order' => (int) ($eventNode['order'] ?? 0),
                        'round' => (string) ($eventNode['round'] ?? null),

                        'swimstyle_id' => $swimstyleModel?->id,

                        'fee' => $this->parseFeeValue($feeNode),
                        'fee_currency' => $feeNode ? (string) ($feeNode['currency'] ?? null) : null,
                    ]
                );

                // Agegroups für dieses Event: alte löschen, neu schreiben
                ParaEventAgegroup::where('para_event_id', $event->id)->delete();

                foreach ($eventNode->AGEGROUPS->AGEGROUP ?? [] as $ageNode) {
                    ParaEventAgegroup::create([
                        'para_event_id' => $event->id,
                        'lenex_agegroupid' => (string) ($ageNode['agegroupid'] ?? null),
                        'name' => (string) ($ageNode['name'] ?? ''),
                        'gender' => (string) ($ageNode['gender'] ?? null),
                        'age_min' => $this->parseIntOrNull($ageNode['agemin'] ?? null),
                        'age_max' => $this->parseIntOrNull($ageNode['agemax'] ?? null),
                        'handicap_raw' => (string) ($ageNode['handicap'] ?? null),
                    ]);
                }
            }
        }

        return $meet;
    }

    /**
     * FEE-Angabe aus LENEX holen.
     */
    protected function parseFeeValue(?SimpleXMLElement $feeNode): ?float
    {
        if (!$feeNode || !isset($feeNode['value'])) {
            return null;
        }

        // Wert bereits in Euro:
        return (float) $feeNode['value'];

        // Falls es Cent wären:
        // return ((float) $feeNode['value']) / 100;
    }

    /**
     * int oder null aus einem LENEX-Attribut.
     */
    protected function parseIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function loadLenexRootFromPath(string $path): SimpleXMLElement
    {
        $xmlString = $this->readLenexXml($path);
        $root = simplexml_load_string($xmlString);

        if (!$root instanceof SimpleXMLElement) {
            throw new RuntimeException('LENEX XML konnte nicht geparst werden.');
        }

        return $root;
    }

    /**
     * CLUBS / ATHLETES / ENTRIES importieren und mit bestehendem Meeting verknüpfen.
     */
    public function importEntriesFromPath(string $path, ParaMeet $meet): void
    {
        $xmlString = $this->readLenexXml($path);
        $root = simplexml_load_string($xmlString);

        if (!$root instanceof SimpleXMLElement) {
            throw new RuntimeException('LENEX XML konnte nicht geparst werden (Entries).');
        }

        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode) {
            throw new RuntimeException('Keine MEET-Definition im LENEX (Entries) gefunden.');
        }

        // AGEDATE-Knoten (Stichtag für Altersberechnung)
        $ageDateNode = $meetNode->AGEDATE[0] ?? null;
        $ageDateValue = $ageDateNode ? (string) ($ageDateNode['value'] ?? null) : null;

        // Sessions und Events des Meetings aus der DB laden
        $meet->load(['sessions.events.agegroups', 'sessions.events.swimstyle']);

        // 1) Session-Lookup (session_number -> ParaSession)
        $sessionByNumber = [];
        foreach ($meet->sessions as $sessionModel) {
            $sessionByNumber[(int) $sessionModel->number] = $sessionModel;
        }

        // 2) Mapping ENTRY@eventid (10,20,...) -> (session_number, event_number)
        $entryEventMap = [];

        foreach ($meetNode->SESSIONS->SESSION ?? [] as $sessionNode) {
            $sessionNumber = (int) ($sessionNode['number'] ?? 0);
            foreach ($sessionNode->EVENTS->EVENT ?? [] as $eventNode) {
                $eventNumber = (int) ($eventNode['number'] ?? 0);
                $lenexEventIdForEntry = (string) ($eventNode['eventid'] ?? '');
                if ($lenexEventIdForEntry !== '') {
                    $entryEventMap[$lenexEventIdForEntry] = [$sessionNumber, $eventNumber];
                }
            }
        }

        // 3) Event-Lookup: (session_number, event_number) -> ParaEvent
        $eventBySessionAndNumber = [];
        foreach ($meet->sessions as $sessionModel) {
            foreach ($sessionModel->events as $eventModel) {
                $eventBySessionAndNumber[$sessionModel->number.':'.$eventModel->number] = $eventModel;
            }
        }

        // 4) CLUBS / ATHLETES / ENTRIES durchgehen
        foreach ($meetNode->CLUBS->CLUB ?? [] as $clubNode) {

            // Nation über NationResolver aus clubNode@nation (z.B. AUT)
            $nation = $this->nationResolver->fromLenexCode((string) $clubNode['nation']);

            // ParaClub aus Resolver
            $club = $this->clubResolver->resolveFromLenex($clubNode);

            $this->applyClubMetaFromLenex($club, $clubNode);

            foreach ($clubNode->ATHLETES->ATHLETE ?? [] as $athNode) {

                $athlete = $this->athleteResolver->resolveFromLenex(
                    $athNode,
                    $club,
                    $nation
                );

                $lenexAthleteId = (string) ($athNode['athleteid'] ?? null);

                foreach ($athNode->ENTRIES->ENTRY ?? [] as $entryNode) {
                    $lenexEntryEventId = (string) ($entryNode['eventid'] ?? '');
                    if ($lenexEntryEventId === '' || !isset($entryEventMap[$lenexEntryEventId])) {
                        // Kein Mapping zum Event (z.B. Quali für anderes Meeting)
                        continue;
                    }

                    [$sessionNumber, $eventNumber] = $entryEventMap[$lenexEntryEventId];

                    $sessionModel = $sessionByNumber[$sessionNumber] ?? null;
                    if (!$sessionModel) {
                        continue;
                    }

                    $eventModel = $eventBySessionAndNumber[$sessionNumber.':'.$eventNumber] ?? null;
                    if (!$eventModel) {
                        continue;
                    }

                    // Stichtag bestimmen (AGEDATE -> Sessiondatum -> meet.from_date)
                    $ageDate = null;
                    if ($ageDateValue) {
                        $ageDate = Carbon::parse($ageDateValue);
                    } elseif ($eventModel->session?->date) {
                        $ageDate = Carbon::parse($eventModel->session->date);
                    } elseif ($meet->from_date) {
                        $ageDate = Carbon::parse($meet->from_date);
                    }

                    // Agegroup via AgegroupResolver (wenn Stichtag verfügbar)
                    $agegroupModel = $ageDate
                        ? $this->agegroupResolver->resolve($eventModel, $athlete, $ageDate)
                        : null;

                    // Entrytime
                    $entryTimeStr = (string) ($entryNode['entrytime'] ?? '');
                    $entryTimeMs = $this->parseTimeToMs($entryTimeStr);

                    // MEETINFO (Quali-Wettkampf)
                    $mi = $entryNode->MEETINFO[0] ?? null;

                    ParaEntry::updateOrCreate(
                        [
                            'para_event_id' => $eventModel->id,
                            'para_athlete_id' => $athlete->id,
                        ],
                        [
                            'para_meet_id' => $meet->id,
                            'para_session_id' => $sessionModel->id,
                            'para_event_agegroup_id' => $agegroupModel?->id,
                            'para_club_id' => $club->id,

                            'lenex_athleteid' => $lenexAthleteId !== '' ? $lenexAthleteId : null,
                            'lenex_eventid' => $lenexEntryEventId,

                            'entry_time' => $entryTimeStr ?: null,
                            'entry_time_ms' => $entryTimeMs,

                            'course' => $mi ? (string) ($mi['course'] ?? null) : null,
                            'qualifying_date' => $mi ? (string) ($mi['date'] ?? null) : null,
                            'qualifying_meet_name' => $mi ? (string) ($mi['name'] ?? null) : null,
                            'qualifying_city' => $mi ? (string) ($mi['city'] ?? null) : null,
                            'qualifying_nation' => $mi ? (string) ($mi['nation'] ?? null) : null,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Ergänzt ParaClub mit ShortNameDe, Subregion und Region
     * anhand des LENEX-CLUB-Knotens.
     */
    public function applyClubMetaFromLenex(ParaClub $club, SimpleXMLElement $clubNode): void
    {
        // Kurzname, falls im LENEX vorhanden
        $shortName = (string) ($clubNode['shortname'] ?? $clubNode['name'] ?? '');
        if ($shortName !== '') {
            $club->shortNameDe = $shortName;
        }

        // Region-Code aus LENEX (z.B. WLSV, NLSV, …)
        $regionCode = (string) ($clubNode['region'] ?? '');

        if ($regionCode !== '') {
            // Subregion anhand lsvCode suchen
            $subregion = Subregion::where('lsvCode', $regionCode)->first();

            if ($subregion) {
                $club->subregion_id = $subregion->id;
            } else {
                // wenn kein passender LSV-Code gefunden wird, Subregion auf null
                $club->subregion_id = null;
            }
        }

        $club->save();
    }

    /**
     * Zeit-String (HH:MM:SS.cc / MM:SS.cc) → Millisekunden.
     */
    public function parseTimeToMs(?string $time): ?int
    {
        if (!$time) {
            return null;
        }

        $time = trim($time);

        if (!preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{1,2})(?:\.(\d{1,3}))?$/', $time, $m)) {
            return null;
        }

        $hours = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
        $minutes = (int) $m[2];
        $seconds = (int) $m[3];
        $ms = isset($m[4]) && $m[4] !== '' ? (int) str_pad($m[4], 3, '0') : 0;

        return (($hours * 3600) + ($minutes * 60) + $seconds) * 1000 + $ms;
    }

}
