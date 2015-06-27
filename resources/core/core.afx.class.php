<?php
/**
 * - 04/16/2015 - Added ability to work with http or https protocols
 * - 09/28/2014 - Moved mysql_real_escape_string into a function, 
 *                since there are now applications that don't require a database connection.
 * - 09/24/2014 - Use PHP_SELF or SCRIPT_URI for $raw_uri_data. Some hosts lack one
                  or the other for some reason.
 * - 08/07/2014 - Countermeasures to SQL Injection. Seedling DB for localhost and live
 * - 03/26/2014 - Added the ability to read custom registry values from the 'seedling' registry
 * this allows the framework to launch on the core.registry.php values, but when apps run they
 * custom registry values to perhaps go to a different database, use another template instead, etc
 * With this, a new site can be essentially created from the database. You know, like mass shadow clones...
 * - 02/27/2013 - Added the ability to use a custom main HTML,
 * useful when you don't want the usual main.htm in a template for specific actions
 *
 * This class is basically the "kernel" of the framework
 * It is how the website can be told what to do, and then know where to find
 * the right classes/files, preload them, sanitize user input, provide
 * additional tools that would seem out of place in the API class, etc. Some
 * subroutines are duplicated here because I wanted this class to be independent.
 * @author Harvey Brooks
 */
class afx{
    // Attributes
    public $base_url = null;
    public $uri_data = null;
    public $app_path = null;
    public $current_app = null;
    public $registry_keys = null;
    public $output_mode = null;
    public $current_action = null;
    public $template_path = null;
    public $template_values = null;
    public $template_postrocessor = null;
    public $template_preprocessor = null;

