<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Round_builder: picks the words for one game round in Salita Quest.
 *
 * Every method is a "pure function" in the same way as Game_rules: it uses only
 * its arguments and PHP's random functions. No database, no session, no clock.
 * The controller fetches the region's words, calls a method here, keeps the
 * answer key on the server, and sends only the round to the browser.
 *
 * Load in a controller with: $this->call->library('round_builder');
 * Then use:                  $this->round_builder->matching($words);
 */
class Round_builder
{
    /**
     * Build one Matching round: some "fish" (words) and some meanings.
     *
     * Targets are the words the player must match. Decoys are extra fish that
     * have no meaning on screen, so the player cannot win by elimination.
     *
     * Rules:
     *   - Targets are picked at random, and no two targets may share a meaning.
     *     Meanings are compared without case and outer spaces ("Love" = " love").
     *   - Decoys = min($max_decoys, number of words - $pairs). A decoy comes from
     *     the words not picked as targets, and its meaning must differ from every
     *     target's meaning. If too few words qualify, the round has fewer decoys.
     *   - The result never shows which meaning belongs to which word. The caller
     *     builds the answer key from its own rows: a fish is a target exactly when
     *     its translation is one of the meanings (decoys never share one).
     *
     * @param array $words      Rows with 'id', 'word', 'translation' and optional 'audio_url'.
     * @param int   $pairs      How many words to match (default 4).
     * @param int   $max_decoys Most decoy fish to add (default 2).
     * @return array 'words'    => targets and decoys, shuffled, each
     *                             ['word_id' => int, 'word' => string, 'audio_url' => string|null]
     *               'meanings' => the targets' translations only, shuffled
     * @throws InvalidArgumentException if there are fewer than $pairs words, or
     *                                  fewer than $pairs different meanings.
     */
    public function matching(array $words, $pairs = 4, $max_decoys = 2)
    {
        $pairs      = (int) $pairs;
        $max_decoys = max(0, (int) $max_decoys);

        if ($pairs < 1) {
            throw new InvalidArgumentException('A matching round needs at least 1 pair.');
        }
        if (count($words) < $pairs) {
            throw new InvalidArgumentException("A matching round needs at least $pairs words.");
        }
        foreach ($words as $w) {
            if (!is_array($w) || !isset($w['id'], $w['word'], $w['translation'])) {
                throw new InvalidArgumentException("Every word needs 'id', 'word' and 'translation'.");
            }
        }

        // Shuffle once, then walk the list: the first word of each new meaning
        // becomes a target until we have enough. Shuffling first keeps it random.
        $pool = array_values($words);
        shuffle($pool);

        $targets      = [];
        $target_keys  = []; // meaning key => true, to spot a meaning already taken
        $not_targets  = [];
        foreach ($pool as $w) {
            $key = $this->meaning_key($w['translation']);
            if (count($targets) < $pairs && !isset($target_keys[$key])) {
                $targets[]         = $w;
                $target_keys[$key] = true;
            } else {
                $not_targets[] = $w;
            }
        }

        if (count($targets) < $pairs) {
            throw new InvalidArgumentException("A matching round needs $pairs different meanings.");
        }

        // Decoys: only words whose meaning is NOT on screen, or the player could
        // match a decoy to a target's meaning and be marked wrong unfairly.
        $want   = min($max_decoys, count($words) - $pairs);
        $decoys = [];
        foreach ($not_targets as $w) {
            if (count($decoys) >= $want) {
                break;
            }
            if (!isset($target_keys[$this->meaning_key($w['translation'])])) {
                $decoys[] = $w;
            }
        }

        // The fish: only id, word and audio. The translation stays on the server.
        $fish = [];
        foreach (array_merge($targets, $decoys) as $w) {
            $fish[] = [
                'word_id'   => (int) $w['id'],
                'word'      => (string) $w['word'],
                'audio_url' => (isset($w['audio_url']) && $w['audio_url'] !== '') ? (string) $w['audio_url'] : null,
            ];
        }
        shuffle($fish);

        // The meanings: plain text in a separate shuffled list, with no word_id
        // next to them, so the order gives nothing away.
        $meanings = [];
        foreach ($targets as $w) {
            $meanings[] = (string) $w['translation'];
        }
        shuffle($meanings);

        return [
            'words'    => $fish,
            'meanings' => $meanings,
        ];
    }

    /**
     * The form of a meaning used to compare two meanings:
     * outer spaces removed and lower case, so "Love", "love " and "LOVE" are equal.
     */
    private function meaning_key($translation)
    {
        return mb_strtolower(trim((string) $translation), 'UTF-8');
    }
}
