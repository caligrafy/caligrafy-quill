<?php

/**
 * Model.php
 * Model is the base model from which application models can be extended
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

/**
 * Class Model is the base model from which the application models can be extended
 * @author Dory Azar
*/

namespace Caligrafy;
use \Exception as Exception;

class Model extends \stdClass {
    
    public $table;
    protected $__restricted;
    
    public function __construct($table = null)
    {   
        return $this($table);
    }

    public function __invoke($table = null, $dynamicArgs = array())
    {
        // connect to database
        $this->table = $table != null? $table : $this->table;
        $this->__restricted = ['table', '__restricted', 'pivot'];
        foreach($dynamicArgs as $property => $value) {
            $this->$property = $value;
        }
        return $this;
    }

    /**
     * Associates a model with a table in the database
     */
    public function associate($table) 
    {
        $this($table);
        return $this;
    }
    
    /**
     * Basic CRUD functions on the associated table
     */

    // CREATE
    public function create($inputs) 
    { 
        $inputArray = $this->prepareInputs($inputs);

        // Format the value binder for PDO
        $columns = implode(',', array_map(array($this, 'quoteIdentifier'), array_keys($inputArray)));
        $binder = implode(',', array_fill(0, sizeof($inputArray), '?'));

        // Execute the result
        if (empty($inputArray)) {
            Database::execute("INSERT INTO ". $this->table . " () VALUES ()");
        } else {
            Database::execute("INSERT INTO ". $this->table . " (".$columns.") VALUES (".$binder.")", array_values($inputArray));
        }

        // update the object to insert the id
        $this->id = Database::lastInsertId();
        return $this->find($this->id); 

    }

    // UPDATE
    public function update($inputs, $id)
    { 
        $inputArray = $this->prepareInputs($inputs);

        // check if the record exists
        $record = $id? $this->find($id) : null;
        if (!empty($record) && !empty($inputArray)) {
            $binder = array_map(function($v) { return $this->quoteIdentifier($v)." = ?"; }, array_keys($inputArray));
            $values = array_values($inputArray);
            $values[] = $id;
            Database::execute("UPDATE ".$this->table." SET ".implode(",", $binder)." WHERE id = ?", $values);
        } 

        // return the fetched result from database
        return $this->find($id); 
    }
    
    // DELETE
    public function delete($id)
    { 
      // Create query
        $result = Database::execute("DELETE FROM ". $this->table . " WHERE id= ?", [$id]);
        return $result;
    }
    
    // FIND
    public function find($id)
    { 
        // Create query
         $result = Database::execute('SELECT * FROM ' . $this->table . " WHERE id = ?", [$id]);
         $result = Database::toArray($result);
         
        return !empty($result[0])? $this->arrayToModel($result[0]) : null;
    }
    
    // ALL
    /**
     * Fetches all the records, optionally completed by SQL clauses
     * @param array $args 'condition' (ex: 'WHERE price > ?'), 'order' (ex: 'ORDER BY title'), 'limit' (ex: 'LIMIT 10')
     *                    and 'bindings' (array of values bound to the ? placeholders). The clauses are inserted as is:
     *                    never concatenate user input in them, use ? placeholders and 'bindings' instead.
     */
    public function all($args = array()) { 
        
        $extension = '';
        $bindings = array();
        // Create query
        if(!empty($args)) {
            $condition = isset($args['condition'])? ' '.$args['condition'].' ' : '';
            $order = isset($args['order'])? ' '.$args['order'].' ' : '';
            $limit = isset($args['limit'])? ' '.$args['limit'].' ' : '';
            $extension = $condition.$order.$limit;
            $bindings = isset($args['bindings'])? array_values((array)$args['bindings']) : array();
        }

        $result = Database::execute('SELECT * FROM ' . $this->table . $extension, $bindings);
        $renderedResult = array();
        foreach($result as $key => $value) {
            $renderedResult[] = $this->arrayToModel($value);
        }
        return $renderedResult;
    } 

