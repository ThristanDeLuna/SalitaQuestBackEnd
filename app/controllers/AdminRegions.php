<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AdminRegions (Member B): the Admin screen for the regions of the map.
 * Full CRUD on the "regions" table. The Read side (list and one region, with word
 * counts and "ready") is the public GET /api/regions in the Regions controller,
 * so the Admin Regions screen reads that and has no list endpoint here.
 *
 *   POST   /api/admin/regions            Create  one region
 *   PUT    /api/admin/regions/{code}     Update  name, area, description, map position
 *   DELETE /api/admin/regions/{code}     Delete  one region (409 while it still has words)
 *
 * Admin only: require_role(['admin']) answers 401 without a token and 403 for another role.
 * Thin controller (criterion 3): the SQL is in Region_model and Word_model (criteria 4 and 5).
 */
class AdminRegions extends ApiController
{
    const NAME_MAX = 100;    // regions.name and regions.area are VARCHAR(100)
    const AREA_MAX = 100;
    const DESC_MAX = 1000;   // description is TEXT

    public function __construct()
    {
        parent::__construct();
        // Load every model here, before any work: each call->model() may open a connection (DEC-89)
        $this->call->model('Region_model');
        $this->call->model('Word_model');
    }

    // POST /api/admin/regions   body: code, name, area, description (optional), latitude, longitude
    public function create()
    {
        $this->api->require_method('POST');
        $this->require_role(['admin']);
        $b = $this->api->body();

        $code = strtolower($this->text($b, 'code'));
        // The code is the key other tables store and it never changes (DEC-36), so it is strict
        if ( ! preg_match('/^[a-z0-9_-]{1,32}$/', $code))
        {
            $this->api->respond_error('Code must be 1 to 32 characters: a to z, 0 to 9, - or _', 422);
        }
        [$name, $area, $description, $lat, $lng] = $this->read_fields($b);

        if ($this->Region_model->code_exists($code))
        {
            $this->api->respond_error('This region code already exists', 409);
        }
        $made = $this->Region_model->create($code, $name, $area, $description, $lat, $lng);
        if (isset($made['error']))   // someone added it between the check and the insert
        {
            $this->api->respond_error('This region code already exists', 409);
        }

        $this->api->respond($this->present($this->Region_model->find_by_code($code)), 201);
    }

    // PUT /api/admin/regions/{code}   body: name, area, description (optional), latitude, longitude
    public function update($code)
    {
        $this->api->require_method('PUT');
        $this->require_role(['admin']);
        $row = $this->find_region($code);
        $b   = $this->api->body();

        // The code never changes (DEC-36): refuse a different one instead of ignoring it silently
        if (isset($b['code']) && strtolower($this->text($b, 'code')) !== $row['code'])
        {
            $this->api->respond_error('The code of a region cannot be changed', 422);
        }
        [$name, $area, $description, $lat, $lng] = $this->read_fields($b);

        $this->Region_model->update_details($row['id'], $name, $area, $description, $lat, $lng);

        $this->api->respond($this->present($this->Region_model->find_by_code($row['code'])));
    }

    // DELETE /api/admin/regions/{code}
    public function remove($code)
    {
        $this->api->require_method('DELETE');
        $this->require_role(['admin']);
        $row = $this->find_region($code);

        // No foreign keys: this check is the only thing that stops orphan words
        if ($this->Word_model->count_in_region($row['code']) > 0)
        {
            $this->api->respond_error('This region still has words. Delete them first.', 409);
        }

        $this->Region_model->remove($row['id']);
        $this->api->respond(['message' => 'Region deleted']);
    }

    // ---------- private helpers (not routes) ----------

    // The region row, or a 404 that stops the request
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

    // A text field from the body, trimmed; '' when it is missing or not text
    private function text(array $b, $key)
    {
        return (isset($b[$key]) && is_string($b[$key])) ? trim($b[$key]) : '';
    }

    // Check and return [name, area, description, latitude, longitude] (422 with a clear message when wrong)
    private function read_fields(array $b)
    {
        $name = $this->text($b, 'name');
        $area = $this->text($b, 'area');
        $desc = $this->text($b, 'description');

        if ($name === '' || mb_strlen($name) > self::NAME_MAX)
        {
            $this->api->respond_error('Name must be 1 to ' . self::NAME_MAX . ' characters', 422);
        }
        if ($area === '' || mb_strlen($area) > self::AREA_MAX)
        {
            $this->api->respond_error('Area must be 1 to ' . self::AREA_MAX . ' characters', 422);
        }
        if (mb_strlen($desc) > self::DESC_MAX)
        {
            $this->api->respond_error('Description must be at most ' . self::DESC_MAX . ' characters', 422);
        }
        // is_numeric() also accepts "1e3" and " 5", so the value is range-checked after the cast
        $lat = $b['latitude']  ?? NULL;
        $lng = $b['longitude'] ?? NULL;
        if ( ! is_numeric($lat) || (float) $lat < -90 || (float) $lat > 90)
        {
            $this->api->respond_error('Latitude must be a number from -90 to 90', 422);
        }
        if ( ! is_numeric($lng) || (float) $lng < -180 || (float) $lng > 180)
        {
            $this->api->respond_error('Longitude must be a number from -180 to 180', 422);
        }

        return [$name, $area, ($desc === '' ? NULL : $desc), round((float) $lat, 6), round((float) $lng, 6)];
    }

    // One region as the API shows it (MySQL gives decimals as text, so they are cast)
    private function present(array $row)
    {
        return [
            'code'        => $row['code'],
            'name'        => $row['name'],
            'area'        => $row['area'],
            'description' => $row['description'],
            'latitude'    => (float) $row['latitude'],
            'longitude'   => (float) $row['longitude'],
        ];
    }
}
