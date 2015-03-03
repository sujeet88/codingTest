<?php
/**
  @Purpose Auth Api Library for the github and bitbucket
  @created by Sujeet
  @created date 28th FEB 2015
  @modified date 28th FEB 2015
  @version 1.0.0
*/

/*include Request Exception class to Handles github and bitbucket Api specific errors */
require_once('AuthApiException.php');

/**
 * Encapsulates the cUrl methods to use github and bitbucket API's in the class
 *
 * @package make cUrl request for github and bitbucket Api's Library
 * @subpackage Core
 */
class AuthApi {

    /**
     * Default options/settings
     * @var string[string]
     */
    public $options = array    (
        'protocol' => 'https',
        'github_url' => ':protocol://api.github.com/:path?format=:format',
        'bitbucket_url' => ':protocol://api.bitbucket.org/1.0/:path?format=:format',
        'format' => 'object',
        'user_agent' => ':apitype php api',
        'timeout' => 10,
        'api_url' => null,
        'api_path' => null,
        'api_type' => null,
        'username' => null,
        'password' => null,
        'custom_errors' => array()
    );

    /**
     * Returned http header code
     * @var string
     */
    public $http_code;

    /**
     * History of the request class, for cache purposes
     * @var array
     */
    protected static $history = array();

    /**
     * Content Type to make the request
     * @var string
     */
    private $_content_type = "";

    /**
     * Default constructor, paramters takes repository url, username, password of options to instantiate and merge options from possible overrides
     * @param       string     $api_url     The Api url that dictates which API and event to use
     * @param       string     $username    The API username
     * @param       string     $password    The API password
     */
    public function __construct($api_url = null, $username = null, $password = null) {
        $options = array(
            'api_url' => $api_url,
            'username' => $username,
            'password' => $password
        );

        //configure api options
        $this->configure( $options );

        //validate api credentials
        $this->validateApiCredentials();
    }

    /**
     * Merges/Configures the passed-in options with the default options
     * @param       array       $options        Passed-in options to be merged with the class defaults
     * @return      github|bitbucket\api\Request
     */
    public function configure(array $options) {
        $this->options = $options + $this->options;

        //Verify Api's domain
        $pieces = parse_url($this->options['api_url']);
        $domain = isset($pieces['host']) ? $pieces['host'] : '';
        $api_domain = '';
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            $api_domain = $regs['domain'];
        }

        //Set api path
        if($pieces['path'][0] == '/') {
            $pieces['path'] = substr($pieces['path'], 1);
        }
        if($pieces['path'][strlen($pieces['path'])-1] == '/') {
            $pieces['path'] = substr($pieces['path'], 0, -1);
        }

        //Set api type
        if($api_domain == 'github.com') {
            $this->setOption('api_type', 'github');
            $this->setOption('api_path', 'repos/'.trim($pieces['path']).'/issues');
        } else if($api_domain == 'bitbucket.org') {
            $this->setOption('api_type', 'bitbucket');
            $this->setOption('api_path', 'repositories/'.trim($pieces['path']).'/issues');
        } else {
            $this->error_handler( 'Method Not Allowed' );
        }

