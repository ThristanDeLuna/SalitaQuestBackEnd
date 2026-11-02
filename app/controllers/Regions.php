<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Regions (Member B): the public, read-only endpoints for the Map and the word list.
 *
 *   GET /api/regions                  Read  every region, with word counts and "ready"
 *   GET /api/regions/{code}           Read  one region (404 if the code is unknown)
 *   GET /api/regions/{code}/words     Read  the words of one region, with audio URL or null
 *
 * Public on purpose: signed-out visitors can browse (CF-04, DEC-46), so there is
 * no require_login(). Thin controller (criterion 3): the SQL is in Region_model and
 * Word_model (criteria 4 and 5); the 4-words / 5-audio rule is Game_rules (DEC-71).
 */
class Regions extends ApiController
{
    // GET /api/regions
    public function index()
    {
        $this->api->require_method('GET');
        $this->call->model('Region_model');
        $this->call->model('Word_model');
        $this->call->library('game_rules');

        // One query for all words; the counts per region are made in PHP
        $counts = $this->count_by_region($this->Word_model->with_audio());

        $out = [];
        foreach ($this->Region_model->all_regions() as $row)
        {
            $out[] = $this->present($row, $counts);
        }
        $this->api->respond(['data' => $out]);
    }

    // GET /api/regions/{code}
    public function show($code)
    {
        $this->api->require_method('GET');
        $this->call->model('Region_model');
        $this->call->model('Word_model');
        $this->call->library('game_rules');

        $row = $this->find_region($code);
        $counts = $this->count_by_region($this->Word_model->with_audio($row['code']));
        $this->api->respond($this->present($row, $counts));
    }

    // GET /api/regions/{code}/words
    public function words($code)
    {
        $this->api->require_method('GET');
        $this->call->model('Region_model');
        $this->call->model('Word_model');

        $row = $this->find_region($code);

        $list = [];
        foreach ($this->Word_model->with_audio($row['code']) as $w)
        {
            $list[] = [
                'id'               => (int) $w['id'],
                'word'             => $w['word'],
                'translation'      => $w['translation'],
                'example_sentence' => $w['example_sentence'],   // NULL when there is none
                'audio_url'        => $w['audio_url'],          // NULL when there is no recording
            ];
        }
        $this->api->respond(['region' => $row['code'], 'data' => $list]);
    }

    // The region row, or a 404 that stops the request. Private: not a route.
    private function find_region($code)
    {
        $code = is_string($code) ? strtolower(trim($code)) : '';
        $row  = preg_match('/^[a-z0-9_-]{1,32}$/', $code) ? $this->Region_model->find_by_code($code) : FALSE;
        if ( ! $row)
        {
            $this->api->respond_error('Region not found', 404);
        }
        return $row;
    }

    // [region_code => ['words' => n, 'audio' => m]] from the rows of Word_model::with_audio()
    private function count_by_region(array $rows)
    {
        $counts = [];
        foreach ($rows as $r)
        {
            $c = $r['region_code'];
            if ( ! isset($counts[$c])) $counts[$c] = ['words' => 0, 'audio' => 0];
            $counts[$c]['words']++;
            if ($r['audio_url'] !== NULL) $counts[$c]['audio']++;
        }
        return $counts;
    }

    // One region as the API shows it (numbers are real numbers; MySQL gives decimals as text)
    private function present(array $row, array $counts)
    {
        $c = isset($counts[$row['code']]) ? $counts[$row['code']] : ['words' => 0, 'audio' => 0];
        return [
            'code'             => $row['code'],
            'name'             => $row['name'],
            'area'             => $row['area'],
            'description'      => $row['description'],
            'latitude'         => (float) $row['latitude'],
            'longitude'        => (float) $row['longitude'],
            'word_count'       => $c['words'],
            'audio_word_count' => $c['audio'],
            'ready'            => $this->game_rules->region_ready($c['words'], $c['audio']),
        ];
    }
}