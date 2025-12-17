<?php

/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Class to handle an application sessions pool to manage concurrency and throttle load. Two sorts of sessions
 * exist: Web-sessions for web access which are managed by the PHP session framework and only mirrored into
 * the application sessions pool and api-sessions for API access. Both are pooled in the ../log/sessions
 * directory, each type represented by a session file, named with its session's ID. The session file starts
 * with three numbers: started at (Unix timestamp, seconds - float); refreshed at (Unix timestamp, seconds -
 * float); user ID (integer) - all terminated by a ";". Session have a keep-alive limit and a lifetime. If a
 * session is inactive until the keep-alive limit is hit or actively hits the lifetime end, it is removed from
 * the application session pool and its PHP-session is closed. Regenerate the session to keep it alive beyond
 * its lifetime end. This will change the session id regularly to mitigate spoofing risks.
 */
class Tfyh_app_sessions
{

    /**
     * see "https://www.php.net/manual/en/session.configuration.php"
     */
    private static $php_ini_defaults = ['session.name' => 'PHPSESSID','session.save_handler' => 'files',
            'session.auto_start' => '0','session.gc_probability' => '1','session.gc_divisor' => '100',
            'session.gc_maxlifetime' => '1440','session.serialize_handler' => 'php',
            'session.cookie_lifetime' => '0','session.cookie_path' => '/','session.cookie_domain' => '',
            'session.cookie_secure' => '0','session.cookie_httponly' => '0','session.cookie_samesite' => '',
            'session.use_strict_mode' => '0','session.use_cookies' => '1','session.use_only_cookies' => '1',
            'session.referer_check' => '','session.cache_limiter' => 'nocache','session.cache_expire' => '180',
            'session.use_trans_sid' => '0','session.trans_sid_tags' => 'a=href,area=href,frame=src,form=',
            'session.trans_sid_hosts' => "\$_SERVER['HTTP_HOST']",'session.sid_length' => '32',
            'session.sid_bits_per_character' => '4','session.upload_progress.enabled' => '1',
            'session.upload_progress.cleanup' => '1','session.upload_progress.prefix' => 'upload_progress_',
            'session.upload_progress.name' => 'PHP_SESSION_UPLOAD_PROGRESS',
            'session.upload_progress.freq' => '1%','session.upload_progress.min_freq' => '1',
            'session.lazy_write' => '1'
    ];

    /**
     * see "https://www.php.net/manual/en/features.session.security.management.php"
     */
    private static $php_ini_security = ['session.cookie_secure' => '1',
            // If off cookies will bes sent also over http, not only https
            'session.cookie_httponly' => '1',
            // Marks the cookie as accessible only through the HTTP protocol
            'session.cookie_samesite' => 'Strict',
            // assert that a cookie ought not to be sent along with cross-site requests.
            'session.use_strict_mode' => '1',
            // see Non-adaptive Session Management. "Warning: Do not misunderstand the DoS risk.
            // session.use_strict_mode=On is mandatory for general session ID security! All sites are advised
            // to enable session.use_strict_mode. "
            'session.sid_length' => '26',
            // the longer, the better. typical setting is 26
            'session.sid_bits_per_character' => '5'
        // typical setting is 5
    ];

    /**
     * The grace period does keep an obsolet session for the ase that a Set-cookie header was not recieved by
     * the client browser. Value is in seconds.
     */
    private static $grace_period = 60;

    public $max_session_duration;

    public $max_session_keepalive;

    public $max_concurrent_sessions;

    private $toolbox;

    private $debug_on;

    private $debug_file = "../log/debug_sessions.log";

    private $sessions_dir = "../log/sessions/";