        //Set user agent based on Api's
        if( !empty($this->options['api_type']) ) {
            $user_agent = str_replace(':apitype', $this->options['api_type'], $this->options['user_agent']);
            $this->setOption('user_agent', $user_agent);
        }
    }

    /**
     * validate api required parameters
     *
     * @throws      github|bitbucket\api\AuthApiException
     */
    function validateApiCredentials() {        
        if( $this->options['api_type'] == "" || $this->options['api_path'] == "" ) {
            $this->error_handler( 'Repository url not valid' );
        } elseif($this->options['username'] == "") {
            $this->error_handler( 'Username Required' );
        } else if($this->options['password'] == "") {
            $this->error_handler( 'Password Required' );
        }
    }

    /**
     * Send a GET|POST HTTP request for github and bitbucket Api's Library
     * @param       array|string    $parameters     Additional parameters to send as data seperate from the url
     * @param       string          $httpMethod     Standard HTTP/1.1 invokation method
     * @param       array           $options        Passed-in options to override the default options
     * @return      object                          Object containing the returned web response
     */
    public function send($parameters = array(), $httpMethod = 'POST', array $options = array()) {
        $initialOptions = null;
        $response = null;

        if ( ! empty( $options ) ) {
            $initialOptions = $this->options;
            $this->configure( $options );
        }

        $response = $this->doSend( $parameters, $httpMethod );
        $response = $this->decodeResponse( $response );

        if ( isset( $initialOptions ) ) {
            $this->options = $initialOptions;
        }

        return $response;
    }

    /**
     * Override for {@link send()}; Sends a GET HTTP request
     * @param       array|string    $parameters     Additional parameters to send as data seperate from the url
     * @param       array           $options        Passed-in options to override the default options
     * @return      object                          Object containing the returned web response
     */
    public function get($parameters = array(), array $options = array()) {
        return $this->send( $parameters, 'GET', $options );
    }

    /**
     * Override for {@link send()}; Sends a POST HTTP request
     * @param       array|string    $parameters     Additional parameters to send as data seperate from the url
     * @param       array           $options        Passed-in options to override the default options
     * @return      object                          Object containing the returned web response
     */
    public function post($parameters = array(), array $options = array()) {
        return $this->send( $parameters, 'POST', $options );
    }

    /**
     * Decodes the JSON text into a usable PHP stdObject
     * @param       mixed       $response       The raw HTTP response from the Github|Bitbucket API
     * @return      object                      Object containing the returned web response
     *
     * @throws      github|bitbucket\api\AuthApiException
     */
    protected function decodeResponse($response) {
        $response_obj = null;
        if ( $this->options['format'] === 'object' && ( is_object($response) || is_string($response) ) ) {
            $response_obj = json_decode( $response, false );
        }

        return $response_obj;

        $this->error_handler( __CLASS__ . ' does not support <em>' . $this->options['format'] . '</em> format.' );
    }

    /**
     * Sends all set parameters to the API url
     * @param       array|string    $parameters     Additional parameters to send as data seperate from the url
     * @param       string          $httpMethod     Standard HTTP/1.1 invokation method
     * @return      mixed
     *
     * @throws      github|bitbucket\api\AuthApiException
     */
    public function doSend($parameters = array(), $httpMethod = 'POST') {
        $this->updateHistory();
        $currentOptions = array();

        // Makes sure to convert object format to json so the library can decode it into a stdClass
        if ($this->options['format'] === 'object') {
            $currentOptions['format'] = 'json';
        }

        $currentOptions = $currentOptions + $this->options;

        //Set curl options
        $curlOptions = $this->setCurlOptions($parameters, $currentOptions, $httpMethod);

        //Initialize the curl
        $curl = curl_init();

        //Assign the curl options
        curl_setopt_array( $curl, $curlOptions );

        if ( ($response = curl_exec( $curl )) === false ) {
            $this->error_handler( 'cURL Error: ' . curl_error( $curl ), curl_errno( $curl ) );
        }

        //Validate repository issue is posted or not
        $this->validateResponse($response);

        //Handle curl errors
        $headers = curl_getinfo( $curl );
        $errorNumber = curl_errno( $curl );
        $errorMessage = curl_error( $curl );

        //Close the curl
        curl_close( $curl );

        $this->content_type = $headers['content_type'];
        $this->http_code = $headers['http_code'];

        //Make custom error message based on OAuth Api Exception class
        if ( ! array_key_exists($headers['http_code'], AuthApiException::$acceptableCodes ) ) {
            $custom_message = "";
            if ( array_key_exists($headers['http_code'], $currentOptions['custom_errors'] ) ) {
                $custom_message = $currentOptions['custom_errors'][$headers['http_code']];
            } else {
                $custom_message = null;
            }

            return $custom_message;
        }

        if ( ! empty( $errorNumber ) ) {
            $this->error_handler( 'error ' . $errorNumber );
        }

        return $response;
    }

    /**
     * Records the requests times
     * When 30 request have been sent in less than a minute,
     * sleeps for two second to prevent reaching the assumed Github|Bitbucket API limitation.
     *
     * @access protected
     * @return void
     */
    protected function updateHistory() {
        self::$history[] = time();

        if ( 30 === count( self::$history ) ) {
            if ( reset( self::$history ) >= (time() - 30) ) {
                sleep( 2 );
            }

            array_shift( self::$history );
        }
    }

    /**
     * Set curl options
     * @param       array           $parameters         The parameter option's name/desc
     * @param       currentOptions  $currentOptions     The current option's value
     * @return      array                               Returns curl options
     *
     * @throws      github|bitbucket\api\AuthApiException
     */
    protected function setCurlOptions($parameters = array(), $currentOptions = array(), $httpMethod = 'POST') {
        //Get Api's full url along with path and format
        $url = $this->generateApiUrl($parameters, $currentOptions);

        $curlOptions = array();
        if ( $currentOptions['username'] ) {
            $curlOptions += array( CURLOPT_USERPWD => sprintf( '%s:%s', $currentOptions['username'], $currentOptions['password'] ) );
        }

        if ( ! empty( $parameters ) &&  $parameters['title'] != '') {
            $queryString = "";
            if($this->options['api_type'] == 'bitbucket') {
                //Make query string for bitbucket api
                $queryString = utf8_encode( http_build_query( $parameters, '', '&' ) );
            } else if($this->options['api_type'] == 'github') {
                //Make query string for github api
                $queryString = json_encode($parameters);
            }

            //Set curl parameter based on called method
            switch ( $httpMethod ) {
                case 'GET':
                    $url .= "&".$queryString;
                    break;
                case 'POST':
                default:
                    $curlOptions += array(
                                        CURLOPT_POST => true,
                                        CURLOPT_POSTFIELDS => $queryString,
                                        CURLOPT_HTTPHEADER => array("Content-Length: ".strlen($queryString)."")
                                    );
                    break;
            }
        } else {
            $this->error_handler('Title Required', (int)$headers['http_code']);
        }

        //Set curl parameter based on called method
        $curlOptions += array(
                            CURLOPT_URL => $url,
                            CURLOPT_USERAGENT => $currentOptions['user_agent'],
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => $currentOptions['timeout'],
                            CURLOPT_SSL_VERIFYPEER => false
                        );

        return $curlOptions;
    }

    /**
     * Generate Api's full url along with path and format
     * Set an description in the parameters array
     * @param       array               $parameters         The parameter option's name/desc
     * @param       currentOptions      $currentOptions     The current option's value
     * @return      string          Returns Api's full url
     *
     * @throws      github|bitbucket\api\AuthApiException
     */
    protected function generateApiUrl(&$parameters, $currentOptions) {
        // Set Api's url based on passed api type
        if($this->options['api_type'] == 'github') {
            $opt_url = $this->options['github_url'];
            //Initialize description variable based on selected Api
            if(isset($parameters['desc'])) {
                $parameters['body'] = $parameters['desc'];
                unset($parameters['desc']);
            }
        } else if($this->options['api_type'] == 'bitbucket') {
            $opt_url = $this->options['bitbucket_url'];
            //Initialize description variable based on selected Api
            if(isset($parameters['desc'])) {
                $parameters['content'] = $parameters['desc'];
                unset($parameters['desc']);
            }
        } else {
            $this->error_handler( 'Method Not Allowed', (int)$headers['http_code'] );
        }

        // Set Api's full url along with path and format
        $url = strtr( $opt_url, array(
                                        ':protocol' => $this->options['protocol'],
                                        ':path' => trim(implode( "/", array_map( 'urlencode', explode( "/", $this->options['api_path'] ) ) ), '/') . (substr($this->options['api_path'], -1) == '/' ? '/' : ''),
                                        ':format' => $currentOptions['format']
                                       ) );
        return $url;
    }


    /**
     * Validate response
     *
     * @access      public
     * @return      void
     *
     * @throws      github|bitbucket\api\AuthApiException
     */
    public function validateResponse($response = NULL) {
        //Check issue is posted or not
        $response_obj = json_decode($response, false);
        $res_err_mes = 'Resource not found!';
        if($response_obj === NULL) {
            $this->error_handler( $res_err_mes );
        } else if(is_object($response_obj) && !isset($response_obj->title)) {
            $this->error_handler( $res_err_mes );
        }
    }

    /**
     * Set an option
     * @param       string      $name       The option's name/key
     * @param       mixed       $value      The option's value
     * @return      Request                 Returns instance of self
     */
    public function setOption($name, $value) {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * Get an option
     * @param       string      $name       The option's name/key
     * @param       mixed       $default    The object that returns in the event the option doesn't exists
     * @return      mixed                   Either the option requested or the default value specified
     */
    public function getOption($name, $default = null) {
        return isset( $this->options[$name] ) ? $this->options[$name] : $default;
    }

    /**
     * Handle execption errors
     * @param       string      $message        The error message
     * @param       integer     $code           The error code
     * @return      void                        Display the error
     */
    public function error_handler($message = null, $code = null) {
        try {
            try {
                throw new AuthApiException($message, $code);
            } catch (AuthApiException $e) {
                // rethrow it
                throw $e;
            }
        } catch (Exception $e) {
            echo PHP_EOL . PHP_EOL ."Error: {$e->getMessage()}". PHP_EOL . PHP_EOL;
            exit;
        }
    }
}// End OAuthApi Class