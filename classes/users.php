<?php

/**
 * A utility class to read and provide the specific user profile according to the application. It is separated
 * from the user.php to keep the latter generic for all applications.
 */
include_once '../classes/user_generic.php';
class Users extends User_generic
{

    /**
     * Construct the Userprofile class. This just assigns the toolbox
     */
    public function __construct (Toolbox $toolbox)
    {
        parent::__construct($toolbox);
    }

    /*
     * ======================== Application specific user property management =====================
     */
    /**
     * Provide an html table with all stored data of the user.
     * 
     * @param int $user_id
     *            the users ID (number, not data base record id)
     * @param Socket $socket
     *            the common database socket
     * @param bool $short
     *            set tot true to get a short version of the profile, rather than the full
     */
    public function get_user_profile (int $user_id, Socket $socket, bool $short = false)
    {
        $user_to_read = $socket->find_record($this->user_table_name, $this->user_id_field_name, $user_id);
        if ($user_to_read === false)
            return "<table><tr><td><b>Nutzer nicht gefunden.</b>&nbsp;&nbsp;&nbsp;</td>" . "<td>" .
                     $this->user_id_field_name . ": '" . $user_id . "'</td></tr>\n";
        // main data
        $html_str = "<table>";
        if ($short) {
            $html_str .= "<tr><td><b>" . $user_to_read["Titel"] . " " .
                     $user_to_read[$this->user_firstname_field_name] . " " .
                     $user_to_read[$this->user_lastname_field_name] . "</b>&nbsp;&nbsp;&nbsp;</td>";
            $html_str .= "<td>" . $user_to_read["Strasse"] . ", " . $user_to_read["Plz"] . " " .
                     $user_to_read["Ort"] . "</td></tr>\n";
            $html_str .= "<tr><td><b>Telefon</b>&nbsp;&nbsp;&nbsp;</td><td>privat: " .
                     $user_to_read["Telefon_privat"] . " / mobil: " . $user_to_read["Handy"] . "</td></tr>\n";
        }
        foreach ($user_to_read as $key => $value) {
            $show = ! $short || (strcasecmp($key, "EMail") === 0) || (strcasecmp($key, "Rolle") === 0);
            if ($value && $show) {
                if (strcasecmp($key, "IBAN") === 0)
                    $html_str .= "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" .
                             substr($value, 0, strlen($value) - 5) . "XXXXX" . "</td></tr>\n";
                elseif (strcasecmp($key, "Passwort_Hash") === 0)
                    $html_str .= "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" .
                             ((strlen($value) > 10) ? "gesetzt" : "nicht gesetzt") . "</td></tr>\n";
                elseif (strcasecmp($key, "Subskriptionen") === 0)
                    $html_str .= $this->get_user_services("subscriptions", $key, $value);
                elseif (strcasecmp($key, "Workflows") === 0)
                    $html_str .= $this->get_user_services("workflows", $key, $value);
                elseif (strcasecmp($key, "Datenschutzmaske") === 0)
                    $html_str .= $this->get_user_services("Datenschutzmaske", $key, $value);
                else
                    $html_str .= "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . $value .
                             "</td></tr>\n";
            }
        }
        
        $user_id = intval($user_to_read[$this->user_id_field_name]);
        $html_str .= $this->get_user_attributes($user_id, $socket, "Funktionen", "von - bis", 2, 3, 4);
        $html_str .= $this->get_user_attributes($user_id, $socket, "Ehrungen", "am", 2, 3, 0);
        $html_str .= $this->get_user_attributes($user_id, $socket, "Spinde", "seit", 1, 4, 0);
        
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
                if (strcasecmp($key, "IBAN") === 0) {
                    $res_IBAN = $this->toolbox->checkIBAN($value);
                    $datum_intval = intval(
                            str_replace("-", "", 
                                    str_replace(".", "", $user_to_check["SEPA_Datum_Mandatsref"])));
                    if (($res_IBAN == false) || (strcmp(strtoupper($value), $value) != 0))
                        $result .= "IBAN " . $value . " ungültig (ggf. klein geschrieben). ";
                    elseif (! $user_to_check["SEPA_Datum_Mandatsref"] || ($datum_intval == 0))
                        $result .= "SEPA_Datum_Mandatsref " . $user_to_check["SEPA_Datum_Mandatsref"] .
                                 " zur IBAN erforderlich und ungültig. ";
                } elseif ((strcasecmp($key, "Geburtsdatum") === 0) && (($this->toolbox->check_and_format_date(
                        $value) === false) || (intval(substr($value, 0, 4)) < 1910))) {
                    $result .= "Geburtsdatum " . $value . " ungültig. ";
                } elseif ((strcasecmp($key, "EMail") === 0) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $result .= "EMail-Adresse " . $value . " ist ungültig. ";
                }
            } else {
                // mandatory integer fields are not checked. They can always be 0, and show up here
                // in that case. Affects: Subskriptionen, Workflows, Anzahl Zeitschriften.
                // mandatory fields for all
                if ((strcasecmp($key, $this->user_lastname_field_name) === 0) ||
                         (strcasecmp($key, "Abteilung") === 0) || (strcasecmp($key, "Beitragsart") === 0) ||
                         (strcasecmp($key, "Zahlweise") === 0) || (strcasecmp($key, "Rolle") === 0) ||
                         (strcasecmp($key, "SEPA_Kz_Ausfuehrung") === 0))
                    $result .= "Erforderliches Feld " . $key . " ohne Eintrag. ";
                // mandatory field which may be "0"
                // e.g. if ((strlen($value) == 0) && (strcasecmp($key, "Plz") === 0))
                // $result .= "Erforderliches Feld " . $key . " ohne Eintrag. ";
            }
        }
        return $result;
    }
}