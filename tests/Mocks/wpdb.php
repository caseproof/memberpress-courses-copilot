<?php

/**
 * Simple wpdb mock for testing
 * 
 * Provides basic database functionality using SQLite for real tests
 * Following CLAUDE.md principles - no fake data, real database operations
 */
class wpdb
{
    public $prefix = 'wp_';
    public $last_error = '';
    public $insert_id = 0;
    public $num_rows = 0;
    public $last_query = '';
    
    private $db;
    private $queries = [];
    
    public function __construct()
    {
        // Use SQLite for real database tests
        $dbFile = sys_get_temp_dir() . '/mpcc_test_' . uniqid() . '.db';
        $this->db = new SQLite3($dbFile);
        $this->db->exec('PRAGMA foreign_keys = ON');
    }
    
    /**
     * Prepare a SQL query for safe execution
     */
    public function prepare($query, ...$args)
    {
        // Simple sprintf-style preparation
        if (empty($args)) {
            return $query;
        }
        
        // Replace WordPress-style placeholders with sprintf placeholders
        $query = str_replace(['%d', '%f', '%s'], ['%d', '%f', '%s'], $query);
        
        // Escape strings
        $escaped_args = array_map(function($arg) {
            if (is_string($arg)) {
                return SQLite3::escapeString($arg);
            }
            return $arg;
        }, $args);
        
        return vsprintf($query, $escaped_args);
    }
    
    /**
     * Execute a query
     */
    public function query($query)
    {
        $this->last_query = $query;
        $this->queries[] = $query;
        
        try {
            $result = $this->db->query($query);
            $this->last_error = '';
            
            if ($result === false) {
                $this->last_error = $this->db->lastErrorMsg();
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Get results from database
     */
    public function get_results($query, $output_type = OBJECT)
    {
        $result = $this->query($query);
        
        if (!$result) {
            return null;
        }
        
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($output_type === OBJECT) {
                $rows[] = (object) $row;
            } elseif ($output_type === ARRAY_A) {
                $rows[] = $row;
            } elseif ($output_type === ARRAY_N) {
                $rows[] = array_values($row);
            }
        }
        
        $this->num_rows = count($rows);
        return $rows;
    }
    
    /**
     * Get single row
     */
    public function get_row($query, $output_type = OBJECT, $y = 0)
    {
        $results = $this->get_results($query, $output_type);
        
        if (!$results || !isset($results[$y])) {
            return null;
        }
        
        return $results[$y];
    }
    
    /**
     * Get single variable
     */
    public function get_var($query, $x = 0, $y = 0)
    {
        $row = $this->get_row($query, ARRAY_N, $y);
        
        if (!$row || !isset($row[$x])) {
            return null;
        }
        
        return $row[$x];
    }
    
    /**
     * Insert data
     */
    public function insert($table, $data, $format = null)
    {
        $columns = array_keys($data);
        $values = array_values($data);
        
        $placeholders = array_fill(0, count($values), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($values as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
        
        if ($stmt->execute()) {
            $this->insert_id = $this->db->lastInsertRowID();
            return true;
        }
        
        $this->last_error = $this->db->lastErrorMsg();
        return false;
    }
    
    /**
     * Update data
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        $set_parts = [];
        foreach ($data as $column => $value) {
            $set_parts[] = sprintf("%s = '%s'", $column, SQLite3::escapeString((string)$value));
        }
        
        $where_parts = [];
        foreach ($where as $column => $value) {
            $where_parts[] = sprintf("%s = '%s'", $column, SQLite3::escapeString((string)$value));
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set_parts),
            implode(' AND ', $where_parts)
        );
        
        return $this->query($sql) !== false;
    }
    
    /**
     * Delete data
     */
    public function delete($table, $where, $where_format = null)
    {
        $where_parts = [];
        foreach ($where as $column => $value) {
            $where_parts[] = sprintf("%s = '%s'", $column, SQLite3::escapeString((string)$value));
        }
        
        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $where_parts)
        );
        
        return $this->query($sql) !== false;
    }
    
    /**
     * Get charset collate for table creation
     */
    public function get_charset_collate()
    {
        return ''; // SQLite doesn't use charset collate
    }
    
    /**
     * Clean up
     */
    public function __destruct()
    {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// Define constants if not already defined
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}