    /**
     * Constructir to cache parameters and set the security level.
     * 
     * @param array $init_settings
     *            The tfyh init settings of $toolbox->config
     * @param int $debug_level
     *            the debug level of $toolbox->config
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $init_settings = $toolbox->config->settings_tfyh["init"];
        $this->max_session_keepalive = (isset($init_settings["max_session_keepalive"])) ? $init_settings["max_session_keepalive"] : 600;
        $this->max_session_duration = (isset($init_settings["max_session_duration"])) ? $init_settings["max_session_duration"] : 43200;
        $this->max_concurrent_sessions = (isset($init_settings["max_concurrent_sessions"])) ? $init_settings["max_concurrent_sessions"] : 25;
        if (! file_exists("../log/sessions"))
            mkdir("../log/sessions");
        $this->debug_on = true; // ($toolbox->config->debug_level > 0);
        $this->toolbox = $toolbox;
        // security initialization is triggered by toolbox intialization, that is before a session_start()
        // call.
        // $this->init_security();
        // file_put_contents("../log/php_ini.log", $this->log_security());
    }

    /* -------------------------------------------------------- */
    /* ------------------ WEB SESSIONS ------------------------ */
    /* -------------------------------------------------------- */
    
    /**
     * Start a web-session, i. e. use the PHP session framework calling session_start() and session_id() and
     * then create or update an application session file with the session id as provided by the PHP session
     * manager. The user id will be retreived from the $this->toolbox->users->session_user record. This
     * includes PHP generated web-session in the applications session pool in order to control the amount of
     * concurrent sessions, because web-sessions do not know of each other nor of web-sessions.
     * 
     * @param String $caller
     *            an arbitrary String for logging. Log contains "by $caller"
     * @param Tfyh_socket $socket
     *            The data base connection socket.
     * @return bool true if available and registered, false else. No session id is returned. Get it by using
     *         the PHP function session_id().
     */
    public function web_session_start (String $caller, Tfyh_socket $socket)
    {
        // remove all obsolete sessions first to prevent from reuse
        $open_sessions_count = $this->cleanse_and_count_sessions();
        // load the web-session context.
        $start_res = true;
        if (session_status() === PHP_SESSION_NONE)
            $start_res = session_start();
        if (! $start_res) {
            echo "Failed to start a web-session context. Most probably some text was already echoed. " .
                     "This can also happen, if a class file has an unvisible character before the '&lt;?php' tag.";
            exit();
        }
        // get session and user ids.
        $session_id = session_id();
        $existing_session = $this->read_session($session_id);
        $existing_session_user_id = (isset($existing_session["user_id"])) ? intval(
                $existing_session["user_id"]) : - 1;
        $preset_user = (isset($this->toolbox->users->session_user) &&
                 isset($this->toolbox->users->session_user["@id"])) ? intval(
                        $this->toolbox->users->session_user["@id"]) : - 1;
        // get the user id. First priority: Existing session's user id, 2nd priority preset user id (for new
        // sessions)
        $user_id = ($existing_session !== false) ? $existing_session_user_id : (($preset_user !== false) ? $preset_user : - 1);
        // set the globally availabl session user
        $session_user = ($user_id < 0) ? $this->toolbox->users->get_empty_user() : $this->toolbox->users->get_user_for_id(
                $user_id, $socket);
        if ($session_user === false) // This can happen, if the user ID of the session file does no more
                                     // exist.
            $session_user = $this->toolbox->users->get_empty_user();
        $this->log_event(i("Fkzrl9|Current web session user...", $user_id), is_array($session_user));
        $this->toolbox->users->set_session_user($session_user);
        // read the session file of the applications session pool.
        if ($existing_session == false) {
            // if there is none, create a new application session file.
            $this->log_event(i("a9ihqL|Starting new web session...", $session_id, $user_id, $caller), 
                    ($start_res !== false));
            return $this->session_create($user_id, $session_id, $open_sessions_count);
        } else {
            // else update the application session file.
            $this->log_event(i("AGz4Iv|Starting existing web se...", $session_id, $user_id, $caller), 
                    ($start_res !== false));
            return $this->session_verify_and_update($user_id, $session_id);
        }
    }

