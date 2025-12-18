<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaEvent;
use App\Models\ParaMeet;
use App\Services\Lenex\LenexImportService;
use App\Services\Lenex\LenexRelayImporter;
use App\Services\Lenex\Preview\LenexPreviewSupport;
use App\Support\SwimTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Random\RandomException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexRelayImportController extends Controller
{
    public function create(ParaMeet $meet): View
    {
        return view('lenex.relays-upload', compact('meet'));
    }

    /**
     * @throws RandomException
     */
    public function preview(
        Request $request,
        ParaMeet $meet,
        LenexImportService $lenex,
        LenexPreviewSupport $support,
    ): View {
        $validated = $request->validate([
            'lenex_file' => ['required', 'file', 'max:51200'], // 50MB
        ]);

        /** @var UploadedFile $file */
        $file = $validated['lenex_file'];

        // 1) Upload speichern (RELATIVER Pfad)
        $relativePath = $support->storeUploadedLenex($file);

        // 2) Absoluten Pfad holen (Windows-safe)
        $absolutePath = Storage::disk('local')->path($relativePath);

        // 3) LENEX Root laden (zentraler Loader)
        $root = $lenex->loadLenexRootFromPath($absolutePath);

        /** @var SimpleXMLElement|null $meetNode */
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX (Relays) gefunden.');
        }

        // resultid -> agegroup infos (damit wir z.B. 34Pkt / VI / MI / handicap=14 kennen)
        $resultAgegroupByResultId = $this->buildResultAgegroupIndex($meetNode);

        // 4) Globaler LENEX Athlete Index (athleteid -> ATHLETE node)
        $globalAthNodeById = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $cNode) {
            foreach (($cNode->ATHLETES->ATHLETE ?? []) as $aNode) {
                $id = (string) ($aNode['athleteid'] ?? '');
                if ($id !== '') {
                    $globalAthNodeById[$id] = $aNode;
                }
            }
        }

        // 5) LENEX Event Meta Index (eventid -> relaycount/distance/stroke)
        $lenexEventMeta = $this->buildLenexEventMetaIndex($meetNode);

        // 6) DB Events + Swimstyle laden (DB-first)
        $meet->load('sessions.events.swimstyle');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // 7) Alle athleteids aus RELAYPOSITIONS sammeln (für Entry-Mapping)
        $allPosIds = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                foreach (($relayNode->RESULTS->RESULT ?? []) as $resultNode) {
                    foreach (($resultNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $posNode) {
                        $aid = (string) ($posNode['athleteid'] ?? '');
                        if ($aid !== '') {
                            $allPosIds[] = $aid;
                        }
                    }
                }
            }
        }
        $allPosIds = array_values(array_unique($allPosIds));

        $entriesByLenexAthleteId = ParaEntry::query()
            ->when(!empty($allPosIds), fn($q) => $q->whereIn('lenex_athleteid', $allPosIds))
            ->with('athlete.club')
            ->get()
            ->keyBy('lenex_athleteid');

        // 8) Preview-Struktur aufbauen
        $clubs = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            /** @var SimpleXMLElement $clubNode */
            $lenexClubId = (string) ($clubNode['clubid'] ?? '');
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));

            // Club im System finden (zentralisiert)
            $existingClub = ParaClub::findByLenexOrName($lenexClubId, $clubName);

            // Club-interne Athletes indexieren (LENEX-Clubzugehörigkeit)
            $athNodeById = [];
            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                $aid = (string) ($athNode['athleteid'] ?? '');
                if ($aid !== '') {
                    $athNodeById[$aid] = $athNode;
                }
            }

            $relayRows = [];

            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                /** @var SimpleXMLElement $relayNode */
                $relayNumber = (string) ($relayNode['number'] ?? '');
                $relayGender = (string) ($relayNode['gender'] ?? '');

                foreach (($relayNode->RESULTS->RESULT ?? []) as $resultNode) {
                    /** @var SimpleXMLElement $resultNode */
                    $resultId = (string) ($resultNode['resultid'] ?? '');
                    $lenexEventId = (string) ($resultNode['eventid'] ?? '');

                    $invalidReasons = [];

                    /** @var ParaEvent|null $event */
                    $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;
                    $swimstyle = $event?->swimstyle; // ✅ wichtig: bevor du $swimstyle prüfst!

                    $agInfo = $resultId !== '' ? ($resultAgegroupByResultId[$resultId] ?? null) : null;
                    $relaySportClass = $this->resolveRelaySportClassCode($agInfo); // z.B. "S14", "S34", "S49", "S21", "S20"

                    if (!$event) {
                        $invalidReasons[] = "Event {$lenexEventId} nicht im Meeting vorhanden";
                    }

                    $meta = $lenexEventMeta[$lenexEventId] ?? ['relaycount' => 0, 'distance' => 0, 'stroke' => ''];

                    // ✅ Relay-Check DB-first, sonst LENEX fallback
                    if ($swimstyle) {
                        if (!$swimstyle->is_relay) {
                            $invalidReasons[] = 'Event ist kein Relay-Event (DB swimstyle.is_relay=false)';
                        }
                    } else {
                        if ((int) ($meta['relaycount'] ?? 0) <= 1) {
                            $invalidReasons[] = 'Event ist kein Relay-Event (LENEX relaycount<=1)';
                        }
                    }

                    if (!$existingClub) {
                        $invalidReasons[] = 'Verein im System nicht gefunden';
                    }

                    // Event Label (DB-first, deutsch)
                    $relaycount = (int) ($swimstyle?->relaycount ?? 0);
                    $distance = (int) ($swimstyle?->distance ?? 0);
                    $strokeDe = (string) ($swimstyle?->stroke_name_de ?? $swimstyle?->stroke ?? '');

                    // fallback nur falls swimstyle fehlt
                    if (!$swimstyle) {
                        $relaycount = (int) ($meta['relaycount'] ?? 0);
                        $distance = (int) ($meta['distance'] ?? 0);
                        $strokeDe = (string) ($meta['stroke'] ?? '');
                    }

                    $eventLabel = $this->formatRelayEventLabel($relaycount, $distance, $strokeDe, $lenexEventId);

                    if ($event && !$swimstyle) {
                        $invalidReasons[] = 'Event hat keine Swimstyle-Verknüpfung (swimstyle_id fehlt)';
                    }

                    // Teilnehmer prüfen
                    $positions = [];

                    foreach (($resultNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $posNode) {
                        /** @var SimpleXMLElement $posNode */
                        $aid = (string) ($posNode['athleteid'] ?? '');
                        $leg = (int) ($posNode['number'] ?? 0);

                        // ✅ immer initialisieren
                        $dbAthlete = null;

                        $inLenexClub = $aid !== '' && isset($athNodeById[$aid]);

                        // Für Namen: erst club-intern, sonst global
                        $globalAthNode = $globalAthNodeById[$aid] ?? null;
                        $nameNode = $inLenexClub ? ($athNodeById[$aid] ?? null) : $globalAthNode;
                        $strokeCodeForClass = (string) ($swimstyle?->stroke ?? ($meta['stroke'] ?? 'FREE')); // FREE/BREAST/MEDLEY...
                        $sportClassUsed = $this->lenexAthleteSportClass($globalAthNode, $strokeCodeForClass);

                        $first = $nameNode ? trim((string) ($nameNode['firstname'] ?? $nameNode['givenname'] ?? '')) : '';
                        $last = $nameNode ? trim((string) ($nameNode['lastname'] ?? $nameNode['familyname'] ?? '')) : '';
                        $birthdate = $nameNode ? trim((string) ($nameNode['birthdate'] ?? '')) : '';

                        // Prefer: ParaEntry.lenex_athleteid -> Athlete
                        $mappedEntry = $aid !== '' ? ($entriesByLenexAthleteId[$aid] ?? null) : null;
                        $dbAthlete = $mappedEntry?->athlete;

                        // Fallback: match by name (+ birthdate)
                        if (!$dbAthlete && $first !== '' && $last !== '') {
                            $dbAthlete = ParaAthlete::query()
                                ->with('club')
                                ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
                                ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)])
                                ->when($birthdate !== '', fn($q) => $q->whereDate('birthdate', $birthdate))
                                ->first();
                        }

                        // Wenn LENEX-Name leer, aber DB da: Namen auffüllen
                        if (($first === '' || $last === '') && $dbAthlete) {
                            $first = (string) $dbAthlete->firstName;
                            $last = (string) $dbAthlete->lastName;
                        }

                        $existsInDb = (bool) $dbAthlete;

                        // Warnungen (mit Namen)
                        if (!$inLenexClub) {
                            $invalidReasons[] =
                                $this->lenexAthleteLabel($aid, $globalAthNode, $dbAthlete)
                                .' gehört im LENEX nicht zu diesem Verein';
                        }

                        if (!$existsInDb) {
                            $invalidReasons[] =
                                $this->lenexAthleteLabel($aid, $globalAthNode)
                                .' nicht in para_athletes gefunden';
                        }

                        if ($dbAthlete && $existingClub && (int) $dbAthlete->para_club_id !== (int) $existingClub->id) {
                            $otherClubName = $dbAthlete->club?->shortNameDe
                                ?? $dbAthlete->club?->nameDe
                                ?? null;

                            $invalidReasons[] =
                                $this->dbAthleteLabel($dbAthlete)
                                .' ist im System bei anderem Verein'
                                .($otherClubName ? " ({$otherClubName})" : '');
                        }

                        $positions[] = [
                            'leg' => $leg,
                            'lenex_athlete_id' => $aid,
                            'first_name' => $first,
                            'last_name' => $last,
                            'in_lenex_club' => $inLenexClub,
                            'exists_in_db' => $existsInDb,
                            'db_athlete_id' => $dbAthlete?->id,
                            'sport_class' => $sportClassUsed,
                        ];
                    }

                    $check = $this->checkRelaySportClassRule($relaySportClass, $positions);

                    if (!$check['ok']) {
                        $invalidReasons[] = $check['message'];
                    }

                    $swimtimeStr = (string) ($resultNode['swimtime'] ?? '');
                    $swimtimeMs = SwimTime::parseToMs($swimtimeStr);

                    $relayRows[] = [
                        'result_id' => $resultId ?: ($nation.'|'.$clubName.'|'.$relayNumber.'|'.$lenexEventId),
                        'lenex_resultid' => $resultId,
                        'lenex_eventid' => $lenexEventId,

                        // ✅ für Anzeige
                        'relay_event_label' => $eventLabel,
                        'relay_sportclass' => $relaySportClass, // z.B. "S14"
                        'agegroup_name' => $agInfo['name'] ?? null,
                        'para_event_id' => $event?->id,
                        'swimstyle_id' => $swimstyle?->id,

                        'relay_number' => $relayNumber,
                        'relay_gender' => $relayGender,
                        'swimtime_lenex' => (string) ($resultNode['swimtime'] ?? ''),
                        'swimtime' => $swimtimeStr,
                        'swimtime_ms' => $swimtimeMs,
                        'positions' => $positions,
                        'invalid' => !empty($invalidReasons),
                        'invalid_reasons' => array_values(array_unique($invalidReasons)),
                    ];
                }
            }

            if (!empty($relayRows)) {
                $clubs[] = [
                    'club_id' => $lenexClubId,
                    'club_name' => $clubName,
                    'nation' => $nation,
                    'relay_rows' => $relayRows,
                ];
            }
        }

        // RELATIVER Pfad wird in Hidden Input gespeichert
        $lenexFilePath = $relativePath;

        return view('lenex.relays-preview', compact('meet', 'clubs', 'lenexFilePath'));
    }

    // ------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------

    private function buildResultAgegroupIndex(SimpleXMLElement $meetNode): array
    {
        $map = [];
        $bestOrder = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                foreach (($eventNode->AGEGROUPS->AGEGROUP ?? []) as $agNode) {

                    $agName = (string) ($agNode['name'] ?? '');
                    $agHandicap = (string) ($agNode['handicap'] ?? '');
                    $agGender = (string) ($agNode['gender'] ?? '');

                    foreach (($agNode->RANKINGS->RANKING ?? []) as $rk) {
                        $rid = (string) ($rk['resultid'] ?? '');
                        if ($rid === '') {
                            continue;
                        }

                        $order = (int) ($rk['order'] ?? 999999);

                        if (!isset($bestOrder[$rid]) || $order < $bestOrder[$rid]) {
                            $bestOrder[$rid] = $order;
                            $map[$rid] = [
                                'name' => $agName,
                                'handicap' => $agHandicap,
                                'gender' => $agGender,
                            ];
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function buildLenexEventMetaIndex(SimpleXMLElement $meetNode): array
    {
        $meta = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                $eid = (string) ($eventNode['eventid'] ?? '');
                if ($eid === '') {
                    continue;
                }

                $ss = $eventNode->SWIMSTYLE ?? null;

                $meta[$eid] = [
                    'relaycount' => (int) ($ss?->attributes()?->relaycount ?? $ss['relaycount'] ?? 0),
                    'distance' => (int) ($ss?->attributes()?->distance ?? $ss['distance'] ?? 0),
                    'stroke' => (string) ($ss?->attributes()?->stroke ?? $ss['stroke'] ?? ''),
                ];
            }
        }

        return $meta;
    }

    private function resolveRelaySportClassCode(?array $agInfo): ?string
    {
        if (!$agInfo) {
            return null;
        }

        $handicap = trim((string) ($agInfo['handicap'] ?? ''));
        if ($handicap !== '' && ctype_digit($handicap)) {
            $n = (int) $handicap;
            // in deinen Regeln relevant
            if (in_array($n, [14, 20, 21, 34, 49], true)) {
                return 'S'.$n;
            }
        }

        $name = mb_strtoupper((string) ($agInfo['name'] ?? ''));

        if (str_contains($name, '34') && str_contains($name, 'PKT')) {
            return 'S34';
        }
        if (str_contains($name, '20') && str_contains($name, 'PKT')) {
            return 'S20';
        }
        if (str_contains($name, 'T21')) {
            return 'S21';
        }
        if (str_contains($name, 'VI')) {
            return 'S49';
        }
        if (str_contains($name, 'MI')) {
            return 'S14';
        }

        return null;
    }

    private function formatRelayEventLabel(
        int $relaycount,
        int $distance,
        string $strokeDe,
        string $lenexEventId
    ): string {
        $strokeDe = trim($strokeDe);
        if ($relaycount > 1 && $distance > 0) {
            return trim("{$relaycount}x{$distance}m {$strokeDe}");
        }
        if ($distance > 0) {
            return trim("{$distance}m {$strokeDe}");
        }
        return "Event {$lenexEventId}";
    }

    private function lenexAthleteSportClass(?SimpleXMLElement $athNode, string $strokeCode): ?int
    {
        if (!$athNode) {
            return null;
        }

        $hc = $athNode->HANDICAP ?? null;
        if (!$hc instanceof SimpleXMLElement) {
            return null;
        }

        $strokeCode = strtoupper(trim($strokeCode));

        // Para: BACK/FLY/FREE nutzen i.d.R. S-Klasse -> im LENEX steht sie unter "free"
        $attr = match ($strokeCode) {
            'BREAST', 'BREASTSTROKE' => 'breast',
            'MEDLEY', 'IM' => 'medley',
            default => 'free',
        };

        $val = (string) ($hc[$attr] ?? '');
        $val = trim($val);

        return ($val !== '' && ctype_digit($val)) ? (int) $val : null;
    }

    private function lenexAthleteLabel(
        string $lenexId,
        ?SimpleXMLElement $athNode,
        ?ParaAthlete $dbAthlete = null
    ): string {
        $lenexId = trim($lenexId);

        $first = $athNode ? trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? '')) : '';
        $last = $athNode ? trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? '')) : '';

        if (($first === '' || $last === '') && $dbAthlete) {
            $first = (string) $dbAthlete->firstName;
            $last = (string) $dbAthlete->lastName;
        }

        $name = trim(trim($last.', '.$first), ', ');
        return $name !== '' ? "LENEX#{$lenexId} ({$name})" : "LENEX#{$lenexId}";
    }

    private function dbAthleteLabel(ParaAthlete $athlete): string
    {
        $name = trim(trim(($athlete->lastName ?? '').', '.($athlete->firstName ?? '')), ', ');
        return $name !== '' ? "DB#{$athlete->id} ({$name})" : "DB#{$athlete->id}";
    }

    private function checkRelaySportClassRule(?string $relaySportClass, array $positions): array
    {
        $classes = [];
        foreach ($positions as $p) {
            $classes[] = $p['sport_class'] ?? null;
        }

        if (!$relaySportClass) {
            return ['ok' => true, 'message' => null];
        }

        if (in_array(null, $classes, true)) {
            return [
                'ok' => false,
                'message' => "Sportklasse {$relaySportClass}: nicht alle Athleten haben eine HANDICAP-Klasse im LENEX."
            ];
        }

        $sum = array_sum($classes);

        $allBetween = fn(int $min, int $max) => collect($classes)->every(fn($c) => $c >= $min && $c <= $max);

        return match ($relaySportClass) {
            'S14' => collect($classes)->every(fn($c) => in_array($c, [14, 21], true))
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => 'Sportklasse S14: erlaubt sind nur Klassen 14 oder 21.'],

            'S21' => collect($classes)->every(fn($c) => $c === 21)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => 'Sportklasse S21: erlaubt sind nur Athleten mit Klasse 21.'],

            'S20' => ($allBetween(1, 10) && $sum <= 20)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => "Sportklasse S20: Klassen 1–10 und Summe ≤ 20 (aktuell {$sum})."],

            'S34' => ($allBetween(1, 10) && $sum <= 34)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => "Sportklasse S34: Klassen 1–10 und Summe ≤ 34 (aktuell {$sum})."],

            'S49' => ($allBetween(11, 13) && $sum <= 49)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => "Sportklasse S49: Klassen 11–13 und Summe ≤ 49 (aktuell {$sum})."],

            default => ['ok' => true, 'message' => null],
        };
    }

    /**
     * @throws Throwable
     */
    public function import(Request $request, ParaMeet $meet, LenexRelayImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'lenex_file_path' => ['required', 'string'],
            'selected_relays' => ['required', 'array', 'min:1'],
            'selected_relays.*' => ['string'],
        ]);

        $relativePath = $data['lenex_file_path'];

        if (!Storage::disk('local')->exists($relativePath)) {
            return back()->withErrors([
                'lenex_file_path' => 'Die Lenex-Datei wurde nicht mehr gefunden (storage/app). Bitte Preview neu laden.',
            ]);
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        $importer->import($absolutePath, $meet, $data['selected_relays']);

        // optional: tmp löschen
        // Storage::disk('local')->delete($relativePath);

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Staffel-Resultate wurden importiert.');
    }

}
