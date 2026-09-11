<?php

namespace App\Support\Results;

/**
 * Deterministic **competition ranking** ("1224") for a result run's class
 * population: tied entries share the higher rank, and the next distinct value
 * skips ranks by the number of entries tied ahead of it — `1st, 2nd, 2nd, 4th`,
 * never `1st, 2nd, 2nd, 3rd`. See `docs/results-report-cards.md` §"Class
 * position".
 *
 * Ranking is always computed **within the population handed in** — a result
 * run's own roster — never across an entire school. Scores must already be
 * rounded to the precision they will be compared at (2 dp here) so floating
 * point noise never splits what should be an exact tie.
 */
final class RankingCalculator
{
    /**
     * @param  array<int, float>  $scoresByKey  key (e.g. student id) => score, higher is better
     * @return array<int, int> the same keys => 1-based competition rank
     */
    public static function rank(array $scoresByKey): array
    {
        if ($scoresByKey === []) {
            return [];
        }

        arsort($scoresByKey);

        $ranks = [];
        $seen = 0;
        $rank = 0;
        $previousScore = null;

        foreach ($scoresByKey as $key => $score) {
            $seen++;

            if ($previousScore === null || $score < $previousScore) {
                $rank = $seen;
                $previousScore = $score;
            }

            $ranks[$key] = $rank;
        }

        return $ranks;
    }
}
