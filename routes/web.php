<?php

use App\Http\Controllers\ContinentController;
use App\Http\Controllers\LenexImportController;
use App\Http\Controllers\LenexMeetResultsWizardController;
use App\Http\Controllers\LenexRelayImportController;
use App\Http\Controllers\MeetAthleteController;
use App\Http\Controllers\NationController;
use App\Http\Controllers\ParaAthleteClassificationController;
use App\Http\Controllers\ParaAthleteController;
use App\Http\Controllers\ParaClassifierController;
use App\Http\Controllers\ParaEventAgegroupController;
use App\Http\Controllers\ParaEventController;
use App\Http\Controllers\ParaMeetController;
use App\Http\Controllers\ParaRecordImportCandidateController;
use App\Http\Controllers\ParaRecordImportController;
use App\Http\Controllers\ParaRelayEntryController;
use App\Http\Controllers\ParaRelayLegSplitController;
use App\Http\Controllers\ParaRelayMemberController;
use App\Http\Controllers\ParaRelayResultController;
use App\Http\Controllers\ParaRelaySplitController;
use App\Http\Controllers\ParaSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Startseite
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('nations.index');
})->name('home');

/*
|--------------------------------------------------------------------------
| Stammdaten: Kontinente & Nationen
|--------------------------------------------------------------------------
*/

Route::resource('continents', ContinentController::class);
Route::resource('nations', NationController::class);

/*
|--------------------------------------------------------------------------
| LENEX – Meeting-Struktur (ohne konkretes Meet)
|--------------------------------------------------------------------------
*/

Route::prefix('lenex')
    ->name('lenex.')
    ->group(function () {
        Route::get('upload', [LenexImportController::class, 'create'])
            ->name('upload.form');

        Route::post('upload', [LenexImportController::class, 'store'])
            ->name('upload.store');

        Route::get('meet-wizard', [LenexImportController::class, 'create'])
            ->name('meet-wizard.form');

        Route::post('meet-wizard', [LenexImportController::class, 'store'])
            ->name('meet-wizard.store');
    });

/*
|--------------------------------------------------------------------------
| Meetings & LENEX (entries/results) & Athleten je Meeting
|--------------------------------------------------------------------------
*/

Route::resource('meets', ParaMeetController::class);

// Ergebnisse-Übersicht eines Meetings
Route::get('meets/{meet}/results', [ParaMeetController::class, 'results'])
    ->name('meets.results');

