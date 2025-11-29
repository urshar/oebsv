<?php

namespace App\Http\Controllers;

use App\Models\Nation;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaEvent;
use App\Models\ParaMeet;
use App\Models\ParaAthlete;
use App\Services\Lenex\AthleteResolver;
use Illuminate\Http\Request;
use App\Services\AgegroupResolver;
use Illuminate\Support\Carbon;

class MeetAthleteController extends Controller
{
    public function __construct(
        protected AthleteResolver $athleteResolver,
        protected AgegroupResolver $agegroupResolver,
    ) {}


    /**
     * List of athletes with their entries for a selected meeting.
     */
    public function index(ParaMeet $meet)
    {
        // Alle Athleten, die mindestens eine Entry in diesem Meeting haben
        $athletes = ParaAthlete::whereHas('entries', function ($q) use ($meet) {
            $q->where('para_meet_id', $meet->id);
        })
            ->with([
                'nation',
                'club.nation',
                'entries' => function ($q) use ($meet) {
                    $q->where('para_meet_id', $meet->id)
                        ->with([
                            'event.swimstyle',
                            'event.session',
                            'agegroup',
                        ])
                        ->orderBy('para_event_id');
                },
            ])
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        return view('meets.athletes', compact('meet', 'athletes'));
    }

    /**
     * Formular: neuen Athleten für dieses Meeting anlegen.
     */
    public function create(ParaMeet $meet)
    {
        $athlete  = new ParaAthlete();

        $nations = Nation::orderBy('nameEn')->get();
        $clubs   = ParaClub::with('nation')->orderBy('nameDe')->get();

        return view('meets.athletes_create', compact('meet', 'athlete', 'nations', 'clubs'));
    }

    /**
     * Athleten speichern (oder bestehenden per Resolver finden) und
     * zur Event-Auswahl weiterleiten.
     */
    public function store(Request $request, ParaMeet $meet)
    {
        $data = $request->validate([
            'firstName'    => ['required', 'string', 'max:100'],
            'lastName'     => ['required', 'string', 'max:100'],
            'gender'       => ['nullable', 'string', 'max:1'],
            'birthdate'    => ['nullable', 'date'],
            'nation_id'    => ['nullable', 'exists:nations,id'],
            'para_club_id' => ['nullable', 'exists:para_clubs,id'],
            'license'      => ['nullable', 'string', 'max:50'],
        ]);

        $nation = !empty($data['nation_id'])
            ? Nation::find($data['nation_id'])
            : $meet->nation; // Fallback: Meeting-Nation

        $club = !empty($data['para_club_id'])
            ? ParaClub::find($data['para_club_id'])
            : null;

        $athlete = $this->athleteResolver->resolveFromData(
            $data['firstName'],
            $data['lastName'],
            $data['birthdate'] ?? null,
            $data['gender'] ?? null,
            $nation,
            $club,
            $data['license'] ?? null
        );

        return redirect()
            ->route('meets.athletes.entries.create', [$meet, $athlete])
            ->with('status', 'Athlet gespeichert. Bitte Events auswählen.');
    }

    /**
     * Formular: Events für Athlet wählen.
     * Es werden nur Events angezeigt, bei denen eine passende Agegroup existiert.
     */
    public function createEntries(ParaMeet $meet, ParaAthlete $athlete)
    {
        $meet->load(['sessions.events.agegroups', 'sessions.events.swimstyle']);

        $ageDate = $this->resolveAgeDateForMeet($meet);

        $eligible = [];

        if ($athlete->birthdate && $ageDate) {
            foreach ($meet->sessions as $session) {
                foreach ($session->events as $event) {
                    $agegroup = $this->agegroupResolver->resolve($event, $athlete, $ageDate);
                    if ($agegroup) {
                        $eligible[] = [
                            'session'  => $session,
                            'event'    => $event,
                            'agegroup' => $agegroup,
                        ];
                    }
                }
            }
        }

        return view('meets.athlete_entries_create', [
            'meet'          => $meet,
            'athlete'       => $athlete,
            'eligibleEvents'=> $eligible,
        ]);
    }

    /**
     * Ausgewählte Events speichern -> ParaEntry-Datensätze anlegen.
     */
    public function storeEntries(Request $request, ParaMeet $meet, ParaAthlete $athlete)
    {
        // entries[event_id] = agegroup_id
        $entries = $request->input('entries', []);

        if (empty($entries)) {
            return redirect()
                ->route('meets.athletes.index', $meet)
                ->with('status', 'Keine Events ausgewählt.');
        }

        $eventIds = array_map('intval', array_keys($entries));

        $events = ParaEvent::with('session')
            ->whereIn('id', $eventIds)
            ->get()
            ->keyBy('id');

        foreach ($entries as $eventId => $agegroupId) {
            $eventId     = (int) $eventId;
            $agegroupId  = $agegroupId ? (int) $agegroupId : null;

            if (!isset($events[$eventId])) {
                continue;
            }

            $event   = $events[$eventId];
            $session = $event->session;

            ParaEntry::updateOrCreate(
                [
                    'para_event_id'   => $event->id,
                    'para_athlete_id' => $athlete->id,
                ],
                [
                    'para_meet_id'           => $meet->id,
                    'para_session_id'        => $session?->id,
                    'para_event_agegroup_id' => $agegroupId,
                    'para_club_id'           => $athlete->para_club_id,

                    'lenex_athleteid'        => null,
                    'lenex_eventid'          => null,

                    'entry_time'             => null,
                    'entry_time_ms'          => null,
                    'course'                 => null,
                    'qualifying_date'        => null,
                    'qualifying_meet_name'   => null,
                    'qualifying_city'        => null,
                    'qualifying_nation'      => null,
                ]
            );
        }

        return redirect()
            ->route('meets.athletes.index', $meet)
            ->with('status', 'Meldungen für Athleten wurden gespeichert.');
    }

    /**
     * Wählt ein Datum, an dem das Alter berechnet wird.
     * Fallback: from_date → frühestes Session-Datum → today.
     */
    protected function resolveAgeDateForMeet(ParaMeet $meet): ?Carbon
    {
        if ($meet->from_date) {
            return Carbon::parse($meet->from_date);
        }

        $firstSessionDate = $meet->sessions
            ->filter(fn ($s) => $s->date)
            ->min('date');

        if ($firstSessionDate) {
            return Carbon::parse($firstSessionDate);
        }

        return Carbon::now();
    }

    public function results(ParaMeet $meet, ParaAthlete $athlete)
    {
        $entries = $athlete->entries()
            ->where('para_meet_id', $meet->id)
            ->with([
                'event.swimstyle',
                'results.splits',
            ])
            ->get();

        return view('meets.athletes.results', compact('meet', 'athlete', 'entries'));
    }
}
