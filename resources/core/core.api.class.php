<?php
/**
 * Provides a collection of commonly performed subroutines used in many applications
 * @author Harvey Brooks
 */
class api{
    public $query_book;
    public $content_pipe;
    public $json_response;
    public $current_action;
    public $db_connections;
    public $template_values;
    /**
     * Takes a full string, then truncates it to a set maximum length
     * @param string $string - the original string
     * @param int $max_characters - How short you want the string. Note a truncated
     * string will have an ellipsis at the end of it so it doesn't look weird, so its really
     * $max_characters + 1
     */
    public function truncate_string($string, $max_characters){
        if(strlen($string) >= $max_characters){
            for ($i = 0; $i < ($max_characters - 3); $i++){
                $truncated_string .= $string{$i};
            }
        }
        else {
            for ($i = 0; $i < ($max_characters); $i++){
                $truncated_string .= $string{$i};
            }
        }
        //$truncated_string .= "&hellip;";
        return $truncated_string;
    }
    /**
     * Sends the JSON Response to the page, makes the code look a bit neater
     */
    public function send_json_response(){
        //JSON Header
        header("Content-type:application/json");
        //Send the JSON Response
        die(json_encode($this->json_response));
    }
    /**
     * Removes newlines and carriage returns from a string. Probably useful for processing
     * some form data.
     * @param string $source_string
     * @return string
     */
    public function scrub_string($source_string){
        $source_string = htmlentities($source_string);
        $source_string = str_replace("\n", "", $source_string);
        $source_string = str_replace("\r", "", $source_string);
        return $source_string;
    }
    /**
     * Generates page numbers based on the most recent mysql query that used $this->db_select().
     * The numbering can be represented by the following structure:
     * PREVIOUS PAGE ... MIDDLE_PAGE_NUMBERS ...NEXT PAGE
     * $this->registry_keys["system"]["paging_trail"] - determines how many page numbers the LEADING and FOLLOWING paging trail has
     * Seriously, if you've figured a better way to do this...then I'm a certified lunatic and kudos to you.
     */
    function render_page_numbers(){
        $rendered_pages = null;
        $this->template_values["page_numbers"] = "";
        $paging_trail   = $this->registry_keys["system"]["paging_trail"];
        $current_page   = $this->registry_keys["system"]["current_page"];

        if(!$this->total_pages){
            return "";
        }

        if($current_page > $this->total_pages){
            $current_page = 1;
        }
        // - PREVIOUS PAGE
        if ($current_page > 1){
            $page_url       = $this->local_url("$this->current_action/page/" . ($current_page - 1));
            $rendered_pages = $this->html_tag("a|class=page_number,href=$page_url", "&larr;");
        }
        //- MIDDLE PAGES
        for($i = $current_page; $i < ($paging_trail + $current_page); $i++){
            if($i <= $this->total_pages){
                $page_url = $this->local_url("$this->current_action/page/$i");
                $rendered_pages .= $this->html_tag("a|class=page_number,href=$page_url", $i);
            }
        }
        // - NEXT PAGE
        if (($current_page + 1) < $this->total_pages){
            $page_url = $this->local_url("$this->current_action/page/" . ($current_page + 1));
            $rendered_pages .= $this->html_tag("a|class=page_number,href=$page_url", "&rarr;");
        }

        $this->template_values["page_numbers"] = $rendered_pages;
        return $rendered_pages;
    }
    /**
     * Loads a template, and then processes it with given values. This is useful when you want a part of your application
     * to have its own mini template. For example, the layout of a blog article for one template is going to look like shit
     * for another. If you must run this in a loop (i.e tiny template for an image + image info),
     * then it's better to avoid thrashing the storage medium and just preloading
     * the template using $api->load_view(), and then running $api->parse_template().
     * @param string $view_path - the path to the template file.
     * @param Array $view_data - the template values you want.
     * @return string
     */
    function render_view($view_path, $view_data){
        $view_render   = null;
        $view_template = $this->load_view($view_path);
        $view_render .= $this->parse_template($view_template, $view_data);
        return $view_render;
    }