// Alles was an ein konkretes Meet gebunden ist:
Route::prefix('meets/{meet}')
    ->name('meets.')
    ->group(function () {

        /*
        |--------------------------------------------------------------
        | LENEX Entries für ein bestehendes Meet
        |--------------------------------------------------------------
        */
        Route::prefix('lenex')
            ->name('lenex.')
            ->group(function () {
                // Entries-Import (Form + Verarbeitung)
                Route::get('entries', [LenexImportController::class, 'createEntries'])
                    ->name('entries.form');

                Route::post('entries', [LenexImportController::class, 'storeEntries'])
                    ->name('entries.store');

                // Ergebnisse-Import (3-stufig: Upload -> Preview -> Import)
                Route::get('results', [LenexImportController::class, 'createResults'])
                    ->name('results.form');        // Upload-Formular

                Route::post('results/preview', [LenexImportController::class, 'previewResults'])
                    ->name('results.preview');     // Datei + Vorauswahl (Nation/Verein/Athlet)

                Route::post('results/import', [LenexImportController::class, 'importResults'])
                    ->name('results.import');      // Bestätigung + eigentlicher Import

                // RELAYS-Import (3-stufig: Upload -> Preview -> Import)
                Route::get('relays', [LenexRelayImportController::class, 'create'])
                    ->name('relays.form');

                Route::post('relays/preview', [LenexRelayImportController::class, 'preview'])
                    ->name('relays.preview');

                Route::post('relays/import', [LenexRelayImportController::class, 'import'])
                    ->name('relays.import');

                // NEU: Kombinierter Wizard
                Route::get('results-wizard', [LenexMeetResultsWizardController::class, 'create'])
                    ->name('results-wizard.form');

                Route::post('results-wizard/preview', [LenexMeetResultsWizardController::class, 'preview'])
                    ->name('results-wizard.preview');

                Route::post('results-wizard/import', [LenexMeetResultsWizardController::class, 'import'])
                    ->name('results-wizard.import');

                Route::get('meet', [LenexImportController::class, 'create'])
                    ->name('meet-wizard.form');

                Route::post('meet', [LenexImportController::class, 'store'])
                    ->name('meet-wizard.store');

                // Alias für Navbar: Entries Wizard (Meet-Kontext)
                Route::get('entries-wizard', [LenexImportController::class, 'createEntries'])
                    ->name('entries-wizard.form');

                Route::post('entries-wizard', [LenexImportController::class, 'storeEntries'])
                    ->name('entries-wizard.store');

            });

        // ✅ Alias für Navbar: Entries Wizard
        Route::get('entries-wizard', [LenexImportController::class, 'createEntries'])
            ->name('entries-wizard.form');

        Route::post('entries-wizard', [LenexImportController::class, 'storeEntries'])
            ->name('entries-wizard.store');

        /*
            |--------------------------------------------------------------
            | Relays (Staffeln) – CRUD
            |--------------------------------------------------------------
            */

        // Relay Entries (index/create/store/show/edit/update/destroy)
        Route::resource('relay-entries', ParaRelayEntryController::class);

        // Relay Results (Team-Endzeit etc.)
        // create/store nested unter relay-entries, edit/update/destroy "shallow"
        Route::resource('relay-entries.relay-results', ParaRelayResultController::class)
            ->shallow()
            ->only(['create', 'store', 'edit', 'update', 'destroy']);

        // Relay Members (Teilnehmer/Legs)
        // create/store nested unter relay-entries, edit/update/destroy "shallow"
        Route::resource('relay-entries.relay-members', ParaRelayMemberController::class)
            ->shallow()
            ->only(['create', 'store', 'edit', 'update', 'destroy']);

        // Team-Splits (roh, kumuliert ab Start) – nested unter relay-results
        Route::resource('relay-results.relay-splits', ParaRelaySplitController::class)
            ->shallow()
            ->only(['index', 'store', 'edit', 'update', 'destroy']);

        // Leg-Splits (pro Teilnehmer/Leg) – nested unter relay-members
        Route::resource('relay-members.relay-leg-splits', ParaRelayLegSplitController::class)
            ->shallow()
            ->only(['index', 'store', 'edit', 'update', 'destroy']);

        /*
        |--------------------------------------------------------------
        | Meldungen (Entries) – komplett löschen
        |--------------------------------------------------------------
        */
        Route::delete('entries', [ParaMeetController::class, 'destroyEntries'])
            ->name('entries.destroy');

        /*
        |--------------------------------------------------------------
        | Athleten in einem konkreten Meet
        |--------------------------------------------------------------
        */
        Route::prefix('athletes')
            ->name('athletes.')
            ->group(function () {
                // Liste / Anlegen von Athleten eines Meets
                Route::get('/', [MeetAthleteController::class, 'index'])
                    ->name('index');

                Route::get('create', [MeetAthleteController::class, 'create'])
                    ->name('create');

                Route::post('/', [MeetAthleteController::class, 'store'])
                    ->name('store');

                // Ergebnisse eines bestimmten Athleten in diesem Meet
                Route::get('{athlete}/results', [MeetAthleteController::class, 'results'])
                    ->name('results');

                // Events für Athlet in diesem Meet auswählen (nur passende Agegroup)
                Route::get('{athlete}/entries/create', [MeetAthleteController::class, 'createEntries'])
                    ->name('entries.create');

                Route::post('{athlete}/entries', [MeetAthleteController::class, 'storeEntries'])
                    ->name('entries.store');
            });
    });

/*
|--------------------------------------------------------------------------
| Athleten (global) & Klassifikationen
|--------------------------------------------------------------------------
*/

// Athleten-Stammdaten
Route::resource('athletes', ParaAthleteController::class);

// Bestzeiten eines Athleten
Route::get('athletes/{athlete}/best-times', [ParaAthleteController::class, 'bestTimes'])
    ->name('athletes.best-times');

// Klassifikations-Historie (nested, shallow)
Route::resource('athletes.classifications', ParaAthleteClassificationController::class)
    ->shallow();

/*
|--------------------------------------------------------------------------
| Klassifizierer
|--------------------------------------------------------------------------
*/

Route::resource('classifiers', ParaClassifierController::class);

/*
|--------------------------------------------------------------------------
| Sitzungen / Events / Agegroups (nested, shallow)
|--------------------------------------------------------------------------
*/

Route::resource('meets.sessions', ParaSessionController::class)
    ->shallow();

Route::resource('sessions.events', ParaEventController::class)
    ->shallow();

Route::resource('events.agegroups', ParaEventAgegroupController::class)
    ->shallow();

/*
|--------------------------------------------------------------------------
| Para-Records & Import-Kandidaten
|--------------------------------------------------------------------------
*/

Route::prefix('para-records')
    ->name('para-records.')
    ->group(function () {

        // Übersicht der Records
        Route::get('/', [ParaRecordImportController::class, 'index'])
            ->name('index');

        // Import (Datei hochladen + verarbeiten)
        Route::get('import', [ParaRecordImportController::class, 'create'])
            ->name('import.create');

        Route::post('import', [ParaRecordImportController::class, 'store'])
            ->name('import.store');

        // Import-Kandidaten (Review / Übernahme)
        Route::prefix('import-candidates')
            ->name('import-candidates.')
            ->group(function () {
                Route::get('/', [ParaRecordImportCandidateController::class, 'index'])
                    ->name('index');

                Route::get('{candidate}', [ParaRecordImportCandidateController::class, 'edit'])
                    ->name('edit');

                Route::post('{candidate}', [ParaRecordImportCandidateController::class, 'update'])
                    ->name('update');
            });
    });