    /**
     * Search functionality that searched records in a table
     * @param string $scope defines the columns to search in the database separated by commas
     * @param string $keywords defines the LIKE pattern to look for (% and _ are wildcards). It is bound, never inlined.
     * @return array $result results found in the database
     * @author Dory A.Azar 
     * @version 1.0
     */  
    public function search($scope, $keywords) {

        // clean up the input
        $scopeArray = array_map('trim', explode(',', (string)$scope));
        $keywords = trim((string)$keywords);
        $conditions = array();
        $bindings = array();
        
        //build the query
        foreach($scopeArray as $column) {
            $conditions[] = $this->quoteIdentifier($column) . ' LIKE ?';
            $bindings[] = $keywords;
        }
        
        $sql = 'SELECT * FROM ' . $this->table . ' WHERE ' . implode(' OR ', $conditions);
        
        // Execute the result
        $result = Database::execute($sql, $bindings);
        $renderedResult = array();
        foreach($result as $key => $value) {
            $renderedResult[] = $this->arrayToModel($value);
        }
        return $renderedResult;
    } 

    /**
     * Turns the inputs (array or object) into an array of column => value ready to be bound
     * Only null values and restricted attributes are skipped: 0, '' and false are kept
     */
    protected function prepareInputs($inputs)
    {
        $inputArray = !is_array($inputs)? json_decode(json_encode($inputs), true) : $inputs;
        $inputArray = is_array($inputArray)? $inputArray : array();
        // only the columns of the table are written: helper properties of a model (ex: public $errors = []) are ignored
        $columns = $this->tableColumns();
        $inputArray = array_filter($inputArray, function($value, $key) use ($columns) { 
            return $value !== null && !in_array($key, $this->__restricted, true) && (empty($columns) || in_array($key, $columns, true)); 
        }, ARRAY_FILTER_USE_BOTH);
        return array_map(function($value) { 
            if (is_bool($value)) return (int)$value;
            return is_array($value) || is_object($value)? json_encode($value) : $value; 
        }, $inputArray);
    }

    /**
     * Returns the column names of the associated table (cached per table for the request), or an empty array
     * when they cannot be read
     */
    protected function tableColumns()
    {
        static $cache = array();
        if (!$this->table) {
            return array();
        }
        if (!isset($cache[$this->table])) {
            try {
                $rows = Database::toArray(Database::execute('SHOW COLUMNS FROM `'.str_replace('`', '', $this->table).'`'));
                $cache[$this->table] = array_map(function($row) { return $row['Field']; }, $rows);
            } catch (Exception $e) {
                $cache[$this->table] = array();
            }
        }
        return $cache[$this->table];
    }