    /**
     * Creates a URL that you can use to move around in the web application.
     * URLs in this framework must be absolute. Relative ones wind up either broken,
     * expanding ad infitum, or work only once, etc.
     * @param string $url - where you want it to go. i.e "blog/categories" will translate to
     * "http://yoursite.com/index.php/blog/categories"
     * @return string
     */
    function local_url($url){
        return trim($this->registry_keys["system"]["base_url"] . "index.php/$url");
    }

    /**
     * Deprecated. Perhaps my mind unhinged by one of the wildest flights of lunacy, I created this for some reason.
     * Unfortunately some apps do use it, so I wouldn't get rid of it. Or maybe I was OCD'ing again.
     * @param array $original_array - array you want to add the new information to
     * @param string $new_key - really just the new key in the associative array.
     * @param <type> $new_value - just the value in the associative array attatched to the key above.
     * @return <type>
     */
    function array_push_assoc($original_array, $new_key, $new_value){
        $original_array[$new_key] = $new_value;
        return $original_array;
    }
    /**
     * Takes a string with template tokens, and replaces them with matching values from an array.
     * @param string $template - the string representing the template, so please make sure this isn't a path to a file.
     * @param array $template_values - array with values that will be used to replace tokens in the string.
     * @example:
     * $user_data["user_name"] = "Le Havre";
     * $api->parse_template("Hello World, my name is {%user_name%}",$user_data) -> "Hello World, my name is Le Havre"
     * @return string
     */
    function parse_template($template, $template_values){
        if ($template_values && $template){
            foreach ($template_values as $placeholder => $value){
                $value = preg_replace("/\?/", "\?", $value);
                $value = preg_replace("/\#/", "\#", $value);
                $value = preg_replace("/\^/", "\^", $value);
                $value = preg_replace("/\&/", "\&", $value);
                $value = preg_replace("/\*/", "\*", $value);
                $value = preg_replace("/\(/", "\(", $value);
                $value = preg_replace("/\)/", "\)", $value);
                $value = preg_replace("/\//", "\/", $value);
                $value = preg_replace("/\\\$/","&#36;",$value);

                $template = preg_replace("/{%$placeholder%}/", $value, $template);
            }

            return stripslashes($template);
        }
        else{
            $this->error_message("Uninitialized template or template values<p>$template</p> sizeof template_values-> " . sizeof($template_values), true);
        }
    }
    /**
     * Does exactly what it says on the tin. Since this is used to load template, this dies if it can't read the file or if it is a zero length file.
     * @param type $file_path
     * @return type
     */
    function read_file($file_path){
        if(filesize($file_path) > 0){
            $file_handle = fopen($file_path, "r");
            $file_data   = fread($file_handle, filesize($file_path));
            return $file_data;
        }
        else{
            echo $this->html_tag("strong", "File '$file_path' is a zero length file and could therefore not be loaded.");
            die;
        }
    }
    /**
     * Loads a view, which is really an HTML file with template tokens in it, and then returns the string. This is useful
     * if you don't want to load the same template over and over again (for example, in a loop) which would cause unecessary
     * reads on the file system.
     * Once you specify the path to the file, the subroutine will first look for it in the template directory, because if you have one there
     * then clearly someone wanted the active template to do it differently than the default layout.
     * If it can't find it, then it will attempt to find it in the application directory. You did put a default layout in there, right? Because if you
     * didn't, then your application now depends on whichever template has that layout to work.
     * If it didn't find it there, then there's nothing more to be done and everything will be stopped.
     * @param <type> $file
     * @return <type>
     */
    function load_view($file){
        // 1 -- look for the view in the template directory
        if(is_file($this->registry_keys["system"]["template_path"] . $this->registry_keys["system"]["template_name"] . "/apps/$file")){
            $file_path = $this->registry_keys["system"]["template_path"] . $this->registry_keys["system"]["template_name"] . "/apps/$file";
        }
        // 2 -- look for the view in the application directory
        elseif(is_file($this->registry_keys["system"]["app_path"] . "/$file")){
            $file_path = $this->registry_keys["system"]["app_path"] . "/$file";
        }
        // 3 -- can't be helped. stop everything.
        else{
            echo $this->error_message("View '$file' could not be found in neither the template nor the application directory.", true);
            die;
        }
        // 4 -- check if its not blank before loading it
        return $this->read_file($file_path);
    }
    /**
     * Takes in a string for the HTML markup, and creates a tag out of it.
     * @example: $api->html_tag("a|class=link,href=www.google.com","Google") -> "<a class = "link" href = "www.google.com">Google</a>"
     * @param string $tag_script
     * @param string $inner_html
     * @return string
     */
    function html_tag($tag_script, $inner_html){
        $tag_definition       = explode("|", $tag_script);
        $tag_attributes       = explode(",", $tag_definition[1]);
        $tag_name             = $tag_definition[0];
        $processed_attributes = "";

        foreach($tag_attributes as $i => $attribute_script){
            $attribute_definition = explode("=", $attribute_script);
            $processed_attributes .= $attribute_definition[0] . " = '" . $attribute_definition[1] . "' ";
        }

        return "<" . $tag_name . " " . $processed_attributes . ">" . $inner_html . "</" . $tag_name . ">";
    }

