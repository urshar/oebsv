<?php

use App\Http\Controllers\MeetAthleteController;
use App\Http\Controllers\ParaAthleteClassificationController;
use App\Http\Controllers\ParaAthleteController;
use App\Http\Controllers\ParaClassifierController;
use App\Http\Controllers\ParaEventAgegroupController;
use App\Http\Controllers\ParaEventController;
use App\Http\Controllers\ParaMeetController;
use App\Http\Controllers\ParaSessionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContinentController;
use App\Http\Controllers\NationController;
use App\Http\Controllers\LenexImportController;

Route::get('/', function () {
    return redirect()->route('nations.index');
})->name('home');

// Continents CRUD
Route::resource('continents', ContinentController::class);

// Nations CRUD
Route::resource('nations', NationController::class);

// LENEX upload (meeting structure)
Route::get('/lenex/upload', [LenexImportController::class, 'create'])
    ->name('lenex.upload.form');

Route::post('/lenex/upload', [LenexImportController::class, 'store'])
    ->name('lenex.upload.store');

// Meetings main
Route::resource('meets', ParaMeetController::class);

Route::get('/meets/{meet}/results', [ParaMeetController::class, 'results'])
    ->name('meets.results');

// Nested structure (shallow so edit URLs are short)
Route::resource('meets.sessions', ParaSessionController::class)->shallow();
Route::resource('sessions.events', ParaEventController::class)->shallow();
Route::resource('events.agegroups', ParaEventAgegroupController::class)->shallow();

Route::get('/meets/{meet}/lenex/entries', [LenexImportController::class, 'createEntries'])
    ->name('lenex.entries.form');

Route::post('/meets/{meet}/lenex/entries', [LenexImportController::class, 'storeEntries'])
    ->name('lenex.entries.store');

Route::delete('/meets/{meet}/entries', [ParaMeetController::class, 'destroyEntries'])
    ->name('meets.entries.destroy');

Route::get('/meets/{meet}/athletes', [MeetAthleteController::class, 'index'])
    ->name('meets.athletes.index');

Route::get('/meets/{meet}/athletes/create', [MeetAthleteController::class, 'create'])
    ->name('meets.athletes.create');

Route::post('/meets/{meet}/athletes', [MeetAthleteController::class, 'store'])
    ->name('meets.athletes.store');

// NEU: Events für Athlet auswählen (nur Events mit passender Agegroup)
Route::get('/meets/{meet}/athletes/{athlete}/entries/create', [MeetAthleteController::class, 'createEntries'])
    ->name('meets.athletes.entries.create');

Route::post('/meets/{meet}/athletes/{athlete}/entries', [MeetAthleteController::class, 'storeEntries'])
    ->name('meets.athletes.entries.store');

Route::resource('athletes', ParaAthleteController::class);

// Nested CRUD for classification history
Route::resource('athletes.classifications', ParaAthleteClassificationController::class)->shallow();

Route::resource('classifiers', ParaClassifierController::class);

Route::get('/meets/{meet}/lenex/results', [LenexImportController::class, 'createResults'])
    ->name('lenex.results.form');

Route::post('/meets/{meet}/lenex/results', [LenexImportController::class, 'storeResults'])
    ->name('lenex.results.store');

Route::get('/meets/{meet}/athletes/{athlete}/results', [MeetAthleteController::class, 'results'])
    ->name('meets.athletes.results');

Route::get('/athletes/{athlete}/best-times', [ParaAthleteController::class, 'bestTimes'])
    ->name('athletes.best-times');

Route::get('/meets/{meet}/lenex/results', [LenexImportController::class, 'createResults'])
    ->name('lenex.results.form');

Route::post('/meets/{meet}/lenex/results', [LenexImportController::class, 'storeResults'])
    ->name('lenex.results.store');
