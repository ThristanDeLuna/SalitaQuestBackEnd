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
 *                            $this->round_builder->listening($words);
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
     * Build one Listening round: the player hears a word and picks its meaning
     * from a few options (DEC-90, DEC-91).
     *
     * Rules:
     *   - Questions use only words with audio (a non-empty audio_url), picked at
     *     random, never the same word twice.
     *   - Each question has $options options: the word's own translation once,
     *     plus wrong options from the translations of other words of the region.
     *     Words without audio may give wrong options. Meanings are compared without
     *     case and outer spaces, so no two options of a question mean the same.
     *   - Options are shuffled. The result never shows the word text or which
     *     option is right. The caller builds the answer key from its own rows:
     *     the right option is the translation of the question's word_id.
     *
     * @param array $words     Rows with 'id', 'word', 'translation' and optional 'audio_url'
     *                         (all the words of ONE region, same shape as matching()).
     * @param int   $questions How many questions (default 5).
     * @param int   $options   How many options per question (default 4).
     * @return array 'questions' => list of
     *                 ['word_id' => int, 'audio_url' => string, 'options' => [string, ...]]
     * @throws InvalidArgumentException if fewer than $questions words have audio, or
     *                                  fewer than $options different meanings.
     */
    public function listening(array $words, $questions = 5, $options = 4)
    {
        $questions = (int) $questions;
        $options   = (int) $options;

        if ($questions < 1) {
            throw new InvalidArgumentException('A listening round needs at least 1 question.');
        }
        if ($options < 2) {
            throw new InvalidArgumentException('A listening question needs at least 2 options.');
        }
        foreach ($words as $w) {
            if (!is_array($w) || !isset($w['id'], $w['word'], $w['translation'])) {
                throw new InvalidArgumentException("Every word needs 'id', 'word' and 'translation'.");
            }
        }

        // Shuffle once: the questions and the text used for each meaning are random.
        $pool = array_values($words);
        shuffle($pool);

        // One text per different meaning (meaning key => text), for the wrong options.
        $meaning_text = [];
        foreach ($pool as $w) {
            $key = $this->meaning_key($w['translation']);
            if (!isset($meaning_text[$key])) {
                $meaning_text[$key] = (string) $w['translation'];
            }
        }
        // The word's own meaning is one of these, so every question has
        // count - 1 wrong meanings to choose from.
        if (count($meaning_text) < $options) {
            throw new InvalidArgumentException("A listening round needs $options different meanings.");
        }

        // The questions: only words the player can hear.
        $heard = [];
        foreach ($pool as $w) {
            if (isset($w['audio_url']) && trim((string) $w['audio_url']) !== '') {
                $heard[] = $w;
            }
        }
        if (count($heard) < $questions) {
            throw new InvalidArgumentException("A listening round needs at least $questions words with audio.");
        }

        $round = [];
        foreach (array_slice($heard, 0, $questions) as $w) {
            // Wrong options: any other meaning. Leaving out the right meaning's key
            // also leaves out words that mean the same ("Love" vs " love").
            $wrong = $meaning_text;
            unset($wrong[$this->meaning_key($w['translation'])]);
            $wrong = array_values($wrong);
            shuffle($wrong);

            $choices   = array_slice($wrong, 0, $options - 1);
            $choices[] = (string) $w['translation'];
            shuffle($choices); // so the right one is not always last

            // Only id, audio and options. The word text and translation stay on the server.
            $round[] = [
                'word_id'   => (int) $w['id'],
                'audio_url' => (string) $w['audio_url'],
                'options'   => $choices,
            ];
        }

        return ['questions' => $round];
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
