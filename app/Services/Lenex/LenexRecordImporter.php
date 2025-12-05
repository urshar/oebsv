<?php

namespace App\Services\Lenex;

use App\Models\Nation;
use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaRecord;
use App\Models\ParaRecordSplit;
use App\Models\ParaRecordImportCandidate;
use App\Models\ParaRecordImportCandidateSplit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class LenexRecordImporter
{
    public function __construct(
        protected LenexImportService $lenexImportService,
        protected NationResolver     $nationResolver,
        protected ClubResolver       $clubResolver,   // aktuell nur für Nation ggf.
        protected AthleteResolver    $athleteResolver // dito
    ) {}

    public function import(string $path): void
    {
        $root       = $this->lenexImportService->loadLenexRootFromPath($path);
        $sourceFile = basename($path);

        if (! isset($root->RECORDLISTS)) {
            return;
        }

        DB::transaction(function () use ($root, $sourceFile) {
            foreach ($root->RECORDLISTS->RECORDLIST ?? [] as $recordListXml) {
                $this->importRecordList($recordListXml, $sourceFile);
            }
        });
    }

    protected function importRecordList(SimpleXMLElement $recordListXml, string $sourceFile): void
    {
        $handicap = isset($recordListXml['handicap'])
            ? (int) $recordListXml['handicap']
            : null;

        $nationCode  = (string) ($recordListXml['nation'] ?? '');
        $nationModel = $nationCode !== ''
            ? $this->nationResolver->fromLenexCode($nationCode)
            : null;

        $agegroupNode = $recordListXml->AGEGROUP[0] ?? null;

        $rawAgeMin = $agegroupNode && isset($agegroupNode['agemin'])
            ? (int) $agegroupNode['agemin']
            : -1;

        $rawAgeMax = $agegroupNode && isset($agegroupNode['agemax'])
            ? (int) $agegroupNode['agemax']
            : -1;

        [$ageMin, $ageMax] = $this->normalizeAgeRange($rawAgeMin, $rawAgeMax);

        $recordType = (string) ($recordListXml['type'] ?? '');

        $meta = [
            'source_file'           => $sourceFile,
            'record_list_name'      => (string) ($recordListXml['name'] ?? ''),
            'record_type'           => $recordType,
            'course'                => (string) ($recordListXml['course'] ?? ''),
            'gender'                => (string) ($recordListXml['gender'] ?? ''),
            'handicap'              => $handicap,
            'sport_class'           => $this->mapHandicapToSportClass($handicap),
            'recordlist_updated_at' => $this->parseDate((string) ($recordListXml['updated'] ?? '')),

            'age_min'               => $ageMin,
            'age_max'               => $ageMax,
            'agegroup_code'         => $this->determineAgegroupCode($recordType, $ageMin, $ageMax),

            'nation_id'             => $nationModel?->id,
            'nation_model'          => $nationModel,
        ];

        if (! isset($recordListXml->RECORDS)) {
            return;
        }

        foreach ($recordListXml->RECORDS->RECORD ?? [] as $recordXml) {
            $this->importRecord($recordXml, $meta);
        }
    }

    protected function importRecord(SimpleXMLElement $recordXml, array $meta): void
    {
        if (! isset($recordXml['swimtime']) || ! isset($recordXml->SWIMSTYLE)) {
            return;
        }

        $swimtimeStr = (string) $recordXml['swimtime'];
        $swimtimeMs  = $this->lenexImportService->parseTimeToMs($swimtimeStr);

        if ($swimtimeMs === null) {
            return;
        }

        $swimstyleXml = $recordXml->SWIMSTYLE;
        $distance     = (int) ($swimstyleXml['distance'] ?? 0);
        $relaycount   = (int) ($swimstyleXml['relaycount'] ?? 1);
        $stroke       = (string) ($swimstyleXml['stroke'] ?? '');
        $isRelay      = $relaycount > 1;

        $meetName       = null;
        $meetNationCode = null;
        $swumAt         = null;

        if (isset($recordXml->MEETINFO)) {
            $meetInfo       = $recordXml->MEETINFO;
            $meetName       = (string) ($meetInfo['name'] ?? '');
            $meetNationCode = (string) ($meetInfo['nation'] ?? '');
            $swumAt         = $this->parseDate((string) ($meetInfo['date'] ?? ''));
        }

        // --- Athlete & Club (inkl. Rohdaten für Kandidat) ---
        $holderFirstname = null;
        $holderLastname  = null;
        $holderYear      = null;
        $paraClub        = null;
        $paraAthlete     = null;

        $athSwrid    = null;
        $athTmId     = null;
        $athLicense  = null;
        $athBirth    = null;
        $athGender   = null;

        $clubSwrid   = null;
        $clubCode    = null;
        $clubName    = null;
        $clubNation  = null;

        if (isset($recordXml->ATHLETE)) {
            $athleteXml      = $recordXml->ATHLETE;
            $holderFirstname = trim((string) ($athleteXml['firstname'] ?? ''));
            $holderLastname  = trim((string) ($athleteXml['lastname'] ?? ''));
            $athBirth        = trim((string) ($athleteXml['birthdate'] ?? '')) ?: null;
            $athGender       = trim((string) ($athleteXml['gender'] ?? '')) ?: null;
            $holderYear      = $this->parseYear($athBirth ?? '');

            $athSwrid   = trim((string) ($athleteXml['swrid'] ?? '')) ?: null;
            $athTmIdRaw = (string) ($athleteXml['tmid'] ?? '');
            $athTmId    = $athTmIdRaw !== '' ? (int) $athTmIdRaw : null;
            $athLicense = trim((string) ($athleteXml['license'] ?? '')) ?: null;

            $clubNode = $athleteXml->CLUB[0] ?? null;

            $nationForAthlete = $meta['nation_model'] ?? null;

            if ($clubNode instanceof SimpleXMLElement) {
                $clubSwrid  = trim((string) ($clubNode['swrid'] ?? '')) ?: null;
                $clubCode   = trim((string) ($clubNode['code'] ?? '')) ?: null;
                $clubName   = trim((string) ($clubNode['name'] ?? '')) ?: null;
                $clubNation = (string) ($clubNode['nation'] ?? '') ?: null;

                $paraClub = $this->findExistingClubFromLenex($clubNode);

                if ($clubNation) {
                    $nationForAthlete = $this->nationResolver->fromLenexCode($clubNation) ?? $nationForAthlete;
                }
            }

            $paraAthlete = $this->findExistingAthleteFromLenex(
                $athleteXml,
                $paraClub,
                $nationForAthlete
            );
        }

        $missingAthlete = isset($recordXml->ATHLETE) && ! $paraAthlete;
        $missingClub    = isset($recordXml->ATHLETE->CLUB[0]) && ! $paraClub;

        // -------------------------
        // FALL 1: Kandidat erzeugen
        // -------------------------
        if ($missingAthlete || $missingClub) {
            $candidate = ParaRecordImportCandidate::create([
                'source_file'           => $meta['source_file'] ?? null,
                'nation_id'             => $meta['nation_id'] ?? null,
                'record_list_name'      => $meta['record_list_name'],
                'record_type'           => $meta['record_type'],
                'course'                => $meta['course'],
                'gender'                => $meta['gender'],
                'handicap'              => $meta['handicap'],
                'sport_class'           => $meta['sport_class'],
                'recordlist_updated_at' => $meta['recordlist_updated_at'],

                'age_min'               => $meta['age_min'],
                'age_max'               => $meta['age_max'],
                'agegroup_code'         => $meta['agegroup_code'],

                'distance'              => $distance,
                'stroke'                => $stroke,
                'relaycount'            => $relaycount,
                'is_relay'              => $isRelay,

                'swimtime_ms'           => $swimtimeMs,
                'swum_at'               => $swumAt,
                'status'                => (string) ($recordXml['status'] ?? ''),
                'meet_name'             => $meetName,
                'meet_nation'           => $meetNationCode,

                'athlete_swrid'         => $athSwrid,
                'athlete_tmid'          => $athTmId,
                'athlete_license'       => $athLicense,
                'athlete_firstname'     => $holderFirstname,
                'athlete_lastname'      => $holderLastname,
                'athlete_gender'        => $athGender,
                'athlete_birthdate'     => $athBirth ?: null,

                'club_swrid'            => $clubSwrid,
                'club_code'             => $clubCode,
                'club_name'             => $clubName,
                'club_nation'           => $clubNation,

                'missing_athlete'       => $missingAthlete,
                'missing_club'          => $missingClub,

                'para_athlete_id'       => $paraAthlete?->id,
                'para_club_id'          => $paraClub?->id,
                'resolution_status'     => 'pending',
            ]);

            // Splits an Kandidat hängen
            if (isset($recordXml->SPLITS)) {
                $order = 1;
                foreach ($recordXml->SPLITS->SPLIT ?? [] as $splitXml) {
                    $splitTimeMs = $this->lenexImportService->parseTimeToMs(
                        (string) ($splitXml['swimtime'] ?? '')
                    );
                    if ($splitTimeMs === null) {
                        continue;
                    }

                    ParaRecordImportCandidateSplit::create([
                        'para_record_import_candidate_id' => $candidate->id,
                        'distance'                         => (int) ($splitXml['distance'] ?? 0),
                        'order'                            => $order++,
                        'swimtime_ms'                      => $splitTimeMs,
                    ]);
                }
            }

            return;
        }

        // ----------------------------------
        // FALL 2: direkter Rekord + Splits
        // ----------------------------------
        $record = ParaRecord::firstOrCreate(
            [
                'record_list_name' => $meta['record_list_name'],
                'record_type'      => $meta['record_type'],
                'course'           => $meta['course'],
                'gender'           => $meta['gender'],
                'handicap'         => $meta['handicap'],
                'distance'         => $distance,
                'stroke'           => $stroke,
                'relaycount'       => $relaycount,
                'swimtime_ms'      => $swimtimeMs,
                'swum_at'          => $swumAt,
            ],
            [
                'sport_class'           => $meta['sport_class'],
                'nation_id'             => $meta['nation_id'] ?? null,
                'recordlist_updated_at' => $meta['recordlist_updated_at'],

                'status'                => (string) ($recordXml['status'] ?? ''),
                'meet_name'             => $meetName,
                'meet_nation'           => $meetNationCode,

                'holder_firstname'      => $holderFirstname,
                'holder_lastname'       => $holderLastname,
                'holder_year_of_birth'  => $holderYear,

                'is_relay'              => $isRelay,
                'age_min'               => $meta['age_min'],
                'age_max'               => $meta['age_max'],
                'agegroup_code'         => $meta['agegroup_code'],

                'para_athlete_id'       => $paraAthlete?->id,
                'para_club_id'          => $paraClub?->id,
            ]
        );

        // Splits für Rekord
        if (isset($recordXml->SPLITS)) {
            ParaRecordSplit::where('para_record_id', $record->id)->delete();

            $order = 1;
            foreach ($recordXml->SPLITS->SPLIT ?? [] as $splitXml) {
                $splitTimeMs = $this->lenexImportService->parseTimeToMs(
                    (string) ($splitXml['swimtime'] ?? '')
                );
                if ($splitTimeMs === null) {
                    continue;
                }

                ParaRecordSplit::create([
                    'para_record_id' => $record->id,
                    'distance'       => (int) ($splitXml['distance'] ?? 0),
                    'order'          => $order++,
                    'swimtime_ms'    => $splitTimeMs,
                ]);
            }
        }
    }

    // ---------- Matching-Helfer ----------

    protected function findExistingClubFromLenex(SimpleXMLElement $clubNode): ?ParaClub
    {
        $nation = $this->nationResolver->fromLenexCode((string) $clubNode['nation']);
        $nationId = $nation?->id;

        $swrid = trim((string) ($clubNode['swrid'] ?? ''));
        $code  = trim((string) ($clubNode['code'] ?? ''));
        $name  = trim((string) ($clubNode['name'] ?? ''));

        if ($swrid !== '') {
            $existing = ParaClub::where('swrid', $swrid)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($code !== '' && $nationId) {
            $existing = ParaClub::where('clubCode', $code)
                ->where('nation_id', $nationId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($nationId && $name !== '') {
            $existing = ParaClub::where('nameDe', $name)
                ->where('nation_id', $nationId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    protected function findExistingAthleteFromLenex(
        SimpleXMLElement $athNode,
        ?ParaClub $club,
        ?Nation $nation
    ): ?ParaAthlete {
        $swrid   = trim((string) ($athNode['swrid'] ?? ''));
        $tmIdRaw = (string) ($athNode['tmid'] ?? '');
        $tmId    = $tmIdRaw !== '' ? (int) $tmIdRaw : null;
        $license = trim((string) ($athNode['license'] ?? ''));

        $first  = trim((string) $athNode['firstname']);
        $last   = trim((string) $athNode['lastname']);
        $gender = trim((string) ($athNode['gender'] ?? '')) ?: null;
        $birth  = trim((string) ($athNode['birthdate'] ?? '')) ?: null;

        $nationId = $nation?->id;

        if ($swrid !== '') {
            $existing = ParaAthlete::where('swrid', $swrid)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($tmId !== null) {
            $existing = ParaAthlete::where('tmId', $tmId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($license !== '') {
            $existing = ParaAthlete::where('oebsv_license', $license)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($birth && $gender && $nationId) {
            $existing = ParaAthlete::where([
                'firstName' => $first,
                'lastName'  => $last,
                'birthdate' => $birth,
                'gender'    => $gender,
                'nation_id' => $nationId,
            ])->first();

            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    // ---------- Helper ----------

    protected function mapHandicapToSportClass(?int $handicap): ?string
    {
        if ($handicap === null || $handicap <= 0) {
            return null;
        }

        return 'S' . $handicap;
    }

    protected function normalizeAgeRange(int $ageMinRaw, int $ageMaxRaw): array
    {
        $ageMin = max(0, $ageMinRaw);
        $ageMax = $ageMaxRaw < 0 ? 99 : $ageMaxRaw;

        return [$ageMin, $ageMax];
    }

    protected function determineAgegroupCode(string $recordType, int $ageMin, int $ageMax): string
    {
        if (str_ends_with($recordType, '.JG')) {
            return 'JG';
        }

        if ($ageMax <= 18) {
            return 'JG';
        }

        return 'OP';
    }

    protected function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseYear(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->year;
        } catch (\Throwable) {
            return null;
        }
    }
}
