<?php

namespace App\Services\Matching;

use App\Models\ParaAthlete;

class AthleteMatchService
{
    /**
     * Primary match: LENEX athleteid -> para_athletes.tmId
     */
    public function findByLenexAthleteId(?string $lenexAthleteId): ?ParaAthlete
    {
        $lenexAthleteId = trim((string) $lenexAthleteId);
        if ($lenexAthleteId === '' || !ctype_digit($lenexAthleteId)) {
            return null;
        }

        return ParaAthlete::query()
            ->where('tmId', (int) $lenexAthleteId)
            ->first();
    }

    /**
     * @return array<int, array{id:int, score:int, label:string}>
     */
    public function candidates(
        string $firstName,
        string $lastName,
        ?string $birthdate = null,   // YYYY-MM-DD
        ?string $gender = null,      // M/F/X
        ?int $clubId = null,
        int $limit = 8
    ): array {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if ($firstName === '' && $lastName === '') {
            return [];
        }

        $birthdate = $this->normalizeDate($birthdate);
        $gender = $gender ? strtoupper(trim($gender)) : null;

        // Query candidates (lightweight prefilter)
        $q = ParaAthlete::query()->with('club');

        if ($lastName !== '') {
            $ln = mb_strtolower($lastName);
            $q->whereRaw('LOWER(lastName) LIKE ?', ['%'.$this->escapeLike($ln).'%']);
        }

        if ($firstName !== '') {
            $fn = mb_strtolower($firstName);
            $q->whereRaw('LOWER(firstName) LIKE ?', ['%'.$this->escapeLike($fn).'%']);
        }

        // cap to avoid huge scans
        $rows = $q->limit(200)->get();

        $fnN = $this->normName($firstName);
        $lnN = $this->normName($lastName);

        $out = [];

        foreach ($rows as $a) {
            $score = 0;

            $aFnN = $this->normName((string) $a->firstName);
            $aLnN = $this->normName((string) $a->lastName);

            // last name is more important
            $score += (int) round($this->similarity01($lnN, $aLnN) * 55);
            $score += (int) round($this->similarity01($fnN, $aFnN) * 35);

            // birthdate
            $aDob = $a->birthdate ? $a->birthdate->toDateString() : null;
            if ($birthdate && $aDob) {
                if ($birthdate === $aDob) {
                    $score += 20;
                } else {
                    $by = (int) substr($birthdate, 0, 4);
                    $ay = (int) substr($aDob, 0, 4);
                    $diff = abs($by - $ay);
                    if ($diff === 0) {
                        $score += 10;
                    } elseif ($diff === 1) {
                        $score += 6;
                    } elseif ($diff === 2) {
                        $score += 3;
                    }
                }
            }

            // gender
            if ($gender && !empty($a->gender) && strtoupper((string) $a->gender) === $gender) {
                $score += 4;
            }

            // club affinity
            if ($clubId && !empty($a->para_club_id) && (int) $a->para_club_id === (int) $clubId) {
                $score += 8;
            }

            if ($score <= 0) {
                continue;
            }
            if ($score > 100) {
                $score = 100;
            }

            $clubLabel = $a->club?->shortNameDe ?? $a->club?->nameDe ?? '';
            $dobLabel = $aDob ? (' '.$aDob) : '';
            $label = trim($a->lastName.', '.$a->firstName.$dobLabel.($clubLabel ? " · {$clubLabel}" : ''));

            $out[] = [
                'id' => (int) $a->id,
                'score' => $score,
                'label' => $label,
            ];
        }

        usort($out, fn($x, $y) => $y['score'] <=> $x['score']);

        return array_slice($out, 0, $limit);
    }

    private function normalizeDate(?string $d): ?string
    {
        $d = trim((string) $d);
        if ($d === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        return null;
    }

    // -----------------

    private function escapeLike(string $s): string
    {
        // for SQL LIKE with default escape behaviour (basic)
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    private function normName(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);

        // remove accents if possible
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if (is_string($t) && $t !== '') {
            $s = $t;
        }

        $s = preg_replace('/[^a-z0-9]+/i', '', $s) ?: '';
        return $s;
    }

    private function similarity01(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        $max = max(mb_strlen($a), mb_strlen($b));
        if ($max === 0) {
            return 0.0;
        }
        $dist = levenshtein($a, $b);
        $sim = 1.0 - ($dist / $max);
        return max(0.0, min(1.0, $sim));
    }

    public function autoSelectIdFromCandidates(array $candidates, int $minScore = 85): ?int
    {
        if (empty($candidates)) {
            return null;
        }
        $best = $candidates[0];
        return (($best['score'] ?? 0) >= $minScore) ? (int) $best['id'] : null;
    }
}
