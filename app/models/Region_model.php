<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Region_model (Member B): the regions of the map (table "regions", migration 010).
 *
 * Queries only. Role checks, validation, and "is this region ready?" (DEC-71,
 * Game_rules::region_ready) belong to the controller, which combines this model
 * with Word_model (word count) and Audio_model (words with audio).
 *
 * Load in a controller with: $this->call->model('Region_model');
 * Then use:                  $this->Region_model->find_by_code('cebuano');
 */
class Region_model extends Model
{
    protected $table       = 'regions';
    protected $primary_key = 'id';
    // The app writes the date itself, in UTC (DEC-82); the framework must not add its own
    protected $timestamps = false;

    // Columns that insert() / update_where() may write; id is set by MySQL.
    protected $fillable = ['code', 'name', 'area', 'description', 'latitude', 'longitude', 'created_at'];

    public function __construct()
    {
        parent::__construct();
        // The database is not autoloaded (DEC-11); load it here so the model can query.
        // Each call->database() opens its own connection (D's test, DEC-89).
        $this->call->database();
    }

    /**
     * Create: add one region. (Criterion 5 Create, via built-in insert())
     *
     * regions.code is UNIQUE (regions_code_unique). Database::exec() rethrows the
     * MySQL error as a PDOException without the code, so the duplicate is
     * recognised from the message (1062), like Word_model and Audio_model.
     * The code must never change once used (DEC-36), so there is no way to edit it.
     *
     * @return array ['id' => new id] or ['error' => 'duplicate']
     */
    public function create($code, $name, $area, $description, $latitude, $longitude)
    {
        try
        {
            $id = $this->insert([
                'code'        => $code,
                'name'        => $name,
                'area'        => $area,
                'description' => ($description === '' ? NULL : $description),
                'latitude'    => $latitude,
                'longitude'   => $longitude,
                'created_at'  => gmdate('Y-m-d H:i:s'), // UTC, not the server's zone
            ]);
            return ['id' => (int) $id];
        }
        catch (PDOException $e)
        {
            if (strpos($e->getMessage(), '1062') !== FALSE) return ['error' => 'duplicate'];
            throw $e; // any other database error is a real failure
        }
    }

    /**
     * Read: all regions, in the order they were added (the Map and the admin list).
     * (Criterion 4 Query Builder)
     *
     * @return array list of rows (empty when none)
     */
    public function all_regions()
    {
        return $this->db->table($this->table)
                        ->order_by('id', 'ASC')
                        ->get_all() ?: [];
    }

    /**
     * Read: one region by its code, for example 'cebuano'. (Criterion 4, via built-in find_by())
     *
     * @return array|false the row, or FALSE when the code does not exist
     */
    public function find_by_code($code)
    {
        return $this->find_by('code', $code);
    }

    /**
     * Read: does this code exist? Used to answer 404 for an unknown region and 409
     * for a duplicate on create. (Criterion 4, via built-in exists())
     *
     * @return bool
     */
    public function code_exists($code)
    {
        return $this->exists(['code' => $code]);
    }

    /**
     * Update: change the name, area, description and map position of one region.
     * (Criterion 5 Update, via update_where())
     *
     * The code is NOT changed here (DEC-36): other tables store it.
     *
     * @return int rows changed (0 if the id does not exist or nothing changed)
     */
    public function update_details($id, $name, $area, $description, $latitude, $longitude)
    {
        return $this->update_where(
            ['id' => $id],
            [
                'name'        => $name,
                'area'        => $area,
                'description' => ($description === '' ? NULL : $description),
                'latitude'    => $latitude,
                'longitude'   => $longitude,
            ]
        );
    }

    /**
     * Delete: remove one region. (Criterion 5 Delete, via delete_where())
     * The controller must refuse (409) while the region still has words:
     * check Word_model::count_in_region() first. There are no foreign keys.
     *
     * @return int rows deleted (0 or 1)
     */
    public function remove($id)
    {
        return $this->delete_where(['id' => $id]);
    }
}