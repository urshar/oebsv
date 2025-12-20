<?php

namespace App\Services\Matching;

use App\Models\ParaClub;

class ClubMatchService
{
    public function findByLenexClubId(?string $lenexClubId): ?ParaClub
    {
        $lenexClubId = trim((string) $lenexClubId);
        if ($lenexClubId === '' || !ctype_digit($lenexClubId)) {
            return null;
        }

        return ParaClub::query()
            ->where('tmId', (int) $lenexClubId)
            ->first();
    }

    /**
     * @return array<int, array{id:int, score:int, label:string}>
     */
    public function candidates(
        string $clubName,
        ?string $shortName = null,
        int $limit = 10
    ): array {
        $clubName = trim($clubName);
        $shortName = trim((string) $shortName);

        if ($clubName === '' && $shortName === '') {
            return [];
        }

        // Prefilter: LIKE auf Name/Short/Alt
        $q = ParaClub::query();

        if ($clubName !== '') {
            $n = mb_strtolower($clubName);
            $q->where(function ($w) use ($n) {
                $w->whereRaw('LOWER(nameDe) LIKE ?', ['%'.$this->escapeLike($n).'%'])
                    ->orWhereRaw('LOWER(nameEn) LIKE ?', ['%'.$this->escapeLike($n).'%'])
                    ->orWhereRaw('LOWER(altNameDe) LIKE ?', ['%'.$this->escapeLike($n).'%'])
                    ->orWhereRaw('LOWER(altNameEn) LIKE ?', ['%'.$this->escapeLike($n).'%']);
            });
        }

        if ($shortName !== '') {
            $s = mb_strtolower($shortName);
            $q->orWhere(function ($w) use ($s) {
                $w->whereRaw('LOWER(shortNameDe) LIKE ?', ['%'.$this->escapeLike($s).'%'])
                    ->orWhereRaw('LOWER(shortNameEn) LIKE ?', ['%'.$this->escapeLike($s).'%'])
                    ->orWhereRaw('LOWER(altShortNameDe) LIKE ?', ['%'.$this->escapeLike($s).'%'])
                    ->orWhereRaw('LOWER(altShortNameEn) LIKE ?', ['%'.$this->escapeLike($s).'%']);
            });
        }

        $rows = $q->limit(250)->get();

        $nameN = $this->norm($clubName);
        $shortN = $this->norm($shortName);

        $out = [];

        foreach ($rows as $c) {
            $bestNameSim = max(
                $this->similarity01($nameN, $this->norm((string) $c->nameDe)),
                $this->similarity01($nameN, $this->norm((string) $c->nameEn)),
                $this->similarity01($nameN, $this->norm((string) $c->altNameDe)),
                $this->similarity01($nameN, $this->norm((string) $c->altNameEn)),
            );

            $bestShortSim = max(
                $this->similarity01($shortN, $this->norm((string) $c->shortNameDe)),
                $this->similarity01($shortN, $this->norm((string) $c->shortNameEn)),
                $this->similarity01($shortN, $this->norm((string) $c->altShortNameDe)),
                $this->similarity01($shortN, $this->norm((string) $c->altShortNameEn)),
            );

            $score = 0;

            if ($clubName !== '') {
                $score += (int) round($bestNameSim * 70);
                if (mb_strtolower($clubName) === mb_strtolower((string) $c->nameDe)) {
                    $score += 10; // exact boost
                }
            }

            if ($shortName !== '') {
                $score += (int) round($bestShortSim * 25);
            }

            if ($score > 100) {
                $score = 100;
            }
            if ($score <= 0) {
                continue;
            }

            $label = (string) $c->nameDe;
            $short = $c->shortNameDe ?: $c->shortNameEn;
            if (!empty($short)) {
                $label .= " ({$short})";
            }
            if (!empty($c->tmId)) {
                $label .= " · tmId {$c->tmId}";
            }

            $out[] = [
                'id' => (int) $c->id,
                'score' => $score,
                'label' => $label,
            ];
        }

        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($out, 0, $limit);
    }

    private function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    // ---------- helpers ----------

    private function norm(string $s): string
    {
        $s = trim(mb_strtolower($s));
        if ($s === '') {
            return '';
        }
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);

        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if (is_string($t) && $t !== '') {
            $s = $t;
        }

        return preg_replace('/[^a-z0-9]+/i', '', $s) ?: '';
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

    public function autoSelectIdFromCandidates(array $candidates, int $minScore = 88): ?int
    {
        if (empty($candidates)) {
            return null;
        }
        $best = $candidates[0];
        return (($best['score'] ?? 0) >= $minScore) ? (int) $best['id'] : null;
    }
}
