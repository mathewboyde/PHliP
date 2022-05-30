<?php
/*
    PHliP -> PHP Lightning Integration (v1.0.0)
    Author: Mathew Boyde
    VARMAP class phlip
    
    $phlip_db_access (bool)     ->  Grants access to database connection. Set to auto-deny (false) by default. 
    $serv_conn (PDO object)     ->  Main database connection. 
    $dom_prefix (string)        ->  Main domain prefix. This should be the root of your project 
                                    (i.e -> '/' or 'some/path/htdocs/your_root_folders'). 
    $common_path (string)       ->  Defines the folder path to common items. This can include: 
                                    (but not limited to) styles, javscripts, php scripts, headers, footers, etc. 
    $serving_page (string)      ->  Users' current page. 
    $sub_root_location (string) ->  Root for subdomain (if multiple projects exist, or are served, in same root). 
                                    Example: When creating this framework, I had multiple projects in the same 
                                    domain. I had the main project that was at the htdocs root, but other projects 
                                    exist at different levels of a "dev" folder. This variable is only necessary 
                                    if HTML is requesting the path (for linking scripts, styles, etc) and should 
                                    contain the entire site path to the sub domain, such that (domain)/your/site/root_path/.
    $cp_subs (array)            ->  Defines $key -> $value pairs for sub paths within the common folder.
    
    NOTE: Any instance of "r_" denotes "return". EX: r_arr would be read as return array, etc.
    COMING SOON: Ability to query by all sql JOINS.
*/
class phlip {
    public $phlip_db_access = false;
    //BEGIN DB CREDS: Edit these to your db info.
    private $username = "(your info)";
    private $password = "(your info)";
    private $db_host  = "(your info)";
    private $db_name  = "(your info)";
    //END DB CREDS
    public $serv_conn;
    public $dom_prefix;
    public $sub_root_location;
    public $common_path;
    public $cp_subs;
    public $serving_page;
    
