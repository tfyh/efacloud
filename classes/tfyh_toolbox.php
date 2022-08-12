<?php

/**
 * A utility class to read and provide the application settings, start a session, log or display an error,
 * read csv-configurations asf.
 */
class Tfyh_toolbox
{

    /**
     * period for overload detection by too many events in seconds
     */
    private $event_monitor_period = 3600;

    /**
     * path for logging and monitoring
     */
    private $log_dir = "../log/";

    /**
     * The set of characters to xor base64 strings = $base64chars plus the padding character '='. Used for
     * "encryption". The padding character will not be xored.
     */
    private $base64charsPlus = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

    /**
     * A random generated String obfuscating the user login token
     */
    private $obfuscator = "jtzOjk6IjEyNy4wLjAuMSI7czoxMToiZGJfYWNjb3VudHMiO2E6MTp7czo0OiJyb290IjtzOjg6IlNmeDFubHAuIjt9czo3OiJkYl9uYW1lIjtzOjU6ImZ2c3NiIjtzO";

    /**
     * Associative array providing the bit value associated to a base64 character. Used for "encryption".
     */
    private $bitsForChar64 = [];

    /**
     * indexed array providng the characters representing a bit value. Used for "encryption".
     */
    private $charsForBits64 = [];

    /**
     * types of usage statistics gathered.
     */
    private $s_types = ["logins","inits","errors"
    ];

    /**
     * headline of error indicating an overload. Will trigger specific actions. Must start with the no
     * counting tag "!#".
     */
    public $overload_error_headline = "!#Zu viele parallele Zugriffe.";

    /**
     * the app configuration
     */
    public $config;

    /**
     * application logger
     */
    public $logger;

    /**
     * the users class
     */
    public $users;

    /**
     * the users class
     */
    public $app_sessions;

    /**
     * Construct the Util class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct ()
    {
        // config must be first in this sequence
        include_once '../classes/tfyh_config.php';
        $this->config = new Tfyh_config($this);
        $init_settings = (isset($this->config->settings_tfyh["init"])) ? $this->config->settings_tfyh["init"] : array();
        
        include_once '../classes/users.php';
        $this->users = new Users($this);
        include_once '../classes/tfyh_logger.php';
        $this->logger = new Tfyh_logger($this);
        include_once '../classes/tfyh_app_sessions.php';#
        $this->app_sessions = new Tfyh_app_sessions($this);
    }

    /*
     * ======================== Session support ==============================
     */
    /**
     * A token is a sequence of random characters for different purposes. It always starts with a letter,
     * followed by characters including numeric digits. with the information on the session owner in a token
     * list.
     * 
     * @param int $token_length
     *            lengh of the token in characters.
     * @param bool $case_sensitive
     *            Set true to generate case sensitive tokens with a selection of 62 characters. Else a 32
     *            characters sequence is used without "B", "I", "O", "S" to not confound them with "8", "1",
     *            "0", "5".
     * @return String token as generated.
     */
    public function generate_token (int $token_length, bool $case_sensitive)
    {
        $short_set = ($case_sensitive) ? "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz" : "ACDEFGHJKLMNPQRTUVWXYZ";
        $full_set = $short_set . "0123456789";
        $full_set_end = strlen($full_set) - 1;
        $token = substr(str_shuffle($short_set), 0, 1);
        for ($i = 1; $i < $token_length; $i ++)
            $token .= substr($full_set, random_int(0, $full_set_end), 1);
        return $token;
    }

