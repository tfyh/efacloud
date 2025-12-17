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
 * class file for the security concept generation class.
 */
class Sec_concept
{

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * the Efa-tables class providung special table handling support.
     */
    private $efa_tables;

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The data base connection socket.
     */
    private $variables;

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox
     *            standard application toolbox
     * @param Efa_tables $efa_tables
     *            the efa_tables object to execute on transactions. If no execution is needed, e.g. for API
     *            testing, this can be ommitted.
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->toolbox = $toolbox;
        $this->socket = $socket;
    }

    /**
     * Prepare the variables as needed by the PDF template of the security concept.
     */
    private function prepare_variables ()
    {
        global $dfmt_d;
        $this->variables = [];
        $this->variables["CSS"] = file_get_contents("../templates/default.css");
        $cfg = $this->toolbox->config->get_cfg();
        
        // Verein
        $this->variables["Verein"] = $cfg["Verein"];
        // Druckdatum
        $this->variables["Druckdatum"] = date($dfmt_d);
        // Betriebsverantwortlich
        $this->variables["Betriebsverantwortlich"] = $cfg["Betriebsverantwortlich"];
        // Hoster
        $this->variables["Hoster"] = $cfg["Hoster"];
        // ServerURL
        $this->variables["ServerURL"] = $_SERVER['HTTP_HOST'];
        // AVVdatum
        $this->variables["AVVdatum"] = $cfg["AVVdatum"];
        
        // configuration
        include_once "../classes/tfyh_form.php";
        $form_layout = "../config/layouts/configparameter_aendern";
        $config_form = new Tfyh_form($form_layout, $this->socket, $this->toolbox, 1, "secConcept");
        $config_form->read_entered();
        $config_form->preset_values($this->toolbox->config->get_cfg(), true);
        $config_data = $config_form->get_entered();
        $config_labels = $config_form->get_labels();
        $config_string = "";
        foreach ($config_labels as $key => $label) {
            if (strlen($label) > 0)
                $config_string .= str_replace("<br>", " ", $label) . ": <i>" . str_replace(">", "&gt;", 
                        str_replace("<", "&lt;", str_replace("&", "&amp;", $config_data[$key]))) . "</i>\n";
        }
        $this->variables["configuration"] = str_replace("\n", "<br>", $config_string);
        
        // efaCloudVersion
        $this->variables["efaCloudVersion"] = file_get_contents("../public/version");
        // PHPversion
        $this->variables["PHPversion"] = phpversion();
        if (strlen($this->variables["PHPversion"]) == 0)
            $this->variables["PHPversion"] = "unbekannt";
        $PHPextensions = get_loaded_extensions();
        $this->variables["PHPextensions"] = "";
        foreach ($PHPextensions as $PHPextension)
            $this->variables["PHPextensions"] .= $PHPextension . ", ";
        if (strlen($this->variables["PHPversion"]) == 0)
            $this->variables["PHPversion"] = "keine";
        // MySQLversion
        $this->variables["MySQLversion"] = $this->socket->get_server_info();
        // dbUserKennwortlaenge
        $this->variables["dbUserKennwortlaenge"] = strval(
                mb_strlen($this->toolbox->config->get_cfg_db()["db_up"]));
        
        // max_inits_per_hour
        $this->variables["max_inits_per_hour"] = strval(
                $this->toolbox->config->settings_tfyh["init"]["max_inits_per_hour"]);
        // max_errors_per_hour
        $this->variables["max_errors_per_hour"] = strval(
                $this->toolbox->config->settings_tfyh["init"]["max_errors_per_hour"]);
        
        // accessableWebPerRole
        include_once "../classes/tfyh_menu.php";
        $menu_file_path = "../config/access/imenu";
        $audit_menu = new Tfyh_menu($menu_file_path, $this->toolbox);
        $this->variables["accessableWebPerRole"] = $audit_menu->get_allowance_profile_html($menu_file_path);
        // accessableEfaWebperRole
        $menu_file_path = "../config/access/wmenu";
        $audit_menu = new Tfyh_menu($menu_file_path, $this->toolbox);
        $this->variables["accessableEfaWebPerRole"] = $audit_menu->get_allowance_profile_html($menu_file_path);
        // accessableAPIperRole
        $menu_file_path = "../config/access/api";
        $audit_menu = new Tfyh_menu($menu_file_path, $this->toolbox);
        $this->variables["accessableAPIperRole"] = $audit_menu->get_allowance_profile_html($menu_file_path);
        
        // Zugriffe Web: init, login, errors
        $activities = explode("\n", $this->toolbox->logger->get_activities_csv(14));
        $activities_table = "<table>\n";
        foreach ($activities as $activity)
            if (strcmp("_types_", substr($activity, 0, 7)) != 0)
                $activities_table .= "<tr><td>" . str_replace(";", "</td><td>", $activity) . "</td></tr>";
        $this->variables["ZugriffeWeb"] = $activities_table . "</table>";
        
        // ZugriffeAPI, cf. "../pages/home.php"
        include_once "../classes/efa_config.php";
        $efa_config = new Efa_config($this->toolbox);
        $this->variables["ZugriffeAPI"] = $efa_config->get_last_accesses_API($this->socket, false, true);
        
        // ChangesAll
        include_once "../classes/tfyh_list.php";
        
        $changes_all = "<table><tr><td>Autor</td><td>Modifikationstyp: Anzahl Transaktionen</td></tr>";
        $changes_list = new Tfyh_list("../config/lists/verwalten", 5, "ChangeLog", $this->socket, 
                $this->toolbox);
        $changes_table = [];
        $changes_rows = $changes_list->get_rows();
        foreach ($changes_rows as $changes_row) {
            $author = $changes_row[1];
            $modification_type = explode(":", $changes_row[5], 2)[0];
            if ((strcasecmp($modification_type, "updated") != 0) &&
                     (strcasecmp($modification_type, "deleted") != 0))
                $modification_type = "inserted";
            if (! isset($changes_table[$author]))
                $changes_table[$author] = [];
            if (! isset($changes_table[$author][$modification_type]))
                $changes_table[$author][$modification_type] = 1;
            else
                $changes_table[$author][$modification_type] ++;
        }
        
        foreach ($changes_table as $author => $modification_types) {
            $changes_all .= "<tr><td>$author</td><td>";
            foreach ($modification_types as $modification_type => $count) {
                $changes_all .= $modification_type . ": " . $count . ", ";
            }
            $changes_all .= "</td></tr>";
        }
        $changes_all .= "</table>";
        $this->variables["ChangesAll"] = $changes_all;
        
        // privilegierteNutzer
        $privileged_list = new Tfyh_list("../config/lists/verwalten", 3, "Privilegierte Nutzer", $this->socket, 
                $this->toolbox);
        $privileged_rows = $privileged_list->get_rows();
        $privileged_str = "";
        foreach ($privileged_rows as $privileged_row)
            $privileged_str .= $privileged_row[5] . ": (" . $privileged_row[2] . ") " . $privileged_row[3] .
                     " " . $privileged_row[4] . "<br>";
        $this->variables["privilegierteNutzer"] = $privileged_str;
        // efaAdminNutzer
        $efa_admins_list = new Tfyh_list("../config/lists/verwalten", 4, "Nutzer mit efa-Admin Rechten", 
                $this->socket, $this->toolbox);
        $efa_admins_rows = $efa_admins_list->get_rows();
        $efa_admins_str = "";
        foreach ($efa_admins_rows as $efa_admins_row) {
            $workflows = $efa_admins_row[7];
            $concessions = $efa_admins_row[8];
            $workflows_list = str_replace("<td>", "", 
                    str_replace("</td>", "", 
                            str_replace("<tr>", "", 
                                    str_replace("</tr>", "", 
                                            $this->toolbox->users->get_user_services("workflows", "Workflows", 
                                                    $workflows)))));
            $concessions_list = str_replace("<td>", "", 
                    str_replace("</td>", "", 
                            str_replace("<tr>", "", 
                                    str_replace("</tr>", "", 
                                            $this->toolbox->users->get_user_services("concessions", 
                                                    "Concessions", $concessions)))));
            $efa_admins_str .= "(" . $efa_admins_row[2] . ") " . $efa_admins_row[3] . " " . $efa_admins_row[4] .
                     ": " . $workflows_list . $concessions_list . "<br>";
        }
        $this->variables["efaAdminNutzer"] = $efa_admins_str;
        // auditLog
        $this->variables["auditLog"] = str_replace("\n", "<br>", 
                str_replace("  ", " &nbsp;", 
                        str_replace(">", "&gt;", 
                                str_replace("<", "&lt;", 
                                        str_replace("&", "&amp;", file_get_contents("../log/app_audit.log"))))));
    }

    /**
     * Create the security concept PDF file
     */
    public function create_HTML ()
    {
        $this->prepare_variables();
        $template = "efaCloud_Sicherheitskonzept_" . $this->toolbox->config->language_code;
        $template_path = "../templates/" . $template . ".html";
        $template_html = file_get_contents($template_path);
        foreach ($this->variables as $key => $value) {
            $template_html = str_replace("{#" . $key . "#}", $value, $template_html);
        }
        return $template_html;
    }

    /**
     * Create the security concept PDF file
     */
    public function create_PDF ()
    {
        require_once '../classes/pdf.php';
        $pdf = new PDF($this->toolbox, $this->socket, $this->toolbox->users->user_table_name);
        $template = "efaCloud_Sicherheitskonzept_" . $this->toolbox->config->language_code;
        $this->prepare_variables();
        
        $template_path = "../templates/" . $template . ".html";
        $template_html = file_get_contents($template_path);
        foreach ($this->variables as $key => $value) {
            $template_html = str_replace("{#" . $key . "#}", $value, $template_html);
        }
        $saved_at = $pdf->create_pdf($template, i("W2wpqV|efaCloud Security Concep..."), 0, $this->variables);
        $this->toolbox->logger->log(0, intval($this->toolbox->users->session_user["@id"]), 
                i("M4P1uK|Security concept created..."));
        return $saved_at;
    }
}
    
