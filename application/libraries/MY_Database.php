<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Database customizations for this project
 *
 * @package    PluginDir
 * @subpackage libraries
 * @author     l.m.orchard <lorchard@mozilla.com>
 */
class Database extends Database_Core {

    /** Global flag whether or not to use shadow DB for reads */
    public static $enable_shadow = true;

    /**
     * Globally disable use of the shadow database for reads
     */
    public static function disable_read_shadow()
    {
        self::$enable_shadow = false;
    }

    /**
     * Globally enable use of the shadow database for reads
     */
    public static function enable_read_shadow()
    {
        self::$enable_shadow = true;
    }

    /**
     * Runs a query into the driver and returns the result.
     *
     * @param   string  SQL query to execute
     * @return  Database_Result
     */
    public function query($sql = '')
    {
        if ($sql == '') return FALSE;

        // If read shadow is enabled, and defined in config, and this 
        // particular SQL query is not a write, try using the shadow DB 
        // instance.
        if (self::$enable_shadow && isset($this->config['read_shadow']) && 
                !preg_match('#\b(?:INSERT|UPDATE|REPLACE|SET|DELETE|TRUNCATE)\b#i', $sql)) {
            $shadow_db = Database::instance($this->config['read_shadow']);
            return $shadow_db->query($sql);
        }

        // No link? Connect!
        $this->link or $this->connect();

        // Start the benchmark
        $start = microtime(TRUE);

        if (func_num_args() > 1) //if we have more than one argument ($sql)
        {
            $argv = func_get_args();
            $binds = (is_array(next($argv))) ? current($argv) : array_slice($argv, 1);
        }

        // Compile binds if needed
        if (isset($binds))
        {
            $sql = $this->compile_binds($sql, $binds);
        }

        // Fetch the result
        $result = $this->driver->query($this->last_query = $sql);

        // Stop the benchmark
        $stop = microtime(TRUE);

        if ($this->config['benchmark'] == TRUE)
        {
            // Benchmark the query
            Database::$benchmarks[] = array('query' => $sql, 'time' => $stop - $start, 'rows' => count($result));
        }

        return $result;
    }

    /**
     * Selects the or where(s) for a database query.
     *
     * Tweaked to wrap the set of OR clauses in parentheses, AND'ed with the 
     * rest of the where clauses.  Only tested with MySQL so far.
     *
     * @param   string|array  key name or array of key => value pairs
     * @param   string        value to match with key
     * @param   boolean       disable quoting of WHERE clause
     * @return  Database_Core        This Database object.
     */
    public function orwhere($key, $value = NULL, $quote = TRUE)
    {
        $quote = (func_num_args() < 2 AND ! is_array($key)) ? -1 : $quote;
        if (is_object($key))
        {
            $keys = array((string) $key => '');
        }
        elseif ( ! is_array($key))
        {
            $keys = array($key => $value);
        }
        else
        {
            $keys = $key;
        }

        $sub_where = array();
        foreach ($keys as $key => $value)
        {
            $key         = (strpos($key, '.') !== FALSE) ? $this->config['table_prefix'].$key : $key;
            $sub_where[] = $this->driver->where($key, $value, 'OR ', count($sub_where), $quote);
        }
        $this->where[] =
            ( count($this->where) ? 'AND ' : '' ) . 
            '( ' .  implode(' ', $sub_where) .  ' )';

        return $this;
    }

	/**
	 * Selects the or like(s) for a database query.
	 *
	 * @param   string|array  field name or array of field => match pairs
	 * @param   string        like value to match with field
	 * @param   boolean       automatically add starting and ending wildcards
	 * @return  Database_Core        This Database object.
	 */
	public function orlike($field, $match = '', $auto = TRUE)
	{
		$fields = is_array($field) ? $field : array($field => $match);

        $sub_where = array();
		foreach ($fields as $field => $match)
		{
			$field       = (strpos($field, '.') !== FALSE) ? $this->config['table_prefix'].$field : $field;
			$sub_where[] = $this->driver->like($field, $match, $auto, 'OR ', count($sub_where));
		}
        $this->where[] =
            ( count($this->where) ? 'AND ' : '' ) . 
            '( ' .  implode(' ', $sub_where) .  ' )';

		return $this;
	}

}
