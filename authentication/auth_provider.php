<?php

/**
 * class file for the authentication provider class. The only function it has is the get_pwhash(String
 * $efaCloud_user_id). This here is a dummy stub for any developer to build on.
 */
class Auth_provider
{

    /**
     * This client id is the one which was agreed with the auth provider to authorize the efaCloud server for
     * password hash retrieval. 
     */
    private $client_id;

    /**
     * This client key is the one which was agreed with the auth provider to authorize the efaCloud server for
     * password hash retrieval.
     */
    private $client_key;

    /**
     * The server to provide the authentication. It is strongly recommended to use https.
     */
    private $url_api = "";

    /**
     * The token which represents an error during pw hash retrieval.
     */
    private $error_token = "#ERROR#";

    /**
     * public Constructor.
     */
    public function __construct ()
    {
        // enter anything which is needed upon cionstruction here.
    }

    /**
     * get a password hash to validate an entered password for a specific efa cloud user id. The hash will be
     * verified using the standard password_verify($entered_data["Passwort"], $passwort_hash); method.
     * 
     * @param String $efaCloud_user_id            
     * @return password hash or '-' in case of failure
     */
    public function get_pwhash (String $efaCloud_user_id)
    {
        // add all functionality to get a password hash by your auth provider here.
        return '-';
    }
}
?>
