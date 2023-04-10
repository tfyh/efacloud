<?php

/**
 * Class to handle sessions from an application perspective, in particular managing concurrency and load
 * throttling from application perspective, i. e. for all of the current web- and api-sessions. The
 * app-session file starts with three integer numbers: started at (Unix timestamp, seconds); refreshed at
 * (Unix timestamp, seconds); user ID - all terminated by a ";". Its name is the Its file name is the session
 * ID which is either the PHP session id for web-access or the tfyh-session ID for api access.
 */
class Tfyh_app_sessions
{

    public $max_session_duration;

    public $max_session_keepalive;

    public $max_concurrent_sessions;

    private $toolbox;

    private $debug_on;

    private $debug_file = "../log/debug_sessions.log";

    private $sessions_dir = "../log/sessions/";

    /**
     * Caching parameters
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
        $this->debug_on = ($toolbox->config->debug_level > 0);
        $this->toolbox = $toolbox;
    }

    /**
     * Read a session file
     * 
     * @param String $session_id
     *            the session ID = filename
     * @return the session as associative array: started_at, refreshed_at, user_id or false on errors
     */
    private function read_session (String $session_id)
    {
        $session_file = $this->sessions_dir . $session_id;
        if (! file_exists($session_file))
            return false;
        $times_and_user = file_get_contents($session_file);
        if ($times_and_user === false) {
            if ($this->debug_on)
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . "\n " . i("3Srdaj|Failed to read existing ...") . " " .
                                 $session_file . "\n", FILE_APPEND);
            return false;
        }
        $parts = explode(";", $times_and_user);
        if (count($parts) < 3) {
            if ($this->debug_on)
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . "\n " . i("8I1Ul0|Malformatted session fil...") . " " .
                                 $session_file . "\n", FILE_APPEND);
            return false;
        } else {
            $session = array();
            $session["started_at"] = intval($parts[0]);
            $session["refreshed_at"] = intval($parts[1]);
            $session["user_id"] = intval($parts[2]);
            // part[3] is the transcription for readability, never used for technical purposes.
            return $session;
        }
    }

    /**
     * Cleanse the file system from expired sessions' files and count the remainder. If a session is cleansed,
     * this also closes the PHP session using $this->session_close().
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
                    $now = time();
                    if ($this->debug_on)
                        file_put_contents($this->debug_file, 
                                date("Y-m-d H:i:s") . ": " . i("26flZh|Session checked:") . " " . $session_file .
                                         ", " . i("W81e1T|started") . " " .
                                         date("Y-m-d H:i:s", $session["started_at"]) . ", " .
                                         i("RhKowz|refreshed") . " " .
                                         date("Y-m-d H:i:s", $session["refreshed_at"]) . "\n", FILE_APPEND);
                    if ($session["started_at"] < $now - $this->max_session_duration) {
                        $this->session_close(
                                i("1ZDOoa|Exceeded maximum session...", 
                                        strval($this->max_session_duration / 3600)), $session_file);
                    } elseif ($session["refreshed_at"] < $now - $this->max_session_keepalive) {
                        $this->session_close(
                                i("RwHQSN|Exceeded maximum inactiv...", ($this->max_session_keepalive / 60)), 
                                $session_file);
                    } else
                        $open_sessions_count ++;
                }
            }
        }
        return $open_sessions_count;
    }

    /**
     * Create a new app session Id.
     */
    public function create_app_session_id ()
    {
        $session_id = "tfyh" . $this->toolbox->generate_token(26, true);
        return $session_id;
    }

    /**
     * Get the user for a session.
     * 
     * @param String $session_id
     *            the session to look at
     * @return number the id of the user, if a session was found, else false.
     */
    public function session_user_id (String $session_id)
    {
        $session = $this->read_session($session_id);
        if ($session === false)
            return false;
        else
            return $session["user_id"];
    }

    /**
     * Start a session from application perspective. This will create or refresh the application session file.
     * The user may change, in essence for the login scenario to keep the session, for a user who was not
     * identified before the login. The PHP session context can be used: set the $session_id to "" or leave it
     * away to rely on it. The PHP session context stores the user record within the $_SESSION["User"]
     * including the user ID during the web login process.
     * 
     * @param int $user_id
     *            the user ID. Set to -1 for anonymous users.
     * @param bool $use_php
     *            the session ID = filename. Set to "" or omit to use the PHP session management. You may
     *            generate a new app session ID with the tfyh_app_sessions->create_app_session_id() function
     * @return true if opened, false else.
     */
    public function session_open (int $user_id, String $session_id = "")
    {
        
        // remove all obsolete sessions first. This ensures that an obsolete session can not be
        // reused.
        $open_sessions_count = $this->cleanse_and_count_sessions();
        if ($this->debug_on)
            file_put_contents($this->debug_file, 
                    date("Y-m-d H:i:s") . ": " . i("p4z2js|Cleansed obsolete sessio...") . " " .
                             $open_sessions_count . "\n", FILE_APPEND);
        
        // get the PHP context, if requested.
        if (strlen($session_id) == 0) {
            session_start();
            $session_id = session_id();
        }
        
        // read the session, if after cleansing still existing
        $session_file = $this->sessions_dir . $session_id;
        $existing_session = $this->read_session($session_id);
        
        // create or refresh
        if ($existing_session == false) {
            // the session may be still available within the PHP context, but not valid in the app
            // session context. Remove all information for this case.
            $_SESSION = array();
            if ($this->debug_on)
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . ": " . i("Ukfuhg|session_open - initializ...") . "\n", 
                        FILE_APPEND);
            // create new, if not existing
            if ($open_sessions_count <= $this->max_concurrent_sessions) {
                $now = time();
                $human_readable = $session_id . ", " . i("mGzOgz|started") . " " . date("Y-m-d H:i:s", $now) .
                         ", " . i("l3xH18|not yet refreshed, for u...") . " " . $user_id;
                $started_session = $now . ";" . $now . ";" . $user_id . ";" . $human_readable;
                // open the new session
                if (file_put_contents($session_file, $started_session) !== false) {
                    if ($this->debug_on)
                        file_put_contents($this->debug_file, 
                                date("Y-m-d H:i:s") . ": " . i("gS4qXx|Started new session:") . " " .
                                         $human_readable . "\n", FILE_APPEND);
                    return true;
                } else {
                    if ($this->debug_on)
                        file_put_contents($this->debug_file, 
                                date("Y-m-d H:i:s") . ": " . i("Mi1B56|Failed to write new sess...") . " " .
                                         $human_readable . "\n", FILE_APPEND);
                    return false;
                }
            } else {
                if ($this->debug_on)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": " . i("vOFqk1|Refused to start new ses...", $user_id, 
                                    $open_sessions_count) . "\n", FILE_APPEND);
                return false;
            }
        } else {
            // refresh, if existing. For app session w/o PHP context, $_SESSION may not be set.
            $app_session_user_id = intval($existing_session["user_id"]);
            $updated_session_user_id = $app_session_user_id;
            // an anonymous session may be taken by a real user, e.g. at login
            if ($app_session_user_id < 0) {
                // if a PHP session user exists, it takes precedence, else the $user_id will be taken
                $php_session_user_id = (isset($_SESSION["User"]) &&
                         isset($_SESSION["User"][$this->toolbox->users->user_id_field_name])) ? intval(
                                $_SESSION["User"][$this->toolbox->users->user_id_field_name]) : - 1;
                $updated_session_user_id = ($php_session_user_id > 0) ? $php_session_user_id : $user_id;
            }
            $update_session_user = ($updated_session_user_id != $app_session_user_id);
            $started = $existing_session["started_at"];
            $refreshed = time();
            $human_readable = $session_id . ", " . i("Q843R8|started") . " " . date("Y-m-d H:i:s", $started) .
                     ", " . i("Hq7qsl|refreshed") . " " . date("Y-m-d H:i:s", $refreshed) . ", " .
                     i("jY9jZE|for user") . " " . $updated_session_user_id .
                     (($update_session_user) ? " " . i("1t2W9I|from anonymous session.") : "");
            $refreshed_session = $started . ";" . $refreshed . ";" . $updated_session_user_id . ";" .
                     $human_readable;
            if (file_put_contents($session_file, $refreshed_session) !== false) {
                // log success
                if ($this->debug_on)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": " . i("twIq91|Refreshed session:") . " " . $human_readable .
                                     "\n", FILE_APPEND);
                return true;
            } else {
                // log failure
                $this->toolbox->logger->log(2, 0, 
                        i("kGf6KR|Failed to write refreshe...") . " " . $human_readable);
                if ($this->debug_on)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": " . i("nLVyxC|Failed to write refreshe...") . " " .
                                     $human_readable . "\n", FILE_APPEND);
                return false;
            }
        }
    }

    /**
     * Close a session. This does A) remove the app session file from the app session directory. The app
     * session file either is the provided $app_session_id or, if $app_session_id is empty, the current PHP
     * session ID. B) destroy the active PHP session, if $app_session_id is empty or $app_session_id matches
     * its session ID. This way it can be used for closing open sessions and cleansing overdue sessions.
     * 
     * @param String $cause
     *            the cause why the session was closed. Used for logging.
     * @param String $app_session_id
     *            the ID of the app session to close or cleanse. Use "" to close the current PHP session.
     */
    public function session_close (String $cause, String $app_session_id)
    {
        $because_of = (strlen($cause) > 0) ? ": " . $cause : "";
        $php_session_id = session_id();
        // unlink the app session file, if either an $app_session_id is provided or a PHP session
        // open.
        $file_to_unlink = (strlen($app_session_id) > 0) ? $app_session_id : $php_session_id;
        if (strlen($file_to_unlink) > 0) {
            $unlink_success = unlink($this->sessions_dir . $file_to_unlink);
            // monitor result
            if (! $unlink_success)
                $this->toolbox->logger->log(2, 0, 
                        i("YRkM5b|Unable to remove inactiv...", $file_to_unlink) . $because_of);
            if ($this->debug_on) {
                if ($unlink_success)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": " .
                                     i("UDQgXV|Removed app session file...", $file_to_unlink) . $because_of .
                                     " \n", FILE_APPEND);
                else
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": " .
                                     i("INS7kx|Failed to remove session...", $file_to_unlink) . $because_of .
                                     " \n", FILE_APPEND);
            }
        }
        
        // maybe we also have to close the existing PHP session.
        if (strlen($php_session_id) > 0) {
            // it exists, so check whether it matches the app session to close or shall be closed
            // anyway.
            if ((strcmp($php_session_id, $app_session_id) == 0) || (strlen($app_session_id) == 0)) {
                $_SESSION = array();
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . ": " . i("59YAGh|Closed PHP session °%1°", $php_session_id) .
                                 $because_of . " \n", FILE_APPEND);
            }
        }
    }

    /**
     * List all open sessions.
     * 
     * @return a String with all currently available session files' contents. One line per session.
     */
    public function list_sessions ()
    {
        $session_files = scandir("../log/sessions");
        $session_list = "";
        foreach ($session_files as $session_file)
            if (strcmp(substr($session_file, 0, 1), ".") != 0)
                $session_list .= file_get_contents("../log/sessions/" . $session_file) . "\n";
        return $session_list;
    }
}
    