    /**
     * Updates (limits) the current web-session's lifetime to the $grace_period from now, generates a new
     * web-session, i.e. creates a new session id, sets the current session to read only, sends the Set-cookie
     * headers to the client and stores the new session file to the application sessions pool. The current web
     * session must be started to make this work. Note the sequence: the lifetime will always be updated, even
     * if the start of a new session fails.
     * 
     * @param Tfyh_socket $socket
     *            The data base connection socket.
     * @return bool true if available and registered, false else. No session id is returned. Get it by using
     *         the PHP function session_id().
     */
    public function web_session_regenerate_id (Tfyh_socket $socket)
    {
        // get session id of an existing session
        $current_web_session_id = session_id();
        // get existing session's user_id
        $session = $this->read_session($current_web_session_id);
        $user_id = $session["user_id"];
        $this->log_event(i("F7Ctd4|Limiting °%1° for °%2°", $current_web_session_id, $user_id), true);
        // provide the obsolete session with a grace period in case of network issues.
        $grace_period = Tfyh_toolbox::timef() + self::$grace_period;
        $this->write_session($grace_period, $grace_period, $user_id, $current_web_session_id);
        
        session_regenerate_id(); // send Set-Cookie headers
        $new_sid = session_id(); // get the new session id
                                 // close the old and new sessions
        session_write_close();
        // re-open the new session
        session_id($new_sid);
        return $this->web_session_start("regenerate_id", $socket);
    }

    /* -------------------------------------------------------- */
    /* ------------------ API SESSIONS ------------------------ */
    /* -------------------------------------------------------- */
    
    /**
     * Start an api-session, i. e. create or update an application session file. The user must be
     * authenticated and authorized before. This will not change an existing PHP session. Therefore the global
     * $_SESSION variable is not available for API transaction handling. Application sessions pooling is used
     * in order to control the amount of concurrent users, because api-sessions do not know of each other nor
     * of web-sessions.
     * 
     * @param int $user_id
     *            the user ID. Must be > 0, anonymous user do never start an API-sessions.
     * @param Tfyh_socket $socket
     *            The data base connection socket.
     * @param String $session_id
     *            the ID = file name of the session file to reuse if an existing session. Omit or set to null
     *            to check for any existing session. Set to "new" (or any String < 10 characters) to force the
     *            creation of a new session, even if one is still existing.
     * @return bool|String the session ID if a session was available and created or reused, false else.
     */
    public function api_session_start (int $user_id, Tfyh_socket $socket, String $session_id = null)
    {
        // remove all obsolete sessions first to prevent from reuse
        $open_sessions_count = $this->cleanse_and_count_sessions();
        // if no session id is given, try to find an existing one first. This happens, if a
        // user-password-authentication is used instead of a user-session_id-authentication
        if (is_null($session_id))
            $session_id = $this->get_api_session_id($user_id);
        // if there is no session available, create one.
        $create = (strlen($session_id) == 0) || ($this->read_session($session_id) === false);
        // set the globally available session user
        $session_user = ($user_id < 0) ? $this->toolbox->users->get_empty_user() : $this->toolbox->users->get_user_for_id(
                $user_id, $socket);
        $this->toolbox->users->set_session_user($session_user);
        if ($create) {
            $session_id = "~" . Tfyh_toolbox::create_uid(30); // this session id will have 41 characters.
            $this->log_event(i("bYhMEv|Starting new api session...", $session_id), true);
            $created = $this->session_create($user_id, $session_id, $open_sessions_count);
            return ($created) ? $session_id : false;
        } else {
            $updated = $this->session_verify_and_update($user_id, $session_id);
            $logged = $this->log_event(i("s0hHTA|Updated existing api ses...", $session_id), $updated);
            return $session_id;
        }
    }

    /**
     * This updates the current api-sessions lifetime and keep-alive period to self::$grace_period seconds
     * from now and starts a new api-session. Note the sequence: the lifetime will always be updated, even if
     * the start of a new session fails.
     * 
     * @param String $current_api_session_id
     *            the current API session's ID. Its lifetime will be updated.
     * @param Tfyh_socket $socket
     *            The data base connection socket.
     * @return bool|String the session ID if a new session was available and started, false else.
     */
    public function api_session_regenerate_id (String $current_api_session_id, Tfyh_socket $socket)
    {
        // get existing session's user_id
        $session = $this->read_session($current_api_session_id);
        $user_id = $session["user_id"];
        $this->log_event(i("To1Ea4|Limiting °%1° for °%2°", $current_api_session_id, $user_id), true);
        // update the current session's lifietime and keep alive period with a grace period in case of network
        // issues.
        $grace_period = Tfyh_toolbox::timef() + self::$grace_period;
        $this->write_session($grace_period, $grace_period, $user_id, $current_api_session_id);
        // start the new session
        return $this->api_session_start($user_id, $socket, "new");
    }

