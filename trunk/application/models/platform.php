<?php
/**
 * Platform model
 *
 * @package    PluginDir
 * @subpackage models
 * @author     l.m.orchard <lorchard@mozilla.com>
 */
class Platform_Model extends ORM {

    public static $defaults = array(
        'app_id' => '*',
        'app_release' => '*',
        'app_version' => '*',
        'locale' => '*'
    );
    
    /**
     * Find all, each row associated with release count.
     *
     * @return array List of name / count arrays.
     */
    public function find_release_counts()
    {
        return $this->db->query("
            SELECT count(plugin_releases.id) AS count, platforms.*
            FROM platforms
            JOIN plugin_releases ON platforms.id = plugin_releases.platform_id
            JOIN plugins ON plugin_releases.plugin_id = plugins.id
            WHERE plugins.sandbox_profile_id IS NULL
            GROUP BY platforms.id
            ORDER BY count DESC
        ")->result_array();
    }

}