    /**
     * Validates a column name and returns it quoted for SQL
     */
    protected function quoteIdentifier($name)
    {
        $name = trim((string)$name);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new Exception(Errors::get(2001) . "- invalid column name '" . sanitize($name) . "'", 2001);
        }
        return '`' . $name . '`';
    }

    /**
     * Save functionality applied directly to the object that could update or create depending on whether the object has an id or not
     */
    public function save()
    {
        $id = $this->id?? null;
        return $id? $this->update($this, $this->id) : $this->create($this);
    }

    
    /**
     * Defines the hasOne relationship
     * @param string $modelName defines the name of the model to join to
     * @param string $joinedTableName defines the name of the table to join to
     * @param string $localKey defines the identifier key of the joined table
     * @param string $foreignKey defines the identifier key of the key indexing the joined table
     * @return Model object containing the records from the join
     * @author Dory A.Azar 
     * @version 1.0
     */  
    public function hasOne($modelName, $joinedTableName, $foreignKey = null, $localKey = null, $functionName = null)
    {
        $functionName = $functionName?? debug_backtrace()[1]['function'];
        $joinedTableName = strtolower($joinedTableName);
        $oneToOneModel = class_exists($modelName)? new $modelName($joinedTableName): null;
        $outcome = array();
        if ($oneToOneModel instanceof Model) {
                $localId = $localKey?? 'id';
                $localKey = $localKey?  $joinedTableName.".".$localKey : $joinedTableName.".id";
                $foreignKey = $foreignKey? $this->table.".".$foreignKey : $this->table.".".strtolower($modelName)."_id";
                $result = Database::execute('SELECT '.$joinedTableName.'.* FROM '.$this->table.' LEFT JOIN '.$oneToOneModel->table.' ON '.$localKey." = ".$foreignKey." WHERE ".$this->table.".id = ?", [$this->id]);
                $rows = Database::toArray($result);
                $oneToOneModel = $oneToOneModel->arrayToModel($rows[0]?? array());
                array_push($this->__restricted, $functionName);

            }
        return $oneToOneModel && $oneToOneModel->id? $oneToOneModel : null ;
    }
    
   
     /**
     * Defines the hasMany relationship
     * @param string $modelName defines the name of the model to join to
     * @param string $joinedTableName defines the name of the table to join to
     * @param string $foreignKey defines the identifier key of the key indexing the joined table
     * @param string $localKey defines the identifier key of the joined table
     * @return Model object containing the records from the join
     * @author Dory A.Azar 
     * @version 1.0
     */    
    public function hasMany($modelName, $joinedTableName, $foreignKey = null, $localKey = null, $functionName = null)
    {
        $functionName = $functionName?? debug_backtrace()[1]['function'];
        $joinedTableName = strtolower($joinedTableName);
        $oneToManyModel = class_exists($modelName)? new $modelName($joinedTableName): null;
        $outcome = array();
        if ($oneToManyModel instanceof Model) {
               $foreignKey = $foreignKey?? strtolower(get_class($this))."_id";
               $localKey = $localKey? $this->$localKey?? $this->id : $this->id;
               $result = Database::execute('SELECT * FROM '.$joinedTableName.' WHERE '.$this->quoteIdentifier($foreignKey).' = ?', [$localKey]);
               $result = Database::toArray($result);
                foreach($result as $key => $value) {
                    $newModel = new $modelName($joinedTableName);
                    $outcome[] = $newModel->arrayToModel($value);
                }
                array_push($this->__restricted, $functionName);

            }
        return $outcome;
    }
    
    // Defines the inverse relationship
    public function belongsTo($modelName, $joinedTableName, $foreignKey = null, $localKey = null)
    {
        $functionName = debug_backtrace()[1]['function'];
        return $this->hasMany($modelName, $joinedTableName, $foreignKey, $localKey, $functionName);
    }
    
    
    public function belongsToMany($modelName, $joinedTableName, $foreignKeyLocal = null, $foreignKeyJoin = null, $functionName = null)
    {

        // the calling function will become a property of the object
        $functionName = $functionName?? debug_backtrace()[1]['function'];
        
        // get the tables' names, foreign keys,  and create the pivot table name
        $table1 = $this->table;
        $table2 = $joinedTableName;
        $model1 = strtolower(get_class($this));
        $model2 = strtolower($modelName);
        $foreignKeyLocal = $foreignKeyLocal?? 'id';
        $foreignKeyJoin = $foreignKeyJoin?? 'id';
        $fklocal = $model1."_"."id";
        $fkjoin = $model2."_"."id";
        $pivot = !$this->tableExists($table1.'_'.$table2)? $this->tableExists($table2.'_'.$table1)? $table2.'_'.$table1 : $table1.'_'.$table2 : $table1.'_'.$table2;
        $outcome = array();
        $pivotOutcome = array();
    
        
        // create the pivot table: the foreign key columns copy the type of the referenced ids (signed or unsigned)
        // and the constraint names are unique per pivot (MySQL requires unique constraint names per database)
        $typeLocal = $this->columnType($table1, $foreignKeyLocal);
        $typeJoin = $this->columnType($table2, $foreignKeyJoin);
        Database::execute('CREATE TABLE IF NOT EXISTS '.$pivot.' (`id` INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,`'.$fklocal.'` '.$typeLocal.' NOT NULL, `'.$fkjoin.'` '.$typeJoin.' NOT NULL, `created_at` DATETIME NOT NULL DEFAULT now(), `modified_at` DATETIME NOT NULL DEFAULT now() ON UPDATE now(), PRIMARY KEY (`id`), CONSTRAINT `'.$this->constraintName($pivot, $fklocal).'` FOREIGN KEY (`'.$fklocal.'`) REFERENCES `'.$table1.'` (`'.$foreignKeyLocal.'`) ON UPDATE CASCADE ON DELETE CASCADE, CONSTRAINT `'.$this->constraintName($pivot, $fkjoin).'` FOREIGN KEY (`'.$fkjoin.'`) REFERENCES `'.$table2.'` (`'.$foreignKeyJoin.'`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8;');


        $pivotModel = new Model($pivot);


        // build the pivot model and associate to the caller object
        // build the pivot model and associate to the caller object
        $result = $this->id? Database::execute('SELECT '.$table2.'.* FROM '.$table1.' LEFT JOIN '.$pivot.' ON '.$table1.'.'.$foreignKeyLocal.'='.$pivot.'.'.$fklocal.' LEFT JOIN '.$table2.' ON '.$table2.'.'.$foreignKeyJoin.'='.$pivot.'.'.$fkjoin.' WHERE '.$table1.'.'.$foreignKeyLocal.' = ?', [$this->id]) : [];
        $result = Database::toArray($result);


        // Get the categories results
        foreach($result as $key => $value) {
            $newModel = new $modelName($joinedTableName);
            if (isset($value['id'])) {
                $outcome[] = $newModel->arrayToModel($value);
            }
        }
        
        // keep track of the keys in the pivot model
        $pivotModel->$fklocal = $this->id?? null;
        $pivotModel->fklocal = $fklocal;
        $pivotModel->fkjoin = $fkjoin;
        
        
        // create a pivot attribute that saves all the pivot info in a model
        $this->pivot = $this->pivot?? new Model();
        $this->pivot->$joinedTableName = $pivotModel;
        $this->pivot->$joinedTableName->all = $result;
        $this->pivot->$joinedTableName->functionName = $functionName;

        
        // create restricted names to avoid having them transfer upon save
        array_push($this->pivot->$joinedTableName->__restricted, 'fklocal', 'fkjoin', 'all', 'functionName');
        array_push($this->pivot->$joinedTableName->__restricted, $functionName);
        
        return $outcome;
    }
    
    
    public function attach($joinedTableName, $id)
    {
        return $this->attachDetach($joinedTableName, $id);
    }
    
    
    public function detach($joinedTableName, $id)
    {
        return $this->attachDetach($joinedTableName, $id, false);
    }
    
    protected function attachDetach($joinedTableName, $id, $attach = true)
    {

        $idLocal = $this->id?? null;
        $idJoin = $id?? null;
        
        if ($idLocal && $idJoin) {
            // if it has a pivot
            if ($this->pivot->$joinedTableName) { 
                // get the pivot ids
                $fklocal = $this->pivot->$joinedTableName->fklocal;
                $fkjoin = $this->pivot->$joinedTableName->fkjoin;
                
                // create a model to add in case of attachment
                $attachModel = new Model($this->pivot->$joinedTableName->table);
                $attachModel->$fklocal = $idLocal;
                $attachModel->$fkjoin = $idJoin;
                
                // find if an entry exists already
                $search = Database::toArray(Database::execute('SELECT id FROM '.$this->pivot->$joinedTableName->table.' WHERE '.$fklocal.' = ? && '.$fkjoin.' = ?', [$idLocal, $idJoin]));

                $idPivot = !empty($search)? isset($search[0]['id'])? $search[0]['id'] : null : null;
                $attachModel->id = $idPivot;
                $attach? $attachModel->save() : $this->pivot->$joinedTableName->delete($idPivot);
                
            }
        }
        $function = $this->pivot->$joinedTableName->functionName;
        return $this->$function();      
    }
    
    /**
     * Turns an array into a Model
     * @param array $array takes the array that needs to be converted to a model
     * @return Model object containing the modified object
     * @author Dory A.Azar 
     * @version 1.0
     */ 
    public function arrayToModel($array) 
    {
        $newModel = new $this($this->table);
        foreach($array as $key => $value) {
            $newModel->$key = $value;
        }
        return $newModel;
    }
      
    /**
     * Turns Model into an array
     * @return array representation of the model
     * @author Dory A.Azar 
     * @version 1.0
     */   
    public function toArray()
    {
        $result = array();
        if($this instanceof Model) {
            $result = (Array)$this;
        }
        return $result;
    }
    
    /**
     * Returns the SQL type of a column (ex: 'int unsigned') so that foreign keys can match it
     * Falls back to INT(25) (the previous default) when the column cannot be inspected
     */
    protected function columnType($table, $column)
    {
        try {
            $rows = Database::toArray(Database::execute('SHOW COLUMNS FROM `'.str_replace('`', '', $table).'` WHERE Field = ?', [$column]));
            $type = $rows[0]['Type']?? null;
            if ($type && preg_match('/^[a-z]+(\(\d+\))?( unsigned)?( zerofill)?$/i', $type)) {
                return strtoupper($type);
            }
        } catch (Exception $e) {}
        return 'INT(25)';
    }

    /**
     * Builds a foreign key constraint name that is unique and fits the 64 characters MySQL allows
     */
    protected function constraintName($pivot, $column)
    {
        $name = 'fk_'.$pivot.'_'.$column;
        return strlen($name) <= 64? $name : 'fk_'.substr($pivot, 0, 40).'_'.substr(md5($pivot.'_'.$column), 0, 16);
    }

    /**
     * Checks if a table exists
     * @return boolean of the result
     * @author Dory A.Azar 
     * @version 1.0
     */   
    public function tableExists($tablename)
    {
        try {
            $result = Database::execute('SELECT * from '.$tablename);
        } catch (Exception $e) {
            return false;
        }
        return true;
        
    }
    
}