    /**
     * Constructor...the first subroutine this class runs when instantiated.
     * @param array $registry_keys - settings for the website, defined as an array in core.registry.php
     */
    function __construct($registry_keys){
        //Connect to initial database
        $this->connect_db(
            $registry_keys["system"]["db_host"],
            $registry_keys["system"]["db_username"],
            $registry_keys["system"]["db_password"],
            $registry_keys["system"]["db_name"]
        );
        //Make sure we have a seedling database registry table
        $this->database_query("
            CREATE TABLE seedling(
                id int not null auto_increment,
                domain varchar(64),
                key_type varchar(64),
                key_name varchar(64),
                key_value longtext,
                constraint pk_task
                primary key(id)
            )
        ");	
        // - Initilalize object attributes
        $this->initialize_attributes($registry_keys);
        // - Sanitize all input that comes from the user. Don't trust 'em
        $this->map_uri();
        $this->sanitize_get();
        $this->set_base_url();
        $this->sanitize_post();
        $this->sanitize_cookie();
        $this->sanitize_request();
		
		$accessProtocol = ($_SERVER["HTTPS"] == "on") ? "https://": "http://";
        //Look for custom registry for this site in the 'seedling' database registry        
        $strippedBaseURL = str_replace("{$accessProtocol}{$_SERVER["HTTP_HOST"]}/","",$this->base_url);        
        //Get the seedling registry keys for this site.
        $seedling_registry = @$this->sql_to_array($this->database_query("
            SELECT      seedling.id,
                        seedling.key_type,
                        seedling.key_name,
                        seedling.key_value
            FROM        seedling
            WHERE       seedling.domain LIKE '%{$strippedBaseURL}'
        "));
        //Make sure we have something
        if(sizeof($seedling_registry) > 0){
            //Loop
            foreach($seedling_registry as $i => $key_data){
                //Override the core.registry.php entries on an as-needed basis
                $this->registry_keys[$key_data["key_type"]][$key_data["key_name"]] = $key_data["key_value"];
            }
            //Re-initialize the framework's attributes to reflect the overriden, if any, registry
            // - Initilalize object attributes
            $this->initialize_attributes($this->registry_keys);
			$this->map_uri();
        }

        // - If the app base class exists, conveniently load it
        if (is_file("$this->app_path/$this->current_app/app.class.php")) {
            require_once("$this->app_path/$this->current_app/app.class.php");
        }
    }
    //Escape String
    public function escape_string($value){
        //If there are database connections available, use mysql_real_escape_string
        if(sizeof($this->db_connections) > 0){
            $escaped_value = mysql_real_escape_string($value);
        }
        else{
            //Otherwise...um, just. Uh...just return it. Because addslashes can ruin your day on some servers
            $escaped_value = $value;
        }
        //Send it back
        return $escaped_value;
    }

    /**
     * Accepts a given application url i.e ("blog/categories")
     * This is useful for when you've processed a form and want to show
     * the success page.
     * @param string $target_url - where you want the website to go
     */
    public function redirect($target_url){
        header("Location:" . $this->base_url . "index.php/$target_url");
    }

    /**
     * Receives a string with template tokens, and then replaces them
     * real values that are received from an array in the second parameter
     * @param string $template
     * @param string $template_values
     * @return string
     */
    function parse_template($template, $template_values){
        foreach ($template_values as $placeholder => $value){
            $template = preg_replace("/{%$placeholder%}/", $value, $template);
        }

        return stripslashes($template);
    }

    /**
     * Provides a way to put define template name/values
     * Yes, you could also just directly access
     * $obj->template_values["template_variable"] = "Template Value"
     * Why do it this way? It looks neater.
     * @param string $placeholder
     * @param string $actual_value
     */
    function set_template_value($placeholder, $actual_value){
        $this->template_values[$placeholder] = $actual_value;
    }

    /**
     * Creates an HTML tag out of a string. Seems silly, but it also seems to
     * make the code appear more consistent than mixing PHP with HTML markup.
     * @example:
     * $this->html_tag("a|class=page_number,href=www.google.com", "Google")
     * Translates to: <a class = "page_number" href = "www.google.com">Google</a>
     * @param string $tag_script
     * @param string $inner_html
     * @return string
     */
    function html_tag($tag_script, $inner_html){
        $tag_definition       = explode("|", $tag_script);
        $tag_attributes       = explode(",", $tag_definition[1]);
        $tag_name             = $tag_definition[0];
        $processed_attributes = "";

        foreach ($tag_attributes as $i => $attribute_script) {
            $attribute_definition = explode("=", $attribute_script);
            $processed_attributes .= $attribute_definition[0] . " = '" . $attribute_definition[1] . "' ";
        }

        return "\n\t<" . $tag_name . " " . $processed_attributes . ">" . $inner_html . "</" . $tag_name . ">";
    }

    /**
     * Accepts the path of the directory you want to read--relative to where
     * index.php is located, and returns an array with the list of files in
     * that directory, if any. Yes, there is an exact duplicate of this
     * (and others) in the core.api.class.php file, but I wanted this one to
     * be independent of the api instead of extending it...and then just
     * apps themselves just extending the afx class. I wanted them to be
     * separate.
     * @param string $target_directory
     * @return Array
     */
    function extract_diskspace_content($target_directory){
        if (is_dir($target_directory)) {
            $i = 0;
            $directory_content;
            $directory_handle = opendir($target_directory);
            while ($directory = readdir($directory_handle)) {
                if ($directory != "." && $directory != "..") {
                    $directory_content[$i] = $directory;
                    $i++;
                }
            }

            closedir($directory_handle);
            return $directory_content;
        }
    }

    /**
     * I don't even know why I'm documenting this, since you're not supposed to
     * touch it. Anyways, this basically gets the web address the framework is
     * running from, and passes that information to the registry so apps can
     * use it/initialize the API class.
     */
    private function set_base_url(){
		$accessProtocol = ($_SERVER["HTTPS"] == "on") ? "https://": "http://";
		
        $args                                      = explode("index.php","{$accessProtocol}{$_SERVER["HTTP_HOST"]}". implode("/", $this->uri_data));        
        $this->base_url                            = $args[0];
        $this->registry_keys["system"]["base_url"] = $this->base_url;
    }

    /**
     * Parses the URI (which is really a type of Uniform Resource Identifier)
     * for the the following information:
     * $this->current_action - what subroutine you want the application controller
     * to run
     * $this->action_value - argument the current action subroutine may need
     * $this->current_app - the name of the application to be run
     * $this->registry_keys["system"]["current_page"] - the current page number
     */
    private function map_uri(){
        $page_location       = array_search("page", $this->uri_data);
        $controller_location = array_search("index.php", $this->uri_data);
		
        if (strlen($this->uri_data[$controller_location + 1]) > 0) {
            $this->registry_keys["system"]["current_app"] = $this->uri_data[$controller_location + 1];
        } else {
            $this->registry_keys["system"]["current_app"] = $this->registry_keys["system"]["default_app"];
        }
		
        $this->current_action                          = $this->uri_data[$controller_location + 2];
        $this->action_value                            = $this->uri_data[$controller_location + 3];
        $this->current_app                             = $this->registry_keys["system"]["current_app"];
        $this->registry_keys["system"]["current_page"] = ($page_location) ? $this->uri_data[$page_location + 1] : 1;		
    }

    /**
     * A subroutine to throw error text to echo an error message. Again,
     * I really don't want to just vomit echo statements everywhere since it
     * looks messy.
     * @param string $error_code - the text to be displayed in the error message
     * @param Boolean $stop_error - set this to true if you want the error to
     * stop PHP execution
     */
    function error_message($error_code, $stop_error){
        echo $this->html_tag("div|class=error_code", $error_code);
        if ($stop_error) {
            die;
        }
    }

    /**
     * Sanitizes the $_POST server variable.
     */
    private function sanitize_post(){
        foreach ($_POST as $key => $value) {
            $_POST[$key] = $this->escape_string(HTMLSpecialChars(strip_tags($value, $this->registry_keys["html"]["legal_markup"])));
        }
    }

    /**
     * Sanitizes the $_GET server variable.
     */
    private function sanitize_get(){
        foreach ($_GET as $key => $value) {
            $_GET[$key] = $this->escape_string(HTMLSpecialChars(strip_tags($value, $this->registry_keys["html"]["legal_markup"])));
        }
    }

    /**
     * Sanitizes the $_REQUEST server variable.
     */
    private function sanitize_request(){
        foreach ($_REQUEST as $key => $value) {
            $_REQUEST[$key] = $this->escape_string(HTMLSpecialChars(strip_tags($value, $this->registry_keys["html"]["legal_markup"])));
        }
    }

    private function sanitize_cookie(){
        foreach ($_COOKIE as $key => $value) {
            $_COOKIE[$key] = $this->escape_string(HTMLSpecialChars(strip_tags($value, $this->registry_keys["html"]["legal_markup"])));
        }
    }

    /**
     * Provides access to the $_REQUEST variable. Accessing them directly works,
     * but again is distressingly hideous. Really shouldn't matter how pretty
     * the code looks, but it sure as hell makes reading the code a less dreary
     * affair.
     * @param string $key - Really just does $_REQUEST[$key]. If you don't specify a key,
     * the class will just throw the $_REQUEST array back.
     * @return string or Array
     */

    function request($key = null){
        return ($key) ? $_REQUEST[$key] : $_REQUEST;
    }

    /**
     * Really just returns $_COOKIE[$key], or the whole array if you don't specify $key
     * @param string $key - the name of the cookie you want
     * @return <type>
     */
    function cookie($key = null){
        return ($key) ? $_COOKIE[$key] : $_COOKIE;
    }

    /**
     * Accepts the $registry_keys and initializes class variables from it.
     * $this->registry_keys - self explanatory
     * $this->uri_data - an array of the website URL
     * $this->template_path - path to the template folder used by the website
     * $this->template_preprocessor - a php file that is run just before the application starts.
     * This is useful if you want to change/add a setting in the registry that your template uses.
     * $this->template_postprocessor - a php file that is run right after the application ends.
     * This is your opportunity to initialize additional template variables, etc.
     * The whole idea behind this framework is to separate the code as reasonably possible from the templates,
     * so that way you can build an application once, and reuse it anywhere because it shouldn't depend on
     * a particular template to work. I also think it'd make maintaining/upgrading stuff easier.
     * @param Array $registry_keys
     */    
    function initialize_attributes($registry_keys){
        $this->registry_keys          = $registry_keys;
        $this->template_path          = $this->registry_keys["system"]["template_path"] . $this->registry_keys["system"]["template_name"];
        $this->template_preprocessor  = "$this->template_path/preprocessor.php";
        $this->template_postprocessor = "$this->template_path/postprocessor.php";
        $this->app_path               = $this->registry_keys["system"]["app_path"];
		
		if(strlen($_SERVER["PHP_SELF"]) >= strlen($_SERVER["SCRIPT_URI"])){
			$raw_uri_data               = explode("/", $_SERVER["PHP_SELF"]);
		}
		else{
			$raw_uri_data               = explode("/", $_SERVER["SCRIPT_URI"]);
		}
		
        //URI Path, with input sanitization baked in
        foreach ($raw_uri_data as $key => $value) {
            $this->uri_data[$key] = $this->escape_string(HTMLSpecialChars(strip_tags($value, $this->registry_keys["html"]["legal_markup"])));
        }
    }

    /**
     * Connects to a MySQL database. Part of its default behavior is to attempt to create the specified database
     * everytime it is called, in order to make sure it exists for the applications to query against.
     * @param string $db_host
     * @param string $db_username
     * @param string $db_password
     * @param string $db_name
     * @return mysql_connection
     */
    public function connect_db($db_host, $db_username, $db_password, $db_name,$fatal){
        $db_connection = @mysql_connect($db_host, $db_username, $db_password);
        if ($db_connection){
            $this->database_query("CREATE DATABASE $db_name");
            mysql_select_db($db_name);
            $this->db_connections[$db_name] = $db_connection;
            return $db_connection;
        }
        else{
            if($fatal){
                $this->error_message("Fatal Error: Could not connect to MySQL Database '$db_name' at '$db_host', so check the db credentials in the registry.",$fatal);
            }
            
            else return false;
        }
    }
    /**
     * Acccepts a table name and a filtering array, and then returns a MySQL resource of the resulting query.
     * The point of this is to make writing the queries even easier, more difficult to get wrong, and provide abstraction
     * in case a real database abstraction layer is implemented in the future. If this still seems exeedingly stupid, you could
     * always use $api->database_query("your select statement").
     * @param string $table - the name of the table that has the information you want.
     * @param array $filters - an array that will be translated to a MySQL query string
     * @param boolean $rows_only - set this to true if you only want to know how many records were returned.
     * @example:
     * $filters["index_field"]["media_label"] = $folder_name;
     * $filters["selection_field"][0] = "media_id";
     * $filters["selection_field"][1] = "media_type";
     * $filters["selection_field"][2] = "media_label";
     * $filters["selection_field"][3] = "media_owner_id";
     * $filters["selection_field"][4] = "media_category";
     * $filters["selection_field"][5] = "media_description";
     * $api->db_select("media",$filters) -> "select media_id, media_type, media_label, media_owner_id, media_category, media_description from media where media_label = 'Test' and media_label = 'Test'"
     * @return mysql_resource
     */
    public function db_select($table, $filters, $rows_only = null,$debug = null){
        $sort_order       = $filters["sort_order"];
        $select_statement = $filters["index_field"];
        $query_paging     = $filters["query_paging"];
        $fields           = $filters["selection_field"];
        $qualifying_field = $filters["qualifying_field"];

        if(is_array($fields)){
            foreach($fields as $i => $field){
                $fields[$i] = $this->escape_string("{$table}.{$field}");
            }

            $fields = implode(", \n", $fields);
        }
        else{
            $fields = null;
        }

        mysql_query('set character set utf8');

        if (is_array($select_statement)){
            foreach ($select_statement as $primary_field_name => $primary_field_value){
                $primary_field_name = $this->escape_string($primary_field_name);
                $primary_field_value = $this->escape_string($primary_field_value);
                $indexed_query = "\n SELECT \n {$fields} \nFROM {$table} \nWHERE {$table}.{$primary_field_name} = '$primary_field_value' \n";
                break;
            }

            foreach ($select_statement as $field_name => $field_value){
                $field_name = $this->escape_string($field_name);
                $field_value = $this->escape_string($field_value);
                $indexed_query .= "\n AND {$table}.{$field_name} = '$field_value' ";
            }

            if($debug){
                $this->error_message($indexed_query);
            }

            if($rows_only == "rows_only"){
                return mysql_num_rows($this->database_query("$indexed_query $sort_order"));
            }
            else{
                return $this->database_query("$indexed_query $sort_order", $query_paging);
            }
        }
        else if ($qualifying_field){
            return $this->database_query("SELECT $fields FROM $table WHERE $qualifying_field = '$select_statement' $sort_order", $query_paging);
        }
        else{
            return $this->database_query("SELECT $fields FROM $table $sort_order", $query_paging);
        }
    }
    /**
     * Runs either a raw query against the database, or translates an array into a mysql query.
     * If provided paging information, it can "page" the query to show a certain range of records.
     * This is really useful because it significantly increases performance by showing only the
     * required information.
     * @param string|array $query
     * @param array $query_paging
     * @return mysql_resource
     */
    function database_query($query_string,$query_paging = null){
        //First, get the query handle
        $query_handle = mysql_query($query_string);
        //If there is a query handle we want query paging...
        if($query_handle && $query_paging){
            $items_per_page = $query_paging["items_per_page"];
            $initial_record = ($query_paging["current_page"] > 1) ? (($query_paging["current_page"] - 1) * $query_paging["items_per_page"]) : 0;
            $new_query = "$query_string LIMIT $initial_record,$items_per_page";
            $this->total_pages = ceil(mysql_num_rows($query_handle) / $items_per_page);
            return mysql_query($new_query);
        }
        //There's no need for query paging, so just send it back
        else{
            return $query_handle;
        }
    }
    /**
     * Takes a mysql_resource and returns an array of it. This removes the step where you'd have to
     * write a while($result_data = mysql_fetch_assoc($sql_resource) every time you run a query to get
     * your data.
     * @param mysql_resource $sql_resource
     * @return array
     */
    public function sql_to_array($sql_resource){
        $i            = 0;
        $result_array = null;
        //Check to make sure we have a valid $sql_resource
        if ($sql_resource){
            while ($result_data = mysql_fetch_assoc($sql_resource)){
                $result_array[$i] = $result_data;
                $i++;
            }
        }
        //Return the $result_array
        return $result_array;
    }
    /**
     * Last subroutine to run. In a nutshell, this one's purpose is to run the postprocessor,
     * render the processed template, and finally show the finished result. It's also supposed
     * to do some additional clean up and other clever stuff, but that'll come later.
     */
    function render_html(){
        if($this->custom_main_html){
            $main_html = $this->custom_main_html;
        }
        else{
            $main_html = "$this->template_path/main.htm";
        }

        // If it exists, run template postprocessor
        if (is_file($this->template_postprocessor)) {
            require_once($this->template_postprocessor);
        }

        if (is_file($main_html)) {
            $template_handle = fopen($main_html, "r");
            $template        = fread($template_handle, filesize($main_html));
            $head_includes   = $this->extract_diskspace_content($this->template_path . $this->registry_keys["system"]["template_includes_path"]);

            if (sizeof($head_includes) > 0) {
                natsort($head_includes);
                foreach ($head_includes as $i => $file_name) {
                    $file_extension = strtolower(end(explode(".", $file_name)));
                    $file_path      = $this->base_url . $this->template_path . $this->registry_keys["system"]["template_includes_path"] . "/$file_name";

                    switch ($file_extension) {
                        case "js":
                            $this->template_values["javascript"] .= $this->html_tag("script|type=text/javascript,src=$file_path", "");
                            break;

                        case "css":
                            $this->template_values["stylesheets"] .= $this->html_tag("link|rel=stylesheet,type=text/css,href=$file_path", "");
                            break;
                    }
                }
            }

            $this->template_values["base_url"]      = $this->base_url;
            $this->template_values["template_path"] = $this->base_url . $this->template_path;
            echo $this->parse_template($template, $this->template_values);
        }
        else {
            echo $this->html_tag("strong", "View not found at '$main_html'");
            die;
        }
    }
}
?>