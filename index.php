<?php
    //Start the session
    session_start();
    // - Manually load api, registry, and core class
    error_reporting(E_ERROR);    
    require_once("resources/core/core.registry.php");
    require_once("resources/core/core.api.class.php");
    require_once("resources/core/core.afx.class.php");        
    // - Instance of core class, and app.class.php for the current application (if it exists)
    global $afx;
	$afx = new afx($registry_keys);
    // - Class Auto Loader
    function class_autoloader($class){
		global $afx;
		//Try the app directory
		if(is_file("$afx->app_path/$class/app.class.php")){
			require_once("$afx->app_path/$class/app.class.php");
			return;
		}		
		//If that didn't work, check in the classlib
		if(is_file("resources/classlib/$class.class.php")){
			require_once("resources/classlib/$class.class.php");
			return;
		}
    }
    spl_autoload_register("class_autoloader");	
    // - If it exists, run template preprocessor
    if(is_file($afx->template_preprocessor)){
        require_once($afx->template_preprocessor);
    }
    // - If the controller exists, load it
    if(is_file("$afx->app_path/$afx->current_app/app.controller.php")){
        require_once("$afx->app_path/$afx->current_app/app.controller.php");
    }
	// - Controller doesn't exist, so try to use the generic
	elseif(is_file("$afx->app_path/$afx->current_app/app.class.php")){
		try{		
			//Try to dynamically create an instance of the app's class
			$app_instance = new $afx->current_app($afx->registry_keys,$afx->cookie());
			//Pass the app's current action to a variable
			$class_function = $afx->current_action;
			//If no particular action is specified, try to run the index function
			if(!$afx->current_action){
				$class_function = "main";
			}
			//First, get the list of legit class methods
            //Then get the list of API methods
            $legitClassMethods = array_diff(get_class_methods($afx->current_app),get_class_methods("api"));
			//Make sure only legit class functions are run, but definitely not the ones from the API            
			if(in_array($class_function,$legitClassMethods)){
				//Try to run $afx->current_action dynamically
				$app_instance->$class_function($afx->action_value,$afx->request());			
			}
			//Push the app's content pipe to the framework's page_content template variable
			$afx->set_template_value("page_content",$app_instance->content_pipe);
		}
		catch(Exception $error){
			//Oh well, we tried, 404
			$afx->error_message("page_not_found", true);
		}
	}
    else{
        $afx->error_message("page_not_found", true);
    }
    // - If it exists, run template preprocessor
    if(is_file($afx->template_postprocessor)){
        require_once($afx->template_postprocessor);
    }    
    // - Showtime
    $afx->render_html();    
?>