    //Provides values for variables defined above
    function __construct() {
        $this -> phlip_db_access = $this -> check_session_status();
        //Check if db_access is true then establish PDO connection to the database
        if($this -> phlip_db_access) {
            $this -> serv_conn = new PDO("mysql:host=$this->db_host;dbname=$this->db_name", $this -> username, $this -> password);
        }
        $this -> serving_page = $_SERVER["REQUEST_URI"];
        $this -> set_timezone();
        $this -> establish_paths();
    }
    /*
        Sets default timezone based on session variable. This should be set either by asking the user, 
        or gathering from IP address information. Either way, you will want to store that value in 
        databse if date/time issues are a concern in your project.
        COMING SOON: Integrate date/time mainpulation/formatting into an easily accessible class.
    */
    public function set_timezone() {
        if(isset($_SESSION["default_timezone"]) && !!$_SESSION["default_timezone"]) {
            date_default_timezone_set($_SESSION["default_timezone"]);
        }
    }
    /*
        Sets the path variables for use in your php files. This function containes four editable 
        variables: dom_prefix, common_path, sub_root_location, and cp_subs.
        NOTE: common_sub_paths should be edited and maintain non-numerical key values. You can add 
        any subfolder of the common folder. If your project exists on the htdocs root, then leave 
        sub_root_location as an empty string.
    */
    public function establish_paths() {
        $this -> dom_prefix = "(/your/web/root/path)";
        $this -> common_path = $this -> dom_prefix . "extra/common_/";
        $this -> sub_root_location = "https://www.example.com/your/path/";
        $this -> cp_subs = [
            "php_main"  => "php/",
            "js_main"   => "js_main/",
            "styles"    => "styles/",
            "icons"     => "icons/",
            "snips"     => "php/snips/",
            "etc"       => "etc/etc/",
        ];
    }
    /*
        Returns a formatted path for including common files to simplify inclusion of files (removes 
        the necessity to write $phlip->common_path . $phlip->cp_subs . etc...). Accepts two variables,
        f_level (string) and f_origin_php (bool, default true). For other paths use dom_prefix and 
        accompanying folder structure.
    */
    public function f_path($f_level, $f_origin_php = true) {
        $r_str = "";
        //Check if sub folder is specified in the cp_subs array.
        if(isset($this -> cp_subs[$f_level])) {
            $r_str = $this -> common_path . $this -> cp_subs[$f_level];
            //Check if php was the origin of the requesting language.
            if(!$f_origin_php) {
                //If not PHP requesting, HTML is most likely requesting and needs different handling.
                $r_str = str_replace($this -> dom_prefix, "", $r_str);
                //Check if this is a different root than normal htdocs.
                if(!!$this -> sub_root_location) {
                    $r_str = $this -> sub_root_location . $r_str;
                }
            }
            return $r_str;
        } else {
            return false;
        }
    }
    //Checks session values if they exists and are not empty to allow db access.
    public function check_session_status() {
        if((isset($_SESSION["user_id"]) && !!$_SESSION["user_id"])) {
            return true;
        } else {
            return false;
        }
    }
}
/*
    phlip_sql handles different types of queries. As of the time of this release (v1.0.0), 
    there are the options of:
    s_ -> select,
    i_ -> insert,
    u_ -> update,
    d_ -> delete
*/
class phlip_sql extends phlip {
	/*
        s_ -> select
        VARMAP s_
        
        $mult (bool)    ->  0 for single record return
                            1 for multiple record return
        $table (string) ->  table name of query
        $w_arr (array)  ->  WHERE ARRAY: array of count/length 2
                            $w_arr[0] contains field names to search by
                            $w_arr[1] contains values for the fields in $w_arr[0]
                            NOTE: The field counts MUST match in order for the query to be successfully 
                            executed. The only case is when you use $where_or = true for more complex 
                            queries (see discussion of $where_or below for more detail). Can be empty  array such as "[[], []]".
        $o_arr (array)  ->  ORDER BY ARRAY: following $w_arr, this array has a count/length of two and the 
                            contents of $o_arr[0] and $o_arr[1] would contain fields and values, respectively.
                            Must use either 'ASC' or 'DESC' in $o_arr[1] values. Can be empty array such as "[[], []]".
        $where_or (bool)->  mix case of WHERE, AND, OR. It is false by default. Mentioned above, when set to true,
                            care must be taken to ensure that the number fields in $w_arr[0] must match the number of 
                            values provided in $w_arr[1]. 
                            EX: searching a table of cars for blue cars with a spoiler ($where_or = false), $w_arr and result would look like:
                                    [["car_color", "has_spoiler"], ["blue", 1]] equates to 
                                    WHERE `car_color` = 'blue' AND `has spoiler` = 1.
                                searching the same parameters as before, but let's say we want to add yellow cars with leather 
                                interior to the query ($where_or = true) we would then group them together separated by an ampersand as follows
                                    [["car_color&has_spoiler", "car_color&has_leather"], ["blue", 1, "yellow", 1]] equates to 
                                    WHERE `car_color` = 'blue' AND `has_spoiler` = 1 OR `car_color` = 'yellow' AND `has_leather` = 1.
                                NOTE in this example we have a $w_arr[0] count/length of 2 whereas $w_arr[1] count/length is 4. This function
                                (or better, prep_where_ helper) explodes on the ampersand character, thus giving $w_arr[0] a total count/length of 4.
    */
	public function s_($mult, $table, $w_arr, $o_arr, $where_or = false) {
        $sql_query = "SELECT * FROM `$table`";
        //Prepare the WHERE portion of the query
		$where_arr = $this -> prep_where_($w_arr, $where_or);
        //Prep the ORDER BY portion of the query
		$order_str = $this -> prep_order_($o_arr);
        //Prepares and fixes the tail end of the query (space)
		$sql_prep = $this -> serv_conn -> prepare(rtrim(($sql_query . $where_arr[0] . $order_str), " "));
        //Executes query as per PDO
		$sql_prep -> execute($where_arr[1]);
        //Switches between returning single record or multiple records
		if($mult == 0) {
			$sql_return = $sql_prep -> fetch(PDO::FETCH_ASSOC);
		} else{
			$sql_return = $sql_prep -> fetchAll(PDO::FETCH_ASSOC);
		}
		return $sql_return;
	}
    /*
        i_ -> insert
        VARMAP i_
        
        $table (string) -> table name of query
        $f_names (array)-> collection of field names to insert values into
        $f_vals (array) -> collection of values to insert into provided fields
        
        NOTE: $f_names and $f_vals count/length MUST be equal
    */
    public function i_($table, $f_names, $f_vals) {
        $a_str = "INSERT INTO `$table` (";
        $add_fields = $this -> prep_add_fields_($f_names, $f_vals);
        $a_str .= $add_fields[0][0] . ") VALUES (" . $add_fields[0][1] . ")";
        $prep_str = $this -> serv_conn -> prepare($a_str);
        $prep_str -> execute($add_fields[1]);
    }
    /*
        u_ -> update
        VARMAP u_
        
        $table (string) ->  table name of query
        $s_arr (array)  ->  set array. similar to the constraints for $w_arr (see s_ documentation above for $w_arr).
        $w_arr (array)  ->  (see s_ documentation above for $w_arr)
        $where_or (bool)->  (see s_ documentation about for $where_or)    
    */
	public function u_($table, $s_arr, $w_arr, $where_or = false) {
		$sql_query = "UPDATE `$table`";
		$set_str = $this -> prep_set_($s_arr[0]);
		$where_arr = $this -> prep_where_($w_arr, $where_or);
        //Merge set and where value arrays ($w_arr[1] and $s_arr[1]) for execution in PDO
        $where_exec = array_merge($s_arr[1], $where_arr[1]);
        //Concatenate full query string
        $query_str = $sql_query . $set_str . $where_arr[0];
		$sql_prep = $this -> serv_conn -> prepare($query_str);
		$sql_prep -> execute($where_exec);
	}
    /*
        d_ -> delete
        VARMAP d_
        
        $table (string) -> table name of query
        $id_val (string)-> the value of the (autoincrement) ID for the record that you wish to delete
        
        COMING SOON: ability to delte multiple records based on different fields/values
    */
    public function d_($table, $id_val) {
        $sql_query = "DELETE FROM `$table` WHERE `ID` = ?";
		$sql_prep = $this -> serv_conn -> prepare($sql_query);
		$sql_prep -> execute([$id_val]);
    }
    // HELPER for preping add queries
    public function prep_add_fields_($f_names, $f_vars) {
        $r_str = $r_vals = "";
        $r_arr = $r_exec_vals = [];
        //Counts of field nams and values must equal one another
        if(count($f_names) == count($f_vars)) {
            for($z = 0; $z < count($f_names); $z++) {
                //String dressing for query style
                $r_str .= "`" . $f_names[$z] . "`, ";
                $r_vals .= "?, ";
                array_push($r_exec_vals, $f_vars[$z]);
            }
            //Trims last portion from the loop
            $r_str = rtrim($r_str, ", ");
            $r_vals = rtrim($r_vals, ", ");
            //Organizes final return array
            $r_arr = [[$r_str, $r_vals], $r_exec_vals];
        }
        return $r_arr;
    }
    // HELPER for preping where query portions
	public function prep_where_($where_arr, $where_or) {
		$r_str = "";
		$r_where_vars = [];
		if(count($where_arr[0]) > 0) {
			$r_str .= " WHERE ";
			for($z = 0; $z < count($where_arr[0]); $z++) {
                //Searches for ampersand to explode and get correct results.
                if(strpos($where_arr[0][$z], "&")) {
                    $w_corr = explode("&", $where_arr[0][$z]);
                    $r_str .= "`" . $w_corr[0] . "` = ? AND `" . $w_corr[1] . "` = ?";
                } else {
                    //If ampersand is not found, it is a standard where procedure
                    $r_str .= "`" . $where_arr[0][$z] . "` = ?";
                }
                //Check to see if not at the next to last iteration (for adding AND, OR appropriately)
				if($z < (count($where_arr[0]) - 1)) {
                    //Use of ternary operator to quick switch between AND, OR (if $where_or == true)
                    $r_str .= ($where_or == true ? " OR " : " AND ");
				}
			}
		}
        //handle the where values
        //CONSIDER: may not need this procedure and instead just use the incoming $w_arr[1] from the parent function
		if(count($where_arr[1]) > 0) {
			for($z = 0; $z < count($where_arr[1]); $z++) {
				array_push($r_where_vars, $where_arr[1][$z]);
			}
		}
		$r_arr = [$r_str, $r_where_vars];
		return $r_arr;
	}
    // HELPER for preping ORDER BY query portions
	public function prep_order_($order_arr) {
		$r_str = "";
        //Count/length of fields and values must equate and be greater than zero (empty array).
		if((count($order_arr[0]) == count($order_arr[1])) && (count($order_arr[0]) > 0)) {
            $r_str = " ORDER BY ";
			for($z = 0; $z < count($order_arr[0]); $z++) {
                //Query formatting concatenation
				$r_str .= "`" . $order_arr[0][$z] . "` " . $order_arr[1][$z];
                //CONSIDER: changing "count($order_arr)" to "count($order_arr) - 1" to remove the rtrim below.
				if($z < count($order_arr)) {
					$r_str .= ", ";
				}
			}
		}
        //Trims trailing bit from loop
        $r_str = rtrim($r_str, ", ");
		return $r_str;
	}
    // HELPER for preping UPDATE queries
	public function prep_set_($set_field) {
        $r_str = " SET ";
        for($z = 0; $z < count($set_field); $z++) {
            //Query formatting concatenation
            $r_str .= "`" . $set_field[$z] . "` = ?, ";
        }
        //Trims trailing bit from loop
        $r_str = rtrim($r_str, ", ");
		return $r_str;
	}
}
/*
Declare new instances of the classes.
CONSIDER: check if they already exist before creating them again, in the 
off chance that a fatal error is raised by someone declaring them more than once.
*/
$phlip = new phlip;
$phlip_sql = new phlip_sql;
?>