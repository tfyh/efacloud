<?php
/**
 *
 *       efaCloud
 *       --------
 *       https://www.efacloud.org
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
 * A utility class to read and provide the specific user profile according to the application. It is separated
 * from the user.php to keep the latter generic for all applications.
 */
include_once '../classes/tfyh_user.php';

class Users extends Tfyh_user
{

    /**
     * Construct the Userprofile class. This just assigns the toolbox
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        parent::__construct($toolbox);
    }

    /* ======================== Application specific user property management ===================== */
    /**
     * Provide an html table with all stored data of the user.
     * 
     * @param int $user_id
     *            the efaCloudUserID to get the profile from
     * @param Tfyh_socket $socket
     *            the common database socket
     * @param bool $short
     *            set tot true to get a short version of the profile, rather than the full
     */
    public function get_user_profile (int $user_id, Tfyh_socket $socket, bool $short = false)
    {
        $user_to_read = $socket->find_record($this->user_table_name, $this->user_id_field_name, $user_id);
        if ($user_to_read === false)
            return "<table><tr><td><b>" . i("cTi6L7|User not found.") . "</b>&nbsp;&nbsp;&nbsp;</td>" . "<td>" .
                     $this->user_id_field_name . ": '" . $user_id . "'</td></tr>\n";
        else
            return $this->get_user_profile_on_array($user_to_read, $socket, $short);
    }

    /**
     * Provide an html table with all stored data of the user.
     * 
     * @param int $id
     *            the user's ID to get the profile from
     * @param Tfyh_socket $socket
     *            the common database socket
     * @param bool $short
     *            set tot true to get a short version of the profile, rather than the full
     */
    public function get_user_profile_on_ID (int $id, Tfyh_socket $socket, bool $short = false)
    {
        $user_to_read = $socket->find_record($this->user_table_name, "ID", $id);
        if ($user_to_read === false)
            return "<table><tr><td><b>Nutzer nicht gefunden.</b>&nbsp;&nbsp;&nbsp;</td>" . "<td>ID: '" . $id .
                     "'</td></tr>\n";
        else
            return $this->get_user_profile_on_array($user_to_read, $socket, $short);
    }

    /**
     * Provide an html table with all stored data of the user.
     * 
     * @param array $user_to_read
     *            the user to get the profile from
     * @param Tfyh_socket $socket
     *            the common database socket
     * @param bool $short
     *            set tot true to get a short version of the profile, rather than the full
     */
    public function get_user_profile_on_array (array $user_to_read, Tfyh_socket $socket, bool $short = false)
    {
        // main data
        $html_str = "<table>";
        if ($short) {
            $html_str .= "<tr><td><b>" . $user_to_read["Titel"] . " " .
                     $user_to_read[$this->user_firstname_field_name] . " " .
                     $user_to_read[$this->user_lastname_field_name] . "</b>&nbsp;&nbsp;&nbsp;</td>";
            $html_str .= "<td>" . $user_to_read["Strasse"] . ", " . $user_to_read["Plz"] . " " .
                     $user_to_read["Ort"] . "</td></tr>\n";
            $html_str .= "<tr><td><b>" . i("D9NwwV|Phone") . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . i("LHk83t|home:") . " " .
                     $user_to_read["Telefon_privat"] . " / mobil: " . $user_to_read["Handy"] . "</td></tr>\n";
        }
        $html_str .= "<tr><th><b>" . i("ugf7Xy|Property") . "</th><th>Wert</th></tr>";
        $no_values_for = "";
        foreach ($user_to_read as $key => $value) {
            $show = ! $short || (strcasecmp($key, "EMail") === 0) || (strcasecmp($key, "Rolle") === 0);
            if ($value && $show && (strcasecmp($key, "ecrhis") != 0)) {
                if (strcasecmp($key, "Passwort_Hash") === 0)
                    $html_str .= "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" .
                             ((strlen($value) > 10) ? i("rObLc3|set") : i("eGLG1w|not set")) . "</td></tr>\n";
                elseif (strcasecmp($key, "Subskriptionen") === 0)
                    $html_str .= $this->get_user_services("subscriptions", $key, $value);
                elseif (strcasecmp($key, "Workflows") === 0)
                    $html_str .= $this->get_user_services("workflows", $key, $value);
                elseif (strcasecmp($key, "Concessions") === 0)
                    $html_str .= $this->get_user_services("concessions", $key, $value);
                else
                    $html_str .= "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . $value .
                             "</td></tr>\n";
            }
            if ((! $value) && (strcasecmp($key, "ecrhis") != 0) && (strcasecmp($key, "Workflows") != 0) &&
                     (strcasecmp($key, "Concessions") != 0))
                $no_values_for .= $key . ", ";
        }
        if (strlen($no_values_for) > 0)
            $html_str .= "<tr><td><b>" . i("nWooch|No values set for") . "</b>&nbsp;&nbsp;&nbsp;</td><td>" .
                     $no_values_for . "</td></tr>\n";
        
        $html_str .= "</table>";
        return $html_str;
    }

    /**
     * Check all user parameters for validity. Similar, but less strict than the check in SPG-Verein-Exporter.
     * 
     * @param Users $users
     *            the Users class to which this here is effectively an extension
     * @param array $user_to_check
     *            the user which shall be checked for validity of all his/her data.
     * @return String the check result. Will be an empty String if all right.
     * @param unknown $user_to_check            
     * @return string
     */
    public function check_user_profile ($user_to_check)
    {
        $result = "";
        foreach ($user_to_check as $key => $value) {
            if ($value) {
                if ((strcasecmp($key, "EMail") === 0) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $result .= "EMail-Adresse " . $value . " ist ungültig. ";
                }
            } else {
                // mandatory integer fields are not checked. They can always be 0, and show up here
                // in that case. Affects: Subskriptionen, Workflows, Anzahl Zeitschriften.
                // mandatory fields for all
                // mandatory field which may be "0"
                // e.g. if ((strlen($value) == 0) && (strcasecmp($key, "Plz") === 0))
                // $result .= "Erforderliches Feld " . $key . " ohne Eintrag. ";
            }
        }
        return $result;
    }
}
