<?php
/**
  @purpose Post issue in the repository based on repository url using AuthApi class.
  @created by Sujeet
  @created date 28th FEB 2015
  @modified date 28th FEB 2015
  @version 1.0.0
**/

/* disbale the error messages */
ini_set('display_errors', 1);

/* include AuthApi class to Handles the github and bitbucket request */
require_once('includes/AuthApi.php');

/* create object of postIssue class */
$index = new index($argv);

/* Send request to post repository issue */
$index->sendRequest();


/**
   Index class to use Auth Api Library methods for the github and bitbucket
   Send request to github|bitbucket to post repository issue based on passed parameters.
   @author Sujeet
   @link Github http://developer.github.com/v3/issues/#create-an-issue
   @link Bitbucket https://confluence.atlassian.com/display/BITBUCKET/issues+Resource#issuesResource-POSTanewissue
   @version   1.0.0
   @created date 28th FEB 2015
   @modified date 28th FEB 2015
**/
class index {

    /**
     * Username
     * @var string
     */
    private $username = "";

    /**
     * Password
     * @var string
     */
    private $password = "";

    /**
     * Repository url
     * @var string
     */
    private $repo_url = "";

    /**
     * Issue title
     * @var string
     */
    private $title = "";

    /**
     * Issue description
     * @var string
     */
    private $desc = "";

    /**
     * Instance of AuthApi
     * @var string
     */
    private $AuthApi = null;

    /**
     * Default constructor, paramters takes 
     * @param       string     $api_url     The Api url that tells which API and event to use
     * @param       string     $username    Username
     * @param       string     $password    Password
     */

    /**
     * Default constructor, paramters takes an array of options
     * @param		array		$argv		Passed-in options to assign the required params
     */
    public function __construct(array $argv = array()) {
        //verify should be only access by command line
        if (PHP_SAPI !== 'cli') {
            die( PHP_EOL .'This file can be only run by command line'. PHP_EOL );
        }

        //check register_argc_argv is enable
        if(count($argv) == 0) {
            die( PHP_EOL .'Enable register_argc_argv in php.ini'. PHP_EOL );
        }

        // Assign passed arguments into variables
        list($filename, $this->username, $this->password, $this->repo_url, $this->title, $this->desc) = $argv;

        if($this->AuthApi == null) {
            //create object of AuthApi
            if(class_exists('AuthApi')) {
                $this->AuthApi = new AuthApi($this->repo_url, $this->username, $this->password);
            } else {
                die( PHP_EOL .'AuthApi class not exists'. PHP_EOL );
            }
        }
    }

    /**
     * Validate required parameters
     * Send a request for github|bitbucket Api's Library
     *
     * @Show error|success message based on returned web response
     */
    public function sendRequest() {
        if($this->inputValidation()) {
            //Send request to create repository issue on github|bitbucket
            $response = $this->AuthApi->post(array('title' => $this->title, 'desc' => $this->desc));

            //show message based on return response from Api's
            if(is_object($response) && isset($response->title)) {
                echo PHP_EOL . ucwords($this->AuthApi->getOption('api_type')) .' Repository issue posted successfully'. PHP_EOL;
            } else {    
                echo PHP_EOL . ucwords($this->AuthApi->getOption('api_type')) .' Repository issue not posted successfully'. PHP_EOL;
            }
        }
    }

    /**
     * validate api required parameters
     *
     * @access protected
     * @return void
     */
    protected function inputValidation() {
        if($this->username == "") {
            echo PHP_EOL .'Username Required'. PHP_EOL;
            return false;
        } else if($this->password == "") {
            echo PHP_EOL .'Password Required'. PHP_EOL;
            return false;
        } else if($this->repo_url == "") {
            echo PHP_EOL .'Repository url Required'. PHP_EOL;
            return false;
        } else if($this->title == "") {
            echo PHP_EOL .'Title Required'. PHP_EOL;
            return false;
        }
        return true;
    }
}

?>