    function html_element_select($select_name,$option_values,$default_option){
        $select_options = "";
        if($option_values){
            foreach($option_values as $option_value){
                if($option_value == $default_option){
                    $select_options .= $this->html_tag("option|selected=true,value=$option_value",$option_value);
                }
                else{
                    $select_options .= $this->html_tag("option|value=$option_value",$option_value);
                }
            }
        }
        else{
            $select_options = $this->html_tag("option|value=General","General");
        }

        return $this->html_tag("select|name=$select_name",$select_options);
    }

    /**
     * Deprecated. Takes an index and a mysql resource, and then returns a JSON object from the information.
     * You should probably use json_encode(), and if that fails, try using this.
     * @param string $index_field - the name of the field in the mysql resource you want.
     * @param <type> $data_set - the mysql resource.
     */
    function return_json($index_field, $data_set){
        echo '{';
        while ($data_fields = mysql_fetch_assoc($data_set)){
            $record_values = "";
            foreach ($data_fields as $field_name => $field_value){
                $record_values .= '"' . $field_name . '":"' . preg_replace('/\\\/', '', $this->scrub_string($field_value)) . '",';
            }

            echo '"' . $data_fields[$index_field] . '":{"":"",' . $record_values . '"":""},';
        }
        echo '"":""}';
    }
    /**
     * Accepts a path, and then creates a directory from that path,
     * and for good measure, then puts a basic "index.html" file inside it to block direct browser access.
     * Since that "index.html" directory access blocker isn't really much security, consider additional solutions with .htaccess or something.
     * @param string $path  - the directory path you want to create.
     */
    public function create_directory($path){
        error_reporting(null);
        if(mkdir($path)){
            chmod($path, 0777);
            $this->directory_access_blocker($path);
        }
        error_reporting(E_WARNING | E_ERROR);
    }
    /**
     * Places an "index.html" file that says "Directory access is forbidden." to deter folder browsing. Might be a good idea to also have
     * some rules in .htaccess preventing this for good measure.
     * @param string $path - the directory you wish yo place the "index.html" directory access blocker.
     * @param boolean $base - forgotten in the sands of time...
     */
    public function directory_access_blocker($path, $base = null){
        //Turn off error reporting
        error_reporting(null);
        $path        = ($base) ? $this->registry_keys["system"]["diskspace_path"] . $path : $path;
        $file_handle = (!is_file("$path/index.html")) ? fopen("$path/index.html", "a") : null;
        if ($file_handle){
            fwrite($file_handle,"
                <html>
                    <head>
                        <title>403 Forbidden</title>
                    </head>
                    <body bgcolor='#ffffff'>
                        <p>Directory access is forbidden.<p>
                    </body>
                </html>
            ");
        }
        //Turn it back on
        error_reporting(E_ERROR);
    }
    /**
    * Exactly what it says on the tin
    */
    public function extract_time_stamp(){
        $time_resource = mysql_fetch_assoc($this->database_query("select now() as timestamp"));
        return $time_resource["timestamp"];
    }
    /**
     * Loads a model, in case you're using the full MVC approach
     * @param string $model_file
     */
    public function load_model($model_file){
        require("{$this->registry_keys["system"]["app_path"]}/{$this->registry_keys["system"]["current_app"]}/models/$model_file");
    }
    /**
     * Accepts the path to a directory and returns an array of the files within it. Note that index.html files are skipped, because
     * in this application they're actually just an HTML file that blocks direct access to the directory.
     * @param string $target_directory - the directory that contains the files you want.
     * @return array
     */
    function extract_diskspace_content($target_directory){
        if(is_dir($target_directory)){
            $i = 0;
            $directory_content;
            $directory_handle = opendir($target_directory);
            while($directory = readdir($directory_handle)){
                if($directory != "." && $directory != ".." && $directory !== "index.html"){
                    $directory_content[$i] = $directory;
                    $i++;
                }
            }
            //Close directory
            closedir($directory_handle);
            return $directory_content;
        }
    }
    /**
     * Accepts the path to a directory on disk, and then deletes all files within
     * before deleting the directory itself.
     * @param <type> $target_directory
     */
    function erase_diskspace_directory($target_directory){
        //Read content
        $folder_content = $this->extract_diskspace_content($target_directory);
        //If there is something in there...
        if(sizeof($folder_content) > 0){
            //Remove the index.html whatever
            unlink("$target_directory/index.html");
            //Remove every file in it
            foreach ($folder_content as $file_name){
                unlink("$target_directory/$file_name");
            }
        }
        //Remove the directory itself
        rmdir($target_directory);
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
        //Stop execution if this is a show-stopping error
        if($stop_error){
            die;
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
    public function connect_db($db_host, $db_username, $db_password, $db_name){
        $db_connection = @mysql_connect($db_host, $db_username, $db_password);
        if ($db_connection){
            $this->database_query("CREATE DATABASE $db_name");
            mysql_select_db($db_name);
            $this->db_connections[$db_name] = $db_connection;
            return $db_connection;
        }
        else{
            $this->error_message("Fatal Error: Could not connect to MySQL Database '$db_name' at '$db_host', so check the db credentials in the registry.", true);
        }
    }

    /**
     * Disconnects a specified database.
     * Part of its default behavior is to run "optimize" and "analyze" against every table in the database before it actually severs the connection.
     * @param string $database_connection
     */
    public function disconnect_db($database_connection){
        //Tidy up the  tables
        foreach ($this->extract_database_tables() as $i => $table){
            $this->database_query("REPAIR TABLE $table");
            $this->database_query("ANALYZE table $table");
            $this->database_query("OPTIMIZE TABLE $table");
        }
        //Close database connection
        mysql_close($database_connection);
    }
    /**
    * Extracts ALL the records in the specified table and then spits it out in JSON format.
    * @param type $table_name
    */
    public function export_table_to_json($table_name){
        $this->json_response = @$this->sql_to_array($this->database_query("select * from $table_name"));
        if($this->json_response){
            $this->send_json_response();
        }
        else{
            die("Yikes! Export query for '$table_name' didn't go quite right");
        }
    }

    /**
     * Returns the amount of records in a table.
     *
     * @param string $table
     * @param string $filter - If you want to count only certain records, this is pretty much your WHERE clause.
     * @return int
     */
    public function count_table_records($table, $filter = null){
        $table_data = mysql_fetch_assoc($this->database_query("select count(*) as 'total_records' from $table $filter"));
        return $table_data["total_records"];
    }

    /**
     * Runs a "show tables" query against the database, then returns an array with the tables.
     * @return array
     */
    public function extract_database_tables(){
        $i            = 0;
        $tables_query = $this->database_query("show tables");
        while ($table_data = mysql_fetch_assoc($tables_query)){
            foreach ($table_data as $field => $value){
                if ($field !== 0){
                    $db_tables[$i] = $value;
                    $i++;
                }
            }
        }

        return $db_tables;
    }

    /**
     * Checks if a given table exists in the database.
     * @param string $table - the name of the table you wish to check if it exists.
     * @return boolean
     */
    public function does_table_exist($table){
        return (is_int(array_search($table, $this->extract_database_tables()))) ? true : false;
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
    public function database_query($query_string,$query_paging = null){
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
                $fields[$i] = mysql_real_escape_string("{$table}.{$field}");
            }
            
            $fields = implode(", \n", $fields);
        }
        else{
            $fields = null;
        }            
                    
        mysql_query('set character set utf8');
        
        if (is_array($select_statement)){
            foreach ($select_statement as $primary_field_name => $primary_field_value){
                $primary_field_name = mysql_real_escape_string($primary_field_name);
                $primary_field_value = mysql_real_escape_string($primary_field_value);
                $indexed_query = "\n SELECT \n {$fields} \nFROM {$table} \nWHERE {$table}.{$primary_field_name} = '$primary_field_value' \n";
                break;
            }

            foreach ($select_statement as $field_name => $field_value){
                $field_name = mysql_real_escape_string($field_name);
                $field_value = mysql_real_escape_string($field_value);
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
     * Modifies a specific database record.
     * @param string $table - the database table you wish to modify, such as "users"
     * @param <type> $field - the field you wish to modify, like "user_name"
     * @param <type> $update - the new value of the field, which can be "New User Name"
     * @param <type> $selector - the field by which you will limit the amount of modified records. i.ie "user_id"
     * @param <type> $operator - the MySQL 'operator' that compares the $selector field to the $record, such as "=" or ">"
     * @param <type> $record - the value you wish to match $selector against, like "5"
     * @param <type> $filter - any additional filtering you want, such as "LIMIT 1", etc.
     * @return boolean
     */
    public function alter_record($table, $field, $update, $selector, $operator, $record, $filter = null){
        return $this->database_query("UPDATE $table SET $field = '$update' WHERE $selector $operator '$record' $filter");
    }

    /**
     * Deletes a specific record from the database.
     *
     * @param string $table - the database table you wish to modify, such as "users"
     * @param string $column - the name of the field you will compare against
     * @param string $operator - the MySQL 'operator' that compares the $column field to the $row, such as "=" or ">"
     * @param string $row - the value you wish to compare the $column against.
     * @return boolean
     */
    public function drop_record($table, $column, $operator, $row){
        return $this->database_query("DELETE FROM $table WHERE $column $operator $row");
    }

    /**
     * Accepts a table name and an array, and generates a MySQL insert statement. This makes it less likely to get the
     * statement wrong, and makes the code a bit easier to read.
     * @example:
     * $folder_data["media_type"] = $folder_type;
     * $folder_data["media_label"] = $folder_name;
     * $folder_data["media_owner_id"] = $folder_owner;
     * $folder_data["media_category"] = $folder_category;
     * $folder_data["media_description"] = $folder_description;
     * $api->db_insert("media",$folder_data);
     * @param string $table - the name of the table
     * @param array $values - the array containing the values of the new record
     * @return boolean
     */
    public function db_insert($table, $values){
        $i = 0;
        if(is_array($values)){
            foreach($values as $field => $value){
                $fields[$i] = $field;
                $i++;
            }

            if (is_array($fields)){
                $fields = implode(",", $fields);
            }

            return $this->database_query("INSERT INTO $table ($fields)\n values ('" . implode("','", $values) . "')");
        }
        else{
            return $this->database_query("INSERT INTO $table ($fields)\n values ('$values')");
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
     * Accepts a MySQL timestamp and returns it in a new format.
     * @param string $sql_timestamp - the time stamp
     * @param string $format - the new format you want.
     * @return string
     */
    function extract_friendly_date($sql_timestamp, $format = null){
        if($format){
            $result = date($format, strtotime($sql_timestamp));
            return $result;
        }
        else{
            $result = date("m d, y", strtotime($sql_timestamp));
            return $result;
        }
    }
}
?>
