<?php

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
        $this->variables = [];
        $this->variables["CSS"] = file_get_contents("../templates/default.css");
        $cfg = $this->toolbox->config->get_cfg();
        
        // Verein
        $this->variables["Verein"] = $cfg["Verein"];
        // Druckdatum
        $this->variables["Druckdatum"] = date("d.m.Y");
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
        $this->variables["dbUserKennwortlaenge"] = strval(strlen($cfg["db_up"]));
        
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
        $clients = scandir("../log/lra");
        $active_clients = "";
        foreach ($clients as $client) {
            if (($client != ".") && ($client != "..")) {
                $client_record = $this->socket->find_record("efaCloudUsers", "efaCloudUserID", $client);
                if ($client_record !== false) {
                    $active_clients .= "<p>" . $client_record["Vorname"] . " " . $client_record["Nachname"] .
                             " (#" . $client_record["efaCloudUserID"] . ", " . $client_record["Rolle"] .
                             "), letzte Aktivität: " . file_get_contents("../log/lra/" . $client) . "</p>";
                    if (file_exists("../log/contentsize/" . $client))
                        $active_clients .= "<table><tr><td>" . str_replace("\n", "</td></tr><tr><td>", 
                                str_replace(";", "</td><td>", 
                                        trim(file_get_contents("../log/contentsize/" . $client)))) .
                                 "</td></tr></table>";
                }
            }
        }
        $this->variables["ZugriffeAPI"] = $active_clients;
        
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
        $efa_admins_list = new Tfyh_list("../config/lists/verwalten", 7, "Nutzer mit efa-Admin Rechten", 
                $this->socket, $this->toolbox);
        $efa_admins_rows = $efa_admins_list->get_rows();
        $efa_admins_str = "";
        foreach ($efa_admins_rows as $efa_admins_row) {
            $workflows = $efa_admins_row[10];
            $concessions = $efa_admins_row[11];
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
        $template = "efaCloud_Sicherheitskonzept";
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
        $template = "efaCloud_Sicherheitskonzept";
        $this->prepare_variables();
        
        $template_path = "../templates/" . $template . ".html";
        $template_html = file_get_contents($template_path);
        foreach ($this->variables as $key => $value) {
            $template_html = str_replace("{#" . $key . "#}", $value, $template_html);
        }
        $saved_at = $pdf->create_pdf($template, "efaCloud_Sicherheitskonzept", 0, $this->variables);
        $this->toolbox->logger->log(0, intval($_SESSION["User"]["Mitgliedsnummer"]), 
                "Sicherheitskonzept erzeugt.");
        return $saved_at;
    }
}
    