    /* -------------------------------------------------------- */
    /* ------------- COMMON SESSION CLOSING ------------------- */
    /* -------------------------------------------------------- */
    
    /**
     * Close a web session. This does A) remove the session file from the session directory and B) destroy the
     * active PHP session.
     * 
     * @param String $cause
     *            the cause why the session was closed. Used for logging.
     * @param String $cause
     *            the cause why the session was closed. Used for logging.
     * @param String $session_id
     *            the ID of the web-session to close. This is only used by the private function
     *            Tfyh_app_sessions::cleanse_and_count_sessions(). Do not use it elswhere, but stay with the
     *            default of "".
     */
    public function web_session_close (String $cause, String $session_id = "")
    {
        // get the PHP session ID, if no session ID is provided.
        if (strlen($session_id) == 0) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            $session_id = session_id();
        }
        if (strlen($session_id) == 0)
            return $this->log_event(i("uL8Ig1|No active web session to..."), false);
        
        // remove the app session file
        $unlink_success = (file_exists($this->sessions_dir . $session_id)) ? unlink(
                $this->sessions_dir . $session_id) : true;
        if (! $unlink_success)
            $this->log_event(i("AsPYhV|Removing session °%1°.", $session_id, $cause), $unlink_success);
        
        // close the PHP session, if no session ID was provided.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            $destroy_result = session_destroy();
            $this->log_event(i("k1aGJk|Destroying session °%1°.", $session_id, $cause), $destroy_result);
        }
    }

    /**
     * Close an api-session. This removes the session file from the session directory.
     * 
     * @param String $cause
     *            the cause why the session was closed. Used for logging.
     * @param String $session_id
     *            the ID of the api-session to close.
     */
    public function api_session_close (String $cause, String $session_id)
    {
        if (strlen($session_id) == 0)
            return $this->log_event(i("lsbpkz|Closing °%1°: No such ap...", $session_id), false);
        
        // remove the app session file
        $unlink_success = unlink($this->sessions_dir . $session_id);
        if (! $unlink_success)
            $this->log_event(i("legYF5|Removing session °%1°.", $session_id, $cause), $unlink_success);
    }

    /**
     * Log session security settings
     */
    private function init_security ()
    {
        foreach (self::$php_ini_defaults as $key => $default) {
            $value = ini_get($key);
            $secure = isset(self::$php_ini_security[$key]) ? self::$php_ini_security[$key] : false;
            if ($secure !== false) {
                if (strcmp($value, $secure) != 0)
                    ini_set($key, $secure);
            } elseif (strcmp($value, $default) != 0)
                ini_set($key, $default);
        }
    }

    /* -------------------------------------------------------- */
    /* ---------- LOG AND SESSION FILE HANDLING --------------- */
    /* -------------------------------------------------------- */
    
    /**
     * Log the session security settings.
     */
    private function log_security ()
    {
        $log_security = "PHP ini settings log.\n";
        $log_security .= "Checking against upgraded security and PHP defaults.\n";
        foreach (self::$php_ini_defaults as $key => $default) {
            $value = ini_get($key);
            if (array_key_exists($key, self::$php_ini_security)) {
                $secure = self::$php_ini_security[$key];
                if (strcmp($value, $secure) == 0)
                    $log_security .= "-- '$key' value is secure '$secure'.\n";
                elseif (strcmp($value, $default) == 0)
                    $log_security .= "-! '$key' value '$value' is not secure but default '$default'.\n";
                else
                    $log_security .= "!! '$key' value '$value' is neither secure '$secure' nor default '$default'.\n";
            } else {
                if (strcmp($value, $default) == 0)
                    $log_security .= "-- '$key' value is default '$default'.\n";
                else
                    $log_security .= "-! '$key' value '$value' is not default '$default'.\n";
            }
        }
        $session_save_path = session_save_path();
        if ($session_save_path == false)
            $log_security .= "!! session_save_path() is false.\n";
        else
            $log_security .= "session path '$session_save_path' properties:\n";
        $session_dir_stat = stat(session_save_path());
        if ($session_dir_stat == false)
            $log_security .= "!! stat() failed..\n";
        else
            foreach ($session_dir_stat as $key => $value)
                if (! is_numeric($key))
                    $log_security .= ".. '$key' = '$value'.\n";
        $session_dir_permissions = fileperms($session_save_path);
        include_once "../classes/tfyh_audit.php";
        if ($session_dir_permissions == false)
            $log_security .= "!! fileperms() failed.\n";
        else
            $log_security .= ".. file permissions of directory = '" . $session_dir_permissions . "'.\n";
        // Tfyh_audit::permissions_string($session_dir_permissions) . "'.\n";
        
        return $log_security;
    }

    /**
     * Put a message to the debug log and in case of errors also to the error log.
     * 
     * @param String $message
     *            the mesdsage to be put. Use a text that allows appending ". Successful" or ". Failed".
     * @param bool $success
     *            true in case of success, false else. False will cause also appending a lessage to the error
     *            log.
     * @param String $cause
     *            the cause for a failure. Will be ignored if $success == true or $cause is empty. Default is
     *            "";
     * @return bool $success - unchanged.
     */
    private function log_event (String $message, bool $success, String $cause = "")
    {
        $because_of = (! $success && (strlen($cause) > 0)) ? ". " . i("vKw9sL|Cause") . ": " . $cause : "";
        $result = ($success) ? i("8bTeiG|Successful") : i("H4WzFD|Failed") . $because_of;
        if ($this->debug_on) {
            file_put_contents($this->debug_file, 
                    date("Y-m-d H:i:s") . ": " . $message . ". " . $result . "\n", FILE_APPEND);
        }
        if (! $success)
            $this->toolbox->logger->log(2, 0, $message . $result);
        return $success;
    }

    /**
     * Get a token based on a hash of the clients IP-address. This is used to differentiate sessions of
     * different clients which may use he very same user ID. Obviously this is not 100% safe, but 99% should
     * be ok.
     * 
     * @return string the hashed token (10 characters)
     */
    private function client_token ()
    {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        return substr(md5(strval($ip_address)), 0, 10);
    }

    /**
     * Write a session file. If this is an update, the file is read before and if the session lifetime is
     * lower or equal to (now + self::$grace_period + 1 second) the update is refused.
     * 
     * @param float $alive_until
     *            the time until which the session is kept alive without further activity
     * @param float $ends_on
     *            the time on which the session will end, even, if active
     * @param int $user_id
     *            the user of the session
     * @param String $session_id
     *            the id of the session
     * @return bool true, if successful, else false.
     */
    private function write_session (float $alive_until, float $ends_on, int $user_id, String $session_id)
    {
        if ($user_id < 0)
            // do not open a counted session for anonymous users.
            return true; // i. e. no session limit hit.
        
        $existig_session = $this->read_session($session_id);
        if (($existig_session !== false) &&
                 ($existig_session["ends_on"] < (Tfyh_toolbox::timef() + self::$grace_period + 1)))
            return $this->log_event(i("Wv9xkv|Writing session °%1°", $session_id), false, 
                    i("6gdISg|no more updates, too clo..."));
        $session_file_contents = $alive_until . ";" . $ends_on . ";" . $user_id . ";" . $this->client_token() .
                 ";" . "alive until " . date("Y-m-d H:i:s", intval($alive_until)) . ", ends on " .
                 date("Y-m-d H:i:s", intval($ends_on));
        $session_file = $this->sessions_dir . $session_id;
        return file_put_contents($session_file, $session_file_contents);
    }

    /**
     * Read and parse a session file into "alive_until", "ends_on", and "user_id"
     * 
     * @param String $session_id
     *            the session ID = filename
     * @return the session as associative array["alive_until" => float, "ends_on" => float, "user_id" => int]
     *         or false on errors
     */
    private function read_session (String $session_id)
    {
        $session_file = $this->sessions_dir . $session_id;
        if (! file_exists($session_file))
            return false; // This is a normal situation when starting a new web session.
        $session_file_contents = file_get_contents($session_file);
        if ($session_file_contents === false)
            return $this->log_event(i("3Srdaj|Failed to read existing ...") . " " . $session_file, false);
        $parts = explode(";", $session_file_contents);
        if (count($parts) < 3)
            return $this->log_event(i("8I1Ul0|Malformatted session fil...") . " " . $session_file, false);
        $session = array();
        $session["alive_until"] = floatval($parts[0]);
        $session["ends_on"] = floatval($parts[1]);
        $session["user_id"] = intval($parts[2]);
        $session["client_token"] = $parts[3];
        // the last has not been included in efaCloud 2.3.3_07 and 2.4.0_01, March/April 2024
        // the last is the transcription for readability, never used for technical purposes.
        return $session;
    }

    /* -------------------------------------------------------- */
    /* --------------- SESSION HANDLING ----------------------- */
    /* -------------------------------------------------------- */
    
    /**
     * Cleanse the application sessions pool from expired sessions' files and count the remainder. Cleansing
     * uses $this->session_close() to also completely remove the associated PHP session. This is called before
     * every session start.
     */
    private function cleanse_and_count_sessions ()
    {
        $session_files = scandir("../log/sessions");
        $open_sessions_count = 0;
        foreach ($session_files as $session_file) {
            if (substr($session_file, 0, 1) != ".") {
                $session = $this->read_session($session_file);
                if ($session === false)
                    unlink($this->sessions_dir . $session_file);
                else {
                    $open_sessions_count ++;
                    $now = time();
                    $cause = ($session["alive_until"] < $now) ? i("K7p76O|Session inactivity timeo...") : (($session["ends_on"] <
                             $now) ? i("n6IV9Y|Session lifetime end") : false);
                    if ($cause !== false) {
                        $open_sessions_count --;
                        if (strpos($session_file, "~") === 0)
                            $this->api_session_close($cause, $session_file);
                        else
                            $this->web_session_close($cause, $session_file);
                    }
                }
            }
        }
        $this->log_event(i("p4z2js|Cleansed obsolete sessio...") . " " . $open_sessions_count, true);
        return $open_sessions_count;
    }

    /**
     * Get the longest living API session of the user with $user_id and the current client's token.
     * 
     * @param int $user_id
     *            the user id to get the session id for.
     * @return string the session ID matching, an empty String if there was none.
     */
    private function get_api_session_id (int $user_id)
    {
        // collect all API-sessions for this user and detect the maximum lifetime
        $session_files = scandir("../log/sessions");
        // default is ascending filename order, thus always the same sequence.
        $api_sessions_of_user = [];
        $max_lifetime = 0;
        $client_token = $this->client_token();
        // iterate over all session files
        foreach ($session_files as $session_file) {
            if (substr($session_file, 0, 1) == "~") {
                $session = $this->read_session($session_file);
                // filter on those with the same user id and client token
                if ((intval($session["user_id"]) == $user_id) &&
                         (strcmp($client_token, $session["client_token"]) == 0)) {
                    if ($session["ends_on"] > $max_lifetime)
                        $max_lifetime = $session["ends_on"];
                    $session["session_id"] = $session_file;
                    $api_sessions_of_user[] = $session;
                }
            }
        }
        // close all extra sessions and return the session id.
        $user_session_id = "";
        foreach ($api_sessions_of_user as $api_session_of_user)
            if ($api_session_of_user["ends_on"] == $max_lifetime)
                return $api_session_of_user["session_id"];
        return "";
    }

    /**
     * Create a not yet existing web- or api-session-file and write it into the application sessions pool.
     * 
     * @param int $user_id
     *            the ID of the user to create a session file for.
     * @param String $session_id
     *            the ID = file name of the session file
     * @param int $open_sessions_count
     *            the current count of open sessions = session files for overload check.
     * @return bool true, if a session file was created, false, if not. Errors are logged.
     */
    private function session_create (int $user_id, String $session_id, int $open_sessions_count)
    {
        $session_file = $this->sessions_dir . $session_id;
        if (file_exists($session_file))
            return $this->log_event(i("K6OO1w|Creating new session fil...") . " " . $session_file, false);
        if ($open_sessions_count >= $this->max_concurrent_sessions)
            return $this->log_event(i("Z9Ncn1|Starting new session. Cu...") . " " . $open_sessions_count, 
                    false);
        $now = Tfyh_toolbox::timef();
        return $this->write_session($now + $this->max_session_keepalive, $now + $this->max_session_duration, 
                $user_id, $session_id);
    }

    /**
     * Get the user id for an existing own session. This is used for the lowa-checks which come regular to
     * find other clients write activities and only use the session id as reference, not the user id.
     * 
     * @param String $session_id
     *            the session id for which the the user id is queried
     * @return String|bool the session id or false if either the session was not matched, or the client token
     *         which is built from the IP-address of the client, does not match.
     */
    public function get_user_id (String $session_id)
    {
        $session = $this->read_session($session_id);
        if ($session === false)
            return - 1;
        if (strcmp($this->client_token(), $session["client_token"]) != 0)
            return - 1;
        return $session["user_id"];
    }

    /**
     * Update an existing web- or api-session's keep-alive timestamp. Sessions are cleansed first, so if the
     * session was already outdated it will not be updated. If the $user_id is not consistent with the
     * sessions user id, the session is closed. If the session file with $session_id does not exist, only an
     * error is logged, but nothing changed in the session context.
     * 
     * @param int $user_id
     *            the user ID. -1 for anonymous users. If neither $user_id matches the user ID in the session
     *            file nor $user_id is -1 the session indicated by the $session_id is closed.
     * @param String $session_id.
     *            The session id to read the session file for.
     * @return bool false, if the user didn't match the session, true else - even if the update write fails,
     *         e. g. because of late end session freeze.
     */
    public function session_verify_and_update (int $user_id, String $session_id)
    {
        $open_sessions_count = $this->cleanse_and_count_sessions();
        // read the session, if after cleansing still existing
        $session_file = $this->sessions_dir . $session_id;
        $existing_session = $this->read_session($session_id);
        
        // error cases
        if ($existing_session == false)
            return $this->log_event(
                    i("AxGDip|User %1 tried to use inv...", $user_id, 
                            "*** (" . strlen($session_id) . " chars)"), false);
        $session_user = intval($existing_session["user_id"]);
        if (($session_user != $user_id) && ($session_user >= 0)) {
            $cause = i("t4W3bN|User %1 tried to use ses...", $user_id, $session_id, 
                    $existing_session["user_id"]);
            $this->web_session_close($cause);
            return false;
        }
        // update the keep-alive
        $ends_on = $existing_session["ends_on"];
        $alive_until = Tfyh_toolbox::timef() + $this->max_session_keepalive;
        $this->write_session($alive_until, $ends_on, $user_id, $session_id);
        return true; // even if the session write fails, this indicates that the session was valid.
    }

    /* -------------------------------------------------------- */
    /* ------------------ UTILITY FUNCTION -------------------- */
    /* -------------------------------------------------------- */
    
    /**
     * List all open sessions.
     * 
     * @return String with all currently available session files' contents. One line per session.
     */
    public function load_warning_info ()
    {
        $log_text .= "-------------------- Sessions ------------------\n";
        $session_files = scandir("../log/sessions");
        $session_list = "";
        foreach ($session_files as $session_file)
            if (strcmp(substr($session_file, 0, 1), ".") != 0)
                $session_list .= file_get_contents("../log/sessions/" . $session_file) . "\n";
        $log_text .= $session_list;
        
        // Log the session details, delete the session
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        $session_data = "";
        $log_text .= "-------------------- Server data ------------------\n";
        foreach ($_SERVER as $parm => $value)
            $log_text .= "$parm = '$value'\n";
        $log_text .= "-------------------- \$_SESSION ------------------\n";
        $log_text .= json_encode($_SESSION);
        $log_text .= "\n-------------------- End ------------------";
    }
}
    
