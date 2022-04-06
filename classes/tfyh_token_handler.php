<?php

/**
 * A utility class to create one-time tokens for user identification.
 */
class Tfyh_token_handler
{

    /**
     * file name to which tokens are written
     */
    private $tokenfile;

    /**
     * Validity period of a token in seconds
     */
    public $token_validity_period = 1200;

    /**
     * Monitoring period of all tokens used to check, whether a user has too many tokens created.
     */
    private $token_monitor_period = 86400;

    // tokens are monitored a full day
    /**
     * Maximum count of tokens a user can get per monitoring period.
     */
    private $tokens_allowed_in_monitor_period = 10;

    /**
     * public Constructor.
     * 
     * @param array $token_file
     *            the token file, e. g. "../log/tokens.txt"
     */
    public function __construct ($token_file)
    {
        $this->tokenfile = $token_file;
    }

    /**
     * Get a new token for the user.
     * 
     * @param int $user_id
     *            provides the userID for which a token shall be generated.
     * @return String token as generated.
     */
    public function get_new_token (int $user_id, Tfyh_toolbox $toolbox)
    {
        $this->cleanse_tokenfile();
        $token = $toolbox->generate_token(6, false);
        if ($user_id >= 0) {
            $nowSeconds = time();
            $contents = file_get_contents($this->tokenfile);
            $contents .= $token . ";" . $nowSeconds . ";" . $user_id;
            file_put_contents($this->tokenfile, $contents);
        }
        return $token;
    }

    /**
     * Remove a session from the set to ensure it is not used for a second transaction.
     * 
     * @param string $token_to_remove
     *            Token to be removed.
     */
    public function remove_token ($token_to_remove)
    {
        // read session file, split lines and check one by one
        $tokenfile_in = file_get_contents($this->tokenfile);
        $tokenile_lines = explode("\n", $tokenfile_in);
        $tokenfile_out = "";
        $nowSeconds = time();
        foreach ($tokenile_lines as $line) {
            if (strlen($line) > 4) {
                $tokenparts = explode(";", $line);
                $period = $nowSeconds - intval($tokenparts[1]);
                if (strcasecmp($token_to_remove, $tokenparts[0]) !== 0) {
                    // keep token, if it is not the one to be removed.
                    $tokenfile_out .= $line . "\n";
                }
            }
        }
        // write cleansed file.
        file_put_contents($this->tokenfile, $tokenfile_out);
    }

    /**
     * Removes all tokens from the token file which are no longer within the monitoring period.
     */
    private function cleanse_tokenfile ()
    {
        // read session file, split lines and check one by one
        $tokenfile_in = file_get_contents($this->tokenfile);
        $tokenfile_lines = explode("\n", $tokenfile_in);
        $tokenfile_out = "";
        $nowSeconds = time();
        foreach ($tokenfile_lines as $line) {
            if (strlen($line) > 4) {
                $tokenparts = explode(";", $line);
                $period = $nowSeconds - intval($tokenparts[1]);
                if ($period < $this->token_monitor_period) {
                    // keep token. Tokens not kept are effectively deleted.
                    $tokenfile_out .= $line . "\n";
                }
            }
        }
        // write cleansed file.
        file_put_contents($this->tokenfile, $tokenfile_out);
    }

    /**
     * Refreshes the lastModified for this token and returns the user ID. Checks the count of remaining
     * session of the user to be less than the allowed. If the allowance was exceeded, the user id is returned
     * as a negative number rather than positive (userID 0 being not allowed).
     * 
     * @param string $token
     *            token of session to be refreshed.
     * @return integer $user_id of token; -1 = user not found, -2 = if max count of sessions for this user is
     *         exceeded)
     */
    public function get_user_and_update ($token)
    {
        
        // read session file, split lines and check one by one
        $tokenfile_in = file_get_contents($this->tokenfile);
        $tokenfile_lines = explode("\n", $tokenfile_in);
        
        // Identify user for this session first.
        $user_id = - 1;
        foreach ($tokenfile_lines as $line) {
            if (strlen($line) > 0) {
                $tokenparts = explode(";", $line);
                if (strcasecmp($token, $tokenparts[0]) == 0) {
                    $user_id = intval($tokenparts[2]);
                }
            }
        }
        if ($user_id == - 1) {
            return - 1;
        }
        
        // Refresh this session and check count of sessions for user.
        $tokenfile_out = "";
        $nowSeconds = time();
        $issued_tokens_for_user = 0;
        foreach ($tokenfile_lines as $line) {
            if (strlen($line) > 0) {
                $tokenparts = explode(";", $line);
                $period = $nowSeconds - intval($tokenparts[1]);
                // update current session and count sessions of this user
                if (intval($tokenparts[2]) == $user_id) {
                    // refresh time stamp
                    if (($period < $this->token_validity_period) && (strcasecmp($token, $tokenparts[0]) == 0)) {
                        $tokenfile_out .= $tokenparts[0] . ";" . $nowSeconds . ";" . $user_id;
                        $tokenfile_out .= "\n";
                        $issued_tokens_for_user ++;
                    } elseif (($period < $this->token_monitor_period) && ($user_id !== intval($tokenparts[2]))) {
                        // count all tokens issued for this user in the monitor period
                        $issued_tokens_for_user ++;
                        $tokenfile_out .= $line . "\n";
                    } else {
                        // if it is a token for this user, but the token not relevant, do nothing.
                        // This will effectively remove the token from the list{
                    }
                } else {
                    // not this user, just keep the line.
                    $tokenfile_out .= $line . "\n";
                }
            } else {
                // do nothing. This will effectively remove the empty lines from the list.
            }
        }
        
        // write file.
        if (strlen($tokenfile_out) > 0) {
            $tokenfile_out = substr($tokenfile_out, 0, strlen($tokenfile_out) - 1);
        }
        file_put_contents($this->tokenfile, $tokenfile_out);
        
        // return a negative user id, if the count of tokens per monitor preiod was exceeded.
        if ($issued_tokens_for_user > $this->tokens_allowed_in_monitor_period)
            return - 2;
        else
            return $user_id;
    }
}

?>