    /**
     * create GUIDv4, see https://www.php.net/manual/de/function.com-create-guid.php
     * 
     * @return string Unique identifier
     */
    public function create_GUIDv4 ()
    {
        // OSX/Linux. Windows environments, see link above
        if (function_exists('openssl_random_pseudo_bytes') === true) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
        
        // Fallback (PHP 4.2+)
        mt_srand((double) microtime() * 10000);
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45); // "-"
        $guidv4 = substr($charid, 0, 8) . $hyphen . substr($charid, 8, 4) . $hyphen . substr($charid, 12, 4) .
                 $hyphen . substr($charid, 16, 4) . $hyphen . substr($charid, 20, 12);
        return $guidv4;
    }

    /**
     * redirect to an error page
     * 
     * @param String $error_headline
     *            Headline which will be displayed on the error page. Preceed by !# to suppress error counting
     * @param String $error_text
     *            Error explanation which will be displayed on the error page
     * @param String $calling_page
     *            The page calling this error to be displayed
     */
    public function display_error (String $error_headline, String $error_text, String $calling_page)
    {
        // no endless error loop.
        if (strrpos($calling_page, "error.php") !== false)
            return;
        file_put_contents("../log/lasterror.txt", $calling_page . ";" . $error_headline . ";" . $error_text);
        header("Location: ../pages/error.php");
        exit();
    }

    /**
     * Encode the timestamp + validity and the user Mail to create a user login token. It will have the user
     * mail in the middle, braced by two changing parts, the timestamp and a padding. The result will be a
     * base64 encoded String in which three characters are replace in order to be URL-compatible: "=" by "_",
     * "/" by "-", "+" by "*".
     * 
     * @param String $user_mail
     *            the users mail address, i.e. his account mail
     * @param String $deep_link
     *            the page to be opened after login, if not the home page
     * @param int $validity
     *            the validity of the links in days from now.
     * @return String token created
     */
    public function create_login_token (String $user_mail, int $validity, String $deep_link)
    {
        $message = strval(time() + $validity * 24 * 3600) . ":" . $user_mail . ":" . $deep_link . ":" .
                 substr(str_shuffle($this->base64charsPlus), 0, 16);
        return str_replace("=", "_", 
                str_replace("/", "-", 
                        str_replace("+", "*", $this->xor64(base64_encode($message), $this->obfuscator))));
    }

    /**
     * Decode the user login token and validate it. See create_login_token() for token format.
     * 
     * @param String $token
     *            Token to be validated
     * @return mixed|boolean the plain text array, being the valitdity, the user mail and the deep link if
     *         valid, false, if not
     */
    public function decode_login_token (String $token)
    {
        $plain_text = explode(":", 
                base64_decode(
                        $this->xor64(
                                str_replace("_", "=", str_replace("-", "/", str_replace("*", "+", $token))), 
                                $this->obfuscator)));
        if (intval($plain_text[0]) >= time())
            return $plain_text;
        else
            return false;
    }

    /*
     * ================ Data validity checks and formatting ============================
     */
    /**
     * Returns the date in "Y-m-d" format (e.g. 2018-04-20), if the array contains a valid date. Returns
     * false, if not. If the year value is < 100 it will be adjusted to a four digit year using this year and
     * the 99 preceding to complete. Returns false, if the date is not valid.
     * 
     * @param array $to_parse
     *            has at least a field year, month and day with integer values. If the year value is < 100 it
     *            will be adjusted to a four digit year using this year and the 99 preceding to complete.
     *            Maximum is year 2999. Returns false, if the date is not valid.
     */
    private function valid_date (array $to_parse)
    {
        if ($to_parse["year"] < 100) {
            $thisyear = intval(date("Y", time()));
            if ($to_parse["year"] <= ($thisyear - 2000))
                $to_parse["year"] = $to_parse["year"] + 2000;
            else
                $to_parse["year"] = $to_parse["year"] + 1900;
        }
        $is_valid = ($to_parse["year"] < 3000);
        $date_uk = sprintf('%04d', $to_parse["year"]) . "-" . sprintf('%02d', $to_parse["month"]) . "-" .
                 sprintf('%02d', $to_parse["day"]);
        $date_uk_int = strTotime($date_uk);
        if ($date_uk_int === false)
            return false;
        elseif ($to_parse["day"] == 0)
            return false;
        elseif ($to_parse["month"] == 0)
            return false;
        return $date_uk;
    }

    /**
     * Check, whether the date_string represents a valid date.
     * 
     * @param String $date_string
     *            string to be checked
     * @return a formatted date string "Y-m-d" (e.g. 2018-04-20), if $date_string is a date between 01.01.0100
     *         and 31.12.2999, else false. Note: If the year value is < 100 it will be adjusted to a four
     *         digit year using this year and the 99 preceding to complete.
     */
    public function check_and_format_date (String $date_string)
    {
        // return empty String for a null date.
        if (strlen($date_string) < 3)
            return "";
        
        // now try to parse, assuming German formatting (DD.MM.YYYY)
        $parsed_date = explode(".", $date_string);
        $parsed_date["day"] = intval($parsed_date[0]);
        $parsed_date["month"] = (isset($parsed_date[1])) ? intval($parsed_date[1]) : 0;
        $parsed_date["year"] = (isset($parsed_date[2])) ? intval($parsed_date[2]) : 0;
        $date_uk = $this->valid_date($parsed_date);
        
        // if failed, try to parse using UK format (YYYY-MM-DD)
        if ($date_uk === false) {
            $parsed_date = explode("-", $date_string);
            $parsed_date["day"] = intval($parsed_date[2]);
            $parsed_date["month"] = (isset($parsed_date[1])) ? intval($parsed_date[1]) : 0;
            $parsed_date["year"] = (isset($parsed_date[0])) ? intval($parsed_date[0]) : 0;
            $date_uk = $this->valid_date($parsed_date);
        }
        
        // return parsing result
        return $date_uk;
    }

    /**
     * Html wrap for form errors String (Add "Fehler:" and change color, if message is not empty.
     * 
     * @param String $form_errors
     *            form errors String which shall be wrapped
     * @return String wrapped form errors String
     */
    public function form_errors_to_html (String $form_errors)
    {
        if (strlen($form_errors) > 0) {
            return '<p><span style="color:#A22;"><b>Fehler: </b> ' . $form_errors . '</span></p>';
        }
        return "";
    }

    /**
     * To enable multiple use of a mail address for more than one Mitglied, mail addresses may be prefixed by
     * an integer plus '.', e.g. 2.john.doe@nowhere.com for the son of John Doe. This here strips the prefix,
     * if existing.
     * 
     * @param String $mail_address
     *            mail address to be checked and stripped, if necessary
     * @return String mail address without prefix
     */
    public function strip_mail_prefix (String $mail_address)
    {
        $mail_parts = explode(".", $mail_address, 2);
        if (strlen($mail_parts[0]) > 1)
            return $mail_address;
        if (is_numeric($mail_parts[0]))
            return $mail_parts[1];
        return $mail_address;
    }

    /**
     * Return the age of a person in years (floating point value)
     * 
     * @param String $birthday
     *            birthday of person
     * @return age in years, false, if $birthday is not a valid date
     */
    public function age_in_years (String $birthday)
    {
        $bd = strtotime($birthday);
        if ($bd === false)
            return false;
        // 31557600 = seconds per year of 365.25 days.
        return (time() - $bd) / 31557600;
    }

    /**
     * my_bcmod - get modulus (substitute for bcmod) string my_bcmod ( string left_operand, int modulus )
     * left_operand can be really big, but be carefull with modulus :( by Andrius Baranauskas and Laurynas
     * Butkus :) Vilnius, Lithuania
     * https://stackoverflow.com/questions/10626277/function-bcmod-is-not-available
     * 
     * @param String $x
     *            first value (x % y)
     * @param String $y
     *            second value (x % y)
     * @return number
     */
    private function my_bcmod (String $x, String $y)
    {
        // how many numbers to take at once? carefull not to exceed (int)
        $take = 5;
        $mod = '';
        
        do {
            $a = (int) $mod . substr($x, 0, $take);
            $x = substr($x, $take);
            $mod = $a % $y;
        } while (strlen($x));
        
        return (int) $mod;
    }

    /**
     * Check, whether the IBAN complies to IBAN rules. removes spaces from IBAN prior to check and ignores
     * letter case. Make sure the IBAN has the apprpriate letter case when being entered in the form. Snippet
     * copied from https://stackoverflow.com/questions/20983339/validate-iban-php
     * 
     * @param String $iban
     *            IBAN to be checked
     * @return true, if IBAN is valid. False, if not
     */
    public function checkIBAN ($iban)
    {
        $iban = strtolower(str_replace(' ', '', $iban));
        $Countries = array('al' => 28,'ad' => 24,'at' => 20,'az' => 28,'bh' => 22,'be' => 16,'ba' => 20,
                'br' => 29,'bg' => 22,'cr' => 21,'hr' => 21,'cy' => 28,'cz' => 24,'dk' => 18,'do' => 28,
                'ee' => 20,'fo' => 18,'fi' => 18,'fr' => 27,'ge' => 22,'de' => 22,'gi' => 23,'gr' => 27,
                'gl' => 18,'gt' => 28,'hu' => 28,'is' => 26,'ie' => 22,'il' => 23,'it' => 27,'jo' => 30,
                'kz' => 20,'kw' => 30,'lv' => 21,'lb' => 28,'li' => 21,'lt' => 20,'lu' => 20,'mk' => 19,
                'mt' => 31,'mr' => 27,'mu' => 30,'mc' => 27,'md' => 24,'me' => 22,'nl' => 18,'no' => 15,
                'pk' => 24,'ps' => 29,'pl' => 28,'pt' => 25,'qa' => 29,'ro' => 24,'sm' => 27,'sa' => 24,
                'rs' => 22,'sk' => 24,'si' => 19,'es' => 24,'se' => 24,'ch' => 21,'tn' => 24,'tr' => 26,
                'ae' => 23,'gb' => 22,'vg' => 24
        );
        $Chars = array('a' => 10,'b' => 11,'c' => 12,'d' => 13,'e' => 14,'f' => 15,'g' => 16,'h' => 17,
                'i' => 18,'j' => 19,'k' => 20,'l' => 21,'m' => 22,'n' => 23,'o' => 24,'p' => 25,'q' => 26,
                'r' => 27,'s' => 28,'t' => 29,'u' => 30,'v' => 31,'w' => 32,'x' => 33,'y' => 34,'z' => 35
        );
        
        if (strlen($iban) == $Countries[substr($iban, 0, 2)]) {
            
            $MovedChar = substr($iban, 4) . substr($iban, 0, 4);
            $MovedCharArray = str_split($MovedChar);
            $NewString = "";
            foreach ($MovedCharArray as $key => $value) {
                if (! is_numeric($MovedCharArray[$key])) {
                    $MovedCharArray[$key] = $Chars[$MovedCharArray[$key]];
                }
                $NewString .= $MovedCharArray[$key];
            }
            if ($this->my_bcmod($NewString, '97') == 1) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Check, whether the pwd complies to password rules.
     * 
     * @param String $pwd
     *            password to be checked
     * @return String list of errors found. Returns empty String, if no errors were found.
     */
    public function check_password (String $pwd)
    {
        $errors = "";
        if ((strlen($pwd) < 8) || (strlen($pwd) > 32)) {
            $errors .= "Das Kennwort muss zwischen 8 Zeichen und 32 Zeichen lang sein. ";
        }
        $numbers = (preg_match("#[0-9]+#", $pwd)) ? 1 : 0;
        $lowercase = (preg_match("#[a-z]+#", $pwd)) ? 1 : 0;
        $uppercase = (preg_match("#[A-Z]+#", $pwd)) ? 1 : 0;
        // Four ASCII blocks: !"#$%&'*+,-./ ___ :;<=>?@ ___ [\]^_` ___ {|}~
        $specialchars = (preg_match("#[!-/]+#", $pwd) || preg_match("#[:-@]+#", $pwd) ||
                 preg_match("#[\[-`]+#", $pwd) || preg_match("#[{-~]+#", $pwd)) ? 1 : 0;
        if (($numbers + $lowercase + $uppercase + $specialchars) < 3)
            $errors .= "Im Kennwort müssen Zeichen aus drei Gruppen der folgenden vier Gruppen " .
                     "enthalten sein: Ziffern, Kleinbuchstaben, Großbuchstaben, Sonderzeichen. " .
                     "Zulässige Sonderzeichen sind !\"#$%&'*+,-./:;<=>?@[\]^_`{|}~";
        return $errors;
    }

    /**
     * Little String mix helper
     * 
     * @param String $p            
     * @return string
     */
    public static function swap_lchars (String $p)
    {
        $P = "";
        for ($i = 0; $i < strlen($p); $i ++)
            if ((ord($p[$i]) >= 97) && (ord($p[$i]) <= 122)) // a (97) ..z (122)
                $P .= chr(219 - ord($p[$i]));
            else
                $P .= $p[$i];
        return $P;
    }

    /*
     * ================= file handling and zipping ===========================
     */
    
    /**
     * Parse a file system tree and return all relative path names of files. Runs recursively.
     * 
     * @param array $file_paths
     *            an array with file path, to which the subsequents paths shall be added.
     * @param String $branch_root_dir
     *            the root of the recursive tree drill down
     * @param String $parent_dir
     *            the relative path within $branch_root_dir of the directory which shall be parsed
     * @return array the $file_paths with all files of this branch added.
     */
    private function list_files_of_tree (array $file_paths, String $branch_root_dir, String $parent_dir)
    {
        $handle = opendir($branch_root_dir . $parent_dir);
        if ($handle !== false) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    // add relative path
                    $file_paths[] = $parent_dir . $entry;
                    // drill down, if this is a directory
                    if (is_dir($branch_root_dir . $parent_dir . $entry)) {
                        $file_paths = $this->list_files_of_tree($file_paths, $branch_root_dir, 
                                $parent_dir . $entry . "/");
                    }
                }
            }
            closedir($handle);
            return $file_paths;
        }
    }

    /**
     * Parse a file system branch and return all relative path names of files
     * 
     * @param String $branch_root_dir
     *            the directory which shall be parsed. Must end with "/".
     * @return array the $file_paths with all files of this branch added.
     */
    public function list_files_of_branch (String $branch_root_dir)
    {
        $file_paths = [];
        return $this->list_files_of_tree($file_paths, $branch_root_dir, "");
    }

    /**
     * Unzipper for backup import. See https://www.php.net/manual/de/ref.zip.php
     * 
     * @param String $zip_path
     *            the path to the zip archive without ".zip" extension. The extension will be stripped off to
     *            create the unzip-directory location for unzipping the archive. This directory must not
     *            exist.
     * @return string|array array of filepaths (Strings, including the unzip-directory) of extracted files on
     *         success, else an error message String
     */
    public function unzip (String $zip_path)
    {
        $dir_path = substr($zip_path, 0, strrpos($zip_path, "."));
        if (! file_exists($zip_path))
            return "#Error: Zip path $zip_path doesn't exist.";
        if (file_exists($dir_path))
            return "#Error: Target directory $dir_path for unzipping already exists, aborted.";
        
        mkdir($dir_path);
        $resource = zip_open($zip_path);
        if (! $resource)
            return "#Error while opening the $zip_path file.";
        $file_list = [];
        $zip_entry = zip_read($resource);
        while ($zip_entry) {
            if (zip_entry_open($resource, $zip_entry, "r")) {
                // get the file descriptor
                $data = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                $dir_name = dirname(zip_entry_name($zip_entry));
                $name = zip_entry_name($zip_entry);
                if (strlen($dir_name) > 0) {
                    // make the path, if file is a directory
                    $base = "$dir_path/";
                    foreach (explode("/", $dir_name) as $k) {
                        $base .= "$k/";
                        if (! file_exists($base)) {
                            if (mkdir($base) === false)
                                $file_list[] = "#Error mkdir failed on directory " . $base . "<br>";
                        }
                    }
                }
                // write file to file system
                $name = "$dir_path/$name";
                $file_list[] = $name;
                $w_bytes = file_put_contents($name, $data);
                zip_entry_close($zip_entry);
            }
            // no errors are issued, if a resource can not be opened within the archive.
            $zip_entry = zip_read($resource);
        }
        zip_close($resource);
        return $file_list;
    }

    /**
     * Store a set of files into a given archive.
     * 
     * @param array $src_filepaths
     *            array if String holding a list of file paths which will be zipped.
     * @param String $zip_filepath
     *            path to archive
     */
    public function zip_files (array $src_filepaths, String $zip_filepath)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($zip_filepath, ZipArchive::CREATE) !== true) {
            file_put_contents($zip_filepath, "");
        }
        foreach ($src_filepaths as $src_filepath)
            if (! is_dir($src_filepath))
                if (file_exists($src_filepath))
                    $zip->addFile($src_filepath);
        $zip->close();
    }

    /**
     * Store a String into the given filepath and create a zip archive at the $filepath . ".zip".
     * 
     * @param String $filename
     *            name file within archive returned. The archive will be named "$filename.zip". MUST NOT BE
     *            SET BY THE USER. This must not include a path to the file but uses the current path to
     *            avoid, that the zip archive itself shows a path. It is therefore NECESSARY that the
     *            "$filename.zip" is chosen in a way not to exist in whatever path of the site in order to
     *            ensure that nothing is overwritten.
     * @return the name of the zip archive created.
     */
    public function zip ($string_to_zip, $filename)
    {
        $zip = new ZipArchive();
        $zip_filename = $filename . ".zip";
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
            exit("cannot open <$zip_filename>\n");
        }
        if ($zip->addFromString($filename, $string_to_zip) !== true)
            exit("cannot write zip <$zip_filename>\n");
        $zip->close();
        return $zip_filename;
    }

    /**
     * Return a file to the user. The file will afterwards be deleted (unlinked). Uses the "header" function,
     * i. e. must be called before any other output is generated by the calling page.
     * 
     * @param String $filepath
     *            path to file which shall be returned.
     * @param String $contenttype
     *            content type which shall be declared, e.g. application/zip.
     */
    public function return_file_to_user (String $filepath, String $contenttype)
    {
        // return file.
        $filename = (strpos($filepath, "/") !== false) ? substr($filepath, strrpos($filepath, "/") + 1) : $filepath;
        if (file_exists($filepath)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public"); // needed for internet explorer
            header("Content-Type: " . $contenttype);
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:" . filesize($filepath));
            header("Content-Disposition: attachment; filename=" . $filename);
            readfile($filepath);
            // unlink($filepath); That results in an execution error. Clean up later.
            exit();
        } else {
            die("Error: File @ " . $filepath . " not found.");
        }
    }

    /**
     * Return a file to the user. The file will afterwards not be deleted (unlinked). Uses the "header"
     * function, i. e. must be called before any other output is generated by the calling page.
     * 
     * @param String $filepath
     *            path to file which shall be returned.
     */
    private function return_zip_file (String $filepath)
    {
        // return zip.
        if (file_exists($filepath)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public"); // needed for internet explorer
            header("Content-Type: application/zip");
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:" . filesize($filepath));
            header("Content-Disposition: attachment; filename=" . $filepath);
            readfile($filepath);
            // unlink($filepath); That results in an execution error. Clean up later.
            exit();
        } else {
            die("Error: File @ " . $filepath . " not found.");
        }
    }

    /**
     * Return it files in a compressed archive to the user. Uses the "header" function, i. e. must be called
     * before any other output is generated by the calling page.
     * 
     * @param array $src_filepaths
     *            array if String holding a list of file paths which will be zipped.
     * @param String $zip_filename
     *            name of archive returned. MUST NOT BE SET BY THE USER. This does not include the path to the
     *            file but uses the current path to avoid, that the zip archive itself shows a path. It is
     *            therefore NECESSARY that the $zip_filename is chosen in a way not to exist in whatever path
     *            of the site in order to ensure that nothing is overwritten.
     * @param bool $remove_files
     *            set true to remove files after delivery to user.
     */
    public function return_files_as_zip (array $src_filepaths, String $zip_filename, bool $remove_files)
    {
        $this->zip_files($src_filepaths, $zip_filename);
        if ($remove_files) {
            foreach ($src_filepaths as $src_filepath) {
                unlink($src_filepath);
            }
        }
        $this->return_zip_file($zip_filename);
    }

    /**
     * Zip a csv-String and return it as file to the user. Uses the "header" function, i. e. must be called
     * before any other output is generated by the calling page.
     * 
     * @param String $csv
     *            String which shall be zipped and forwarded.
     * @param String $fname
     *            name file within archive returned. The archive will be named "$fname.zip". MUST NOT BE SET
     *            BY THE USER. This must not include a path to the file but uses the current path to avoid,
     *            that the zip archive itself shows a path. It is therefore NECESSARY that the "$fname.zip" is
     *            chosen in a way not to exist in whatever path of the site in order to ensure that nothing is
     *            overwritten.
     */
    public function return_string_as_zip (String $string, String $fname)
    {
        $zip_filename = $this->zip($string, $fname);
        $this->return_zip_file($zip_filename);
    }

    /**
     * Scan a directory and return the contents as HTML table
     * 
     * @param String $dir
     *            directory which shall be scanned. Typically "../uploads". This directory must not be the
     *            root of the web-site. Because the directory browser provides access to it for every user.
     * @param int $level_of_top
     *            level of top of the branch for the upload, default is 1, corresponds to ../uploads. Must not
     *            be < 1. Exaple: if there is a root dir ../uploads/all_users_branch the $level_of_top = 2.
     * @return HTML table
     */
    public function get_dir_contents (String $dir, int $level_of_top = 1)
    {
        $result = "<table>";
        $result .= "<tr class=flist><td>&nbsp;</td><td>" . $dir . "</td><td>Aktion</td></tr>";
        $items = 0;
        $cdir = scandir($dir);
        if ($cdir)
            foreach ($cdir as $key => $value) {
                if (! in_array($value, array(".",".."
                ))) {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                        $result .= "<tr class=flist><td><img src='../resources/drive_folder-20px.png' title='Verzeichnis' /></td>" .
                                 "<td><a href='?cdir=" . $dir . "/" . $value . "'>" . $value .
                                 "</a>&nbsp;&nbsp;&nbsp;&nbsp;</td>" . "<td><a href='?xdir=" . $dir . "/" .
                                 $value .
                                 "'><img src='../resources/delete_file-20px.png' title='Verzeichnis löschen, wenn leer' /></a>" .
                                 "</td></tr>\n";
                        $items ++;
                    } else {
                        $result .= "<tr class=flist><td><img src='../resources/drive_file-20px.png' title='Datei' /></td>" .
                                 "<td>" . $value . "&nbsp;&nbsp;&nbsp;&nbsp;</td><td><a href='?dfile=" . $dir .
                                 "/" . $value .
                                 "'><img src='../resources/download_file-20px.png' title='Datei herunterladen' /></a>" .
                                 "<a href='?xfile=" . $dir . "/" . $value .
                                 "'><img src='../resources/delete_file-20px.png' title='Datei löschen' /></a>" .
                                 "</td></tr>\n";
                        $items ++;
                    }
                }
            }
        if ($items == 0)
            $result .= "<tr class=flist><td>(leer)</td><td>(kein Inhalt gefunden.)</td></tr>";
        $parentdir = (strrpos($dir, "/") > 0) ? substr($dir, 0, strrpos($dir, "/")) : $dir;
        // the topmost offered parent directory is the "uploads" folder to ensure
        // entry into the application files hierarchy is not possible.
        if (count(explode("/", $parentdir)) > $level_of_top)
            $result .= "<tr class=flist><td><img src='../resources/drive_file-20px.png' title='eine Ebene höher' /></td><td><a href='?cdir=" .
                     $parentdir . "'>" . $parentdir . "</a></td></tr>";
        $result .= "</table>";
        
        return $result;
    }

    /*
     * ======================= csv read support ==============================
     */
    /**
     * Read the first csv-line into an array of entries. CSV-format must be with text delimiter = " and
     * separator = ;. The line must be encoded in UTF-8.
     * 
     * @param String $csv
     *            A String with a csv table
     * @return array of "row" => the line which was read, plus a "remainder"s => remainder of the $csv String.
     */
    public function read_csv_line (String $csv)
    {
        $lines = Explode("\n", $csv);
        
        // convert lines
        $completed_line = ""; // lines will not be complete, if an entry
                              // contains a line break
        $raw_row = null;
        $remainder = "";
        foreach ($lines as $line) {
            if (! $raw_row)
                $completed_line .= $line . "\n";
            else
                $remainder .= $line . "\n";
            /*
             * a line is complete, if the count of quotes is even. Because a quote itself is replace by two
             * quotes, and a quoted entry always has a quote on both ends.
             */
            $cnt_quotes = substr_count($completed_line, "\"");
            if (! $raw_row && ($cnt_quotes % 2 == 0)) {
                // line is complete. Read it into indexed array
                $raw_row = str_getcsv($completed_line, ";", "\"");
            }
        }
        if (strlen($remainder) > 0)
            $remainder = substr($remainder, 0, strlen($remainder) - 1);
        return array("row" => $raw_row,"remainder" => $remainder
        );
    }

    /**
     * Simple csv entry encoder. If the $entry contains one of ' \n', ';' '"' all "-quotes are duplicated and
     * one '"' added at front and end.
     * 
     * @param String $entry
     *            entry which shall be encoded
     * @return String the encoded entry.
     */
    public function encode_entry_csv (String $entry = null)
    {
        if (is_null($entry))
            return "";
        if ((strpos($entry, "\n") !== false) || (strpos($entry, ";") !== false) ||
                 (strpos($entry, "\"") !== false))
            return "\"" . str_replace("\"", "\"\"", $entry) . "\"";
        return $entry;
    }

    /**
     * Read a simplified csv-file into an array of rows, each row becoming a named array with the names being
     * the first line entries. CSV-format must be with text delimiter = " and separator = ;. There must not be
     * any space character left or right of the delimiter. First line entries must not contain line breaks.
     * Lines ending with unquoted " \" will be joined with the following line. The file is preferrably encoded
     * in UTF-8, but ISO-8859-1 should also work due to automatic encoding detection.
     * 
     * @param String $file_path
     *            path to file with csv table
     * @return array the table which was read. In case of errors, it will be an empty array [].
     */
    public function read_csv_array (String $file_path)
    {
        // read csv file.
        if (file_exists($file_path))
            $content = file_get_contents($file_path);
        else
            return [];
        
        $text = mb_convert_encoding($content, 'UTF-8', 
                mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
        $lines = Explode("\n", $text);
        $table = [];
        
        // convert lines
        $header = null;
        $completed_line = ""; // lines will not be complete, if an entry
                              // contains a line break
        $is_continued = false;
        foreach ($lines as $line) {
            if (strlen(trim($line)) > 0) { // ignore empty lines and lines with only blanks
                                           // first line is header
                if (is_null($header))
                    $header = str_getcsv($line, ";");
                else {
                    if ($is_continued) {
                        while ($line[0] == " ")
                            $line = substr($line, 1);
                    }
                    $completed_line .= $line;
                    $is_continued = false;
                    /*
                     * a line is complete, if the count of quotes is even. Because a quote itself is replace
                     * by two quotes, and a quoted entry always has a quote on both ends.
                     */
                    $cnt_quotes = substr_count($completed_line, "\"");
                    if ($cnt_quotes % 2 == 0) {
                        // line is complete, except a special line endis found: ' \' denoting that
                        // the line is continued.
                        if (strcmp(substr($completed_line, strlen($completed_line) - 2), " \\") == 0) {
                            $completed_line = substr($completed_line, 0, strlen($completed_line) - 2);
                            $is_continued = true;
                            // var_dump($line);
                            // var_dump($completed_line);
                            // exit();
                        } else {
                            // line is complete. Read it intoindexedarray
                            $raw_row = str_getcsv($completed_line, ";", "\"");
                            // now change it to an associative array
                            $c = 0;
                            foreach ($raw_row as $value) {
                                if (isset($header[$c]))
                                    $row[$header[$c]] = $value;
                                $c ++;
                            }
                            $completed_line = "";
                            // each named array is a table row.
                            $table[] = $row;
                        }
                    }
                }
            }
        }
        return $table;
    }

    /*
     * =========================== load throttling ====================================
     */
    /**
     * <p>Measure the frequency of web page inits, api sessions and errors. Meant to prevent from machine
     * attacks. A set $events_limit of transaction timestamps resides in the $directory, e.g. /log/inits, or
     * /log/api_inits. When this function is called the eldest existing timestamp is read. If it is older than
     * now minus the $event_monitor_period, it is replaced by the current time and becomes the youngest
     * timestamp. If not, an error page is displayed. This limits the count of transactions which are
     * timestamped in $directory to $events_limit. </p><p>Reading the pointer to the eldest timstamp,
     * overwriting this timestamp and increasing the pointer is all done in this function
     * 
     * @param String $directory
     *            directory for timestamp files which record the events, i. e. "inits/", "transactions/" or
     *            "errors/"
     * @param int $events_limit
     *            limit of events per event_monitor_period. Should normally be 3000 for "inits/",
     *            "transactions/" and 100 for "errors/"
     * @return boolean|String true, if the load is below limits, an error response in case of detected
     *         overload.
     */
    public function load_throttle (String $directory, int $events_limit)
    {
        /*
         * method uses a ring buffer of time stamps. A pointer stored within the pointer file always indicates
         * the eldest timestamp written.
         */
        // read the oldest timestamp
        $events_dir = $this->log_dir . $directory;
        if (! file_exists($events_dir))
            mkdir($events_dir);
        $pointer_file = $events_dir . "pointer";
        $pointer = intval(file_get_contents($pointer_file));
        $timestamp_file = $events_dir . $pointer;
        if (file_exists($timestamp_file) === true)
            $timestamp = intval(file_get_contents($timestamp_file));
        else
            $timestamp = 0; // oldest possible value
        $monitor_period_start = time() - $this->event_monitor_period;
        $overload_details = "Pointer: " . $pointer . ", Timestamp@pointer: " . $timestamp .
                 ", monitor_period_start: " . $monitor_period_start . ", time now: " . time();
        // move the pointer to the second eldest timestamp, before refreshing the eldest one.
        $pointer ++;
        if ($pointer >= $events_limit)
            $pointer = 0;
        file_put_contents($pointer_file, strval($pointer));
        // refresh the time stamp. This must only be done after the pointer has increased.
        file_put_contents($timestamp_file, time());
        // return true (= ok) if the eldest timestamp was written before the start of the monitoring
        // period
        if ($timestamp < $monitor_period_start) {
            return true;
        } else { // return an error message
                 // distiguish api response and web client response
            if (strpos($directory, "api_") == 0) {
                $error_response = "406;Overload detected @ " . $directory . ". Details: " . $overload_details;
                $this->logger->log(2, 0, $error_response);
                // pause to preempt retries.
                sleep(3);
                return $error_response;
            } else {
                $this->display_error($this->overload_error_headline, 
                        "In den vergangenen " . $this->event_monitor_period .
                                 " Sekunden sind zu viele Anfragen oder Fehler gekommen. " .
                                 "Zur Abwehr von Maschinenangriffen werden diese Ereignisse gezählt und begrenzt. " .
                                 "Daher ist nun Warten angesagt. Überlaufobjekt: " . $directory .
                                 ". Voraussichtliche Dauer der Sperrung: " .
                                 (3 + intval(($monitor_period_start - $timestamp) / 60)) . " Minuten.", 
                                __FILE__);
            }
        }
    }

    /*
     * ================== Miscellaneous ===========================
     */
    /**
     * Return a timestamp for a booking, based on separate date and time.
     * 
     * @param String $date_str
     *            UK-formatted date string
     * @param String $hour_str
     *            hh:mm formatted time string
     * @return timestamp integer
     */
    public function timestamp_for_date_plus_hour (String $date_str, String $hour_str)
    {
        $ret = strtotime($date_str);
        $hhmm = explode(":", $hour_str);
        return $ret + intval($hhmm[0]) * 3600 + intval($hhmm[1]) * 60;
    }

    /**
     * Get the encoded parameters of a request as plain associative array.
     * 
     * @param String $msg
     *            encoded message of GET URL. is a set of "name;value" pairs, separated by ";;" and as full
     *            String encoded base64. Neither names nor values must contain ";" characters. Control
     *            characters like line feed are discouraged and not tested.
     */
    public function get_msg_params (String $msg)
    {
        $nvps = base64_decode($msg);
    }

    /**
     * "Encrypt" a base64 String by xoring it with a key. Decryption is the same as encryption.
     * 
     * @param String $plain
     *            the plain text, must be a base64 String.
     * @param String $key
     *            the key to xor with. Must also be a base 64 String.
     * @return String $plain xored it with $key
     */
    public function xor64 (String $plain, String $key)
    {
        if (count($this->bitsForChar64) < 64) {
            for ($b = 0; $b < 65; $b ++) {
                $character = substr($this->base64charsPlus, $b, 1);
                $this->charsForBits64[$b] = $character;
                $this->bitsForChar64[$character] = $b;
            }
        }
        $xored = "";
        // the key must not contain a padding character ('=')
        $klen = strlen($key);
        $plen = strlen($plain);
        $k = 0;
        for ($p = 0; $p < $plen; $p ++) {
            $ki = $this->bitsForChar64[substr($key, $k, 1)];
            $pi = $this->bitsForChar64[substr($plain, $p, 1)];
            // do not xor the padding part.
            if ($pi == 64)
                $xored .= "=";
            else
                $xored .= $this->charsForBits64[$pi ^ $ki];
            $k ++;
            if ($k == $klen)
                $k = 0;
        }
        return $xored;
    }
}
