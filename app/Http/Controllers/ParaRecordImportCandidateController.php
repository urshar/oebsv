<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaRecord;
use App\Models\ParaRecordImportCandidate;
use App\Models\ParaRecordSplit;
use App\Services\Lenex\NationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ParaRecordImportCandidateController extends Controller
{
    public function __construct(
        protected NationResolver $nationResolver,
    ) {}

    public function index()
    {
        $candidates = ParaRecordImportCandidate::query()
            ->with(['athlete', 'club', 'nation'])
            ->where('resolution_status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('paraRecords.import_candidates.index', compact('candidates'));
    }

    public function edit(ParaRecordImportCandidate $candidate)
    {
        // mögliche Athleten-Kandidaten (nach Name + ggf. Geburtsdatum)
        $matchingAthletes = ParaAthlete::query()
            ->when($candidate->athlete_lastname, fn ($q) =>
            $q->where('lastName', $candidate->athlete_lastname)
            )
            ->when($candidate->athlete_firstname, fn ($q) =>
            $q->where('firstName', $candidate->athlete_firstname)
            )
            ->when($candidate->athlete_birthdate, fn ($q) =>
            $q->where('birthdate', $candidate->athlete_birthdate)
            )
            ->limit(30)
            ->get();

        // mögliche Clubs nach Name
        $matchingClubs = ParaClub::query()
            ->when($candidate->club_name, fn ($q) =>
            $q->where('nameDe', 'LIKE', $candidate->club_name)
            )
            ->limit(30)
            ->get();

        return view('paraRecords.import_candidates.edit', compact(
            'candidate',
            'matchingAthletes',
            'matchingClubs'
        ));
    }

    /**
     * @throws Throwable
     */
    public function update(Request $request, ParaRecordImportCandidate $candidate)
    {
        $data = $request->validate([
            'para_athlete_id'    => ['nullable', 'integer', 'exists:para_athletes,id'],
            'para_club_id'       => ['nullable', 'integer', 'exists:para_clubs,id'],
            'create_new_athlete' => ['nullable', 'boolean'],
            'create_new_club'    => ['nullable', 'boolean'],
        ]);

        $createNewAthlete = (bool) ($data['create_new_athlete'] ?? false);
        $createNewClub    = (bool) ($data['create_new_club'] ?? false);

        DB::transaction(function () use ($candidate, $data, $createNewAthlete, $createNewClub) {
            // CLUB ermitteln/erzeugen
            $paraClub = null;

            if (! empty($data['para_club_id']) && ! $createNewClub) {
                $paraClub = ParaClub::find($data['para_club_id']);
            } elseif ($createNewClub) {
                $paraClub = $this->createClubFromCandidate($candidate);
            }

            // ATHLETE ermitteln/erzeugen
            $paraAthlete = null;

            if (! empty($data['para_athlete_id']) && ! $createNewAthlete) {
                $paraAthlete = ParaAthlete::find($data['para_athlete_id']);
            } elseif ($createNewAthlete) {
                $paraAthlete = $this->createAthleteFromCandidate($candidate, $paraClub);
            }

            // Wenn trotz allem noch was fehlt -> 422
            if (! $paraAthlete || ! $paraClub) {
                abort(422, 'Athlete und Club müssen zugeordnet oder neu angelegt werden.');
            }

            // eigentlichen Rekord erzeugen
            $record = ParaRecord::firstOrCreate(
                [
                    'record_list_name' => $candidate->record_list_name,
                    'record_type'      => $candidate->record_type,
                    'course'           => $candidate->course,
                    'gender'           => $candidate->gender,
                    'handicap'         => $candidate->handicap,
                    'distance'         => $candidate->distance,
                    'stroke'           => $candidate->stroke,
                    'relaycount'       => $candidate->relaycount,
                    'swimtime_ms'      => $candidate->swimtime_ms,
                    'swum_at'          => $candidate->swum_at,
                ],
                [
                    'sport_class'           => $candidate->sport_class,
                    'nation_id'             => $candidate->nation_id,
                    'recordlist_updated_at' => $candidate->recordlist_updated_at,

                    'status'                => $candidate->status,
                    'meet_name'             => $candidate->meet_name,
                    'meet_nation'           => $candidate->meet_nation,

                    'holder_firstname'      => $candidate->athlete_firstname,
                    'holder_lastname'       => $candidate->athlete_lastname,
                    'holder_year_of_birth'  => $candidate->athlete_birthdate
                        ? Carbon::parse($candidate->athlete_birthdate)->year
                        : null,

                    'is_relay'              => $candidate->is_relay,
                    'age_min'               => $candidate->age_min,
                    'age_max'               => $candidate->age_max,
                    'agegroup_code'         => $candidate->agegroup_code,

                    'para_athlete_id'       => $paraAthlete->id,
                    'para_club_id'          => $paraClub->id,
                ]
            );

            // SPLITS vom Kandidaten auf den Record übertragen
            ParaRecordSplit::where('para_record_id', $record->id)->delete();

            foreach ($candidate->splits as $split) {
                ParaRecordSplit::create([
                    'para_record_id' => $record->id,
                    'distance'       => $split->distance,
                    'order'          => $split->order,
                    'swimtime_ms'    => $split->swimtime_ms,
                ]);
            }

            // Kandidat updaten
            $candidate->update([
                'para_athlete_id'   => $paraAthlete->id,
                'para_club_id'      => $paraClub->id,
                'para_record_id'    => $record->id,
                'resolution_status' => 'resolved',
                'resolved_at'       => now(),
            ]);
        });

        return redirect()
            ->route('para-records.import-candidates.index')
            ->with('status', 'Kandidat erfolgreich aufgelöst und Rekord inkl. Splits angelegt.');
    }

    /**
     * Legt einen neuen Club aus den Kandidaten-Daten an.
     */
    protected function createClubFromCandidate(ParaRecordImportCandidate $candidate): ParaClub
    {
        $nation = $candidate->club_nation
            ? $this->nationResolver->fromLenexCode($candidate->club_nation)
            : $candidate->nation; // Relation aus Candidate

        return ParaClub::create([
            'nameDe'    => $candidate->club_name,
            'clubCode'  => $candidate->club_code,
            'swrid'     => $candidate->club_swrid,
            'nation_id' => $nation?->id,
        ]);
    }

    /**
     * Legt einen neuen Athleten aus den Kandidaten-Daten an.
     */
    protected function createAthleteFromCandidate(
        ParaRecordImportCandidate $candidate,
        ?ParaClub $paraClub
    ): ParaAthlete {
        $nation = $candidate->nation ?? $paraClub?->nation;

        return ParaAthlete::create([
            'firstName'     => $candidate->athlete_firstname,
            'lastName'      => $candidate->athlete_lastname,
            'gender'        => $candidate->athlete_gender,
            'birthdate'     => $candidate->athlete_birthdate,
            'oebsv_license' => $candidate->athlete_license,
            'swrid'         => $candidate->athlete_swrid,
            'tmId'          => $candidate->athlete_tmid,
            'nation_id'     => $nation?->id,
            'para_club_id'  => $paraClub?->id,
        ]);
    }
}
