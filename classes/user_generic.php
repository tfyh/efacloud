<?php

/**
 * A utility class to hold the user profile management functions which do not depend on the application.
 */
class User_generic
{
    /**
     * Application specific configuration
     */
    protected $action_links;
    
    public $user_table_name;
    
    public $user_id_field_name;
    
    public $user_archive_table_name;
    
    public $user_firstname_field_name;
    
    public $user_lastname_field_name;
    
    public $user_subscriptions;
    
    public $user_workflows;
    
    /**
     * The common toolbox.
     */
    protected $toolbox;

    /**
     * roles may include other roles. Expansion provides the role plus the respective included roles in an
     * array. The role_hierarchy is read from the file "../config/access/role_hierarchy" which must contain
     * per role a line "role=role1,role2,...".
     */
    public $role_hierarchy;

    /**
     * The used name for an anonymous role. Is alsways the one which includes just itself.
     */
    public $anonymous_role;

    /**
     * Is true for those roles for which those, who get it, shall be listed on role control.
     */
    public $is_priviledged_role;

    /**
     * Construct the Users class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $roles = file_get_contents("../config/access/role_hierarchy");
        $roles_list = explode("\n", $roles);
        foreach ($roles_list as $role_def) {
            if (strlen($role_def) > 0) {
                $nvp = explode("=", trim($role_def));
                $main_role = trim($nvp[0]);
                $is_priviledged_role = (substr($main_role, 0, 1) == "*");
                if ($is_priviledged_role)
                    $main_role = substr($main_role, 1);
                $this->is_priviledged_role[$main_role] = $is_priviledged_role;
                $included_roles = explode(",", trim($nvp[1]));
                if (count($included_roles) == 1)
                    $this->anonymous_role = $main_role;
                $this->role_hierarchy[$main_role] = $included_roles;
            }
        }
        $settings_tfyh = $toolbox->config->settings_tfyh;
        $this->action_links = $settings_tfyh["users"]["action_links"];
        $this->user_table_name = $settings_tfyh["users"]["user_table_name"];
        $this->user_id_field_name = $settings_tfyh["users"]["user_id_field_name"];
        $this->user_archive_table_name = $settings_tfyh["users"]["user_archive_table_name"];
        $this->user_firstname_field_name = $settings_tfyh["users"]["user_firstname_field_name"];
        $this->user_lastname_field_name = $settings_tfyh["users"]["user_lastname_field_name"];
        $this->user_subscriptions = $settings_tfyh["users"]["user_subscriptions"];
        $this->user_workflows = $settings_tfyh["users"]["user_workflows"];
    }

    /*
     * ======================== Access Control ==============================
     */
    /**
     * Check whether an item is hidden on the menu, i. e. it is not shown, but can be accessed. This is
     * declared by a preceding "." prior to the permission of the item..
     * 
     * @param String $permission
     *            the permission of the menu or list item which shall be checked.
     * @return true, if the item is hidden
     */
    public function is_hidden_item ($permission)
    {
        return (strcasecmp(".", substr($permission, 0, 1)) == 0);
    }

    /**
     * Check whether a role shall get access to the given item. The role will be expanded according to the
     * hierarchy and all included roles are as well checked, except it is preceded by a '!'. If the permission
     * String is preceded by a "." the menu will not be shown, but accessible - same for all accessing roles.
     * 
     * @param String $permission
     *            the permission String of the menu item or list which shall be accessed.
     * @param array $user
     *            The user for which the check shall be performed. Default is the $_SESSION["User"], but for
     *            API-Access such user is not set.
     * @return true, if access shall be granted
     */
    public function is_allowed_item (String $permission, array $user = null)
    {
        if (is_null($user))
            $user = $_SESSION["User"];
        $accessing_role = (isset($user) && isset($user["Rolle"])) ? $user["Rolle"] : $this->anonymous_role;
        $subscriptions = ($this->user_subscriptions && isset($user) && isset($user["Subskriptionen"])) ? $user["Subskriptionen"] : 0;
        $workflows = ($this->user_workflows && isset($user) && isset($user["Workflows"])) ? $user["Workflows"] : 0;
        // else it must match one of the role in the hierarchy.
        $roles_of_hierarchy = $this->role_hierarchy[$accessing_role];
        $permissions_of_item = ($this->is_hidden_item($permission)) ? substr($permission, 1) . "," : $permission .
                 ",";
        $permitted = false;
        foreach ($roles_of_hierarchy as $r) {
            // find the role of the role hierarchy in the permissions String
            // add a comma to both, becasue the String is comma separated
            if (strpos($permissions_of_item, $r . ",") !== false)
                $permitted = true;
        }
        // or meet the permitted subscriptions.
        if (! $permitted)
            $permissions_of_item_array = explode(",", $permissions_of_item);
        if (! $permitted && ($subscriptions > 0) && (strpos($permissions_of_item, '#') !== false)) {
            $subscriptions_allowed = 0;
            foreach ($permissions_of_item_array as $permissions_of_item_element)
                if (strpos($permissions_of_item_element, "#") !== false)
                    $subscriptions_allowed = $subscriptions_allowed |
                             intval(substr($permissions_of_item_element, 1));
            if (($subscriptions & $subscriptions_allowed) > 0)
                $permitted = true;
        }
        // or finally meet the permitted workflows.
        if (! $permitted && ($workflows > 0) && (strpos($permissions_of_item, '@') !== false)) {
            $workflows_allowed = 0;
            foreach ($permissions_of_item_array as $permissions_of_item_element)
                if (strpos($permissions_of_item_element, "@") !== false)
                    $workflows_allowed = $workflows_allowed | intval(substr($permissions_of_item_element, 1));
            if (($workflows & $workflows_allowed) > 0)
                $permitted = true;
        }
        return $permitted;
    }

    /**
     *
     * @return String an HTML formatted overviewn on granted accesses for plausibility checking.
     */
    public function get_all_accesses (Socket $socket)
    {
        $html_str = "<h4>Rollen</h4>";
        foreach ($this->is_priviledged_role as $_role => $_is_priviledged) {
            if ($_is_priviledged) {
                $html_str .= "<h5>$_role</h5><p>";
                $all_priviledged = $socket->find_records($this->user_table_name, "Rolle", $_role, 1000);
                foreach ($all_priviledged as $priviledged)
                    $html_str .= "&nbsp;&nbsp;#<a href='../forms/nutzer_aendern.php?done=0&id=" .
                             $priviledged["ID"] . "'>" . $priviledged[$this->user_id_field_name] . "</a>: " .
                             ((isset($priviledged["Titel"])) ? $priviledged["Titel"] : "") . " " .
                             $priviledged[$this->user_firstname_field_name] . " " .
                             $priviledged[$this->user_lastname_field_name] . ".<br>";
                if (! $all_priviledged)
                    $html_str .= "&nbsp;&nbsp;Niemand<br>";
            }
        }
        foreach ($this->is_priviledged_role as $_role => $_is_priviledged) {
            if (! $_is_priviledged) {
                $html_str .= "<h5>$_role</h5><p>";
                $all_non_priviledged = $socket->find_records($this->user_table_name, "Rolle", $_role, 5000);
                if (! $all_non_priviledged)
                    $html_str .= "&nbsp;&nbsp;Niemand<br>";
                else
                    $html_str .= "&nbsp;&nbsp;In Summe " . count($all_non_priviledged) . " Nutzer.<br>";
            }
        }
        if ($this->user_workflows)
            $html_str .= $this->get_service_users_listed("workflows", "Workflows", false, $socket);
        if ($this->user_subscriptions)
            $html_str .= $this->get_service_users_listed("subscriptions", "Subskriptionen", true, $socket);
        
        return $html_str;
    }

    /**
     * Provide a list of users for all services existing
     * 
     * @param String $type
     *            either "subscriptions" or "workflows", i. e. the sevices file name in /config/access.
     * @param String $field_name
     *            either "Subskriptionen" or "Workflows", i. e. the field name in the user record
     * @param bool $count_only
     *            set true to get the count of service users instead of the named list
     * @param Socket $socket
     *            the data base socket to retrieve data
     * @return string
     */
    private function get_service_users_listed (String $type, String $field_name, bool $count_only, 
            Socket $socket)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/$type");
        $services_list = (count($services_set) > 0) ? "<h4>$field_name</h4>" : "";
        foreach ($services_set as $service) {
            $titel = ((strcasecmp("workflows", $type) == 0) ? "@" : "#") . $service["Flag"] . ": " .
                     $service["Titel"];
            $services_list .= "<h5>$titel</h5><p>";
            $service_users = $socket->find_records_sorted($this->ser_table_name, $field_name, 
                    $service["Flag"], 5000, "&", $this->user_firstname_field_name, true);
            $count_of_service_users = ($service_users) ? count($service_users) : 0;
            $services_list .= "In Summe " . $count_of_service_users . " Nutzer.<br>";
            if (! $count_only)
                foreach ($service_users as $service_user)
                    $services_list .= "<a href='../forms/" . strtolower($field_name) .
                             "_aendern.php?done=0&id=" . $service_user["ID"] . "'>#" .
                             $service_user[$this->user_id_field_name] . "</a>: " .
                             ((isset($service_user["Titel"])) ? $service_user["Titel"] : "") . " " .
                             $service_user[$this->user_firstname_field_name] . " " .
                             $service_user[$this->user_lastname_field_name] . ".<br>";
            $services_list .= "</p>";
        }
        return $services_list;
    }

    /**
     * Provide a list of service titles for subscriptions and workflows the user is granted
     * 
     * @param String $type
     *            either "subscriptions" or "workflows", i. e. the sevices file name in /config/access.
     * @param String $key
     *            either "Subskriptionen" or "Workflows", i. e. the field name in the user record
     * @param String $value
     *            the value of the respective field in the user record
     * @return string list of service titles for subscriptions and workflows
     */
    public function get_user_services (String $type, String $key, String $value)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/$type");
        $services_list = "";
        foreach ($services_set as $service)
            if ((intval($value) & intval($service["Flag"])) > 0)
                $services_list .= $service["Titel"] . ", ";
        $change_link = (strcasecmp($type, "subscriptions") == 0) ? "<br><a href='../forms/subskriptionen_aendern.php'> &gt; ändern</a>" : "";
        return "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . $services_list . $change_link .
                 "</td></tr>\n";
    }

    /*
     * ======================== Generic user property management ==============================
     */
    /**
     * Provide a list of attributes for which the user is registered
     * 
     * @param int $user_id
     *            Mitgliedsnummer of user.
     * @param String $attribute
     *            either "Funktionen", "Ehrungen" or "Spinde", i. e. the table name of the attribute table
     * @param String $period_definition
     *            the definition of the time stamp relations, e.g. "am", "seit", "von - bis" of the respective
     *            field in the user record
     * @param int $attr_at
     *            the position of the attribute name within the table row
     * @param int $start_at
     *            the position of the period start within the table row
     * @param int $end_at
     *            the position of the period end within the table row
     * @return string an html formatted attributes table
     */
    public function get_user_attributes (int $user_id, Socket $socket, String $attribute, 
            String $period_definition, int $attr_at, int $start_at, int $end_at)
    {
        $html_str = "<tr><td><b>$attribute</b>&nbsp;&nbsp;&nbsp;</td><td>$period_definition:</td></tr>\n";
        $sql_cmd = "SELECT * FROM `$attribute` WHERE `" . $this->user_id_field_name . "`='" . $user_id . "'";
        $res = $socket->query($sql_cmd);
        $r = 0;
        if ($res !== false)
            do {
                $r ++;
                $row = $res->fetch_row();
                if (! is_null($row)) {
                    $html_str .= "<tr><td>&nbsp;&nbsp;&nbsp;" . htmlspecialchars($row[$attr_at]) . "</td><td>" .
                             $row[$start_at];
                    if ((strpos($period_definition, "-") != false) && ! is_null($row[$end_at]))
                        $html_str .= " - " . $row[$end_at];
                    $html_str .= "</td></tr>\n";
                } else 
                    if ($r === 1)
                        $html_str = "";
            } while ($row);
        else
            $html_str = "";
        return $html_str;
    }

    /**
     * Check within Mitgliederliste and Mitgliederarchiv whether a first name and name already exist to avoid
     * name duplicates.
     * 
     * @param array $new_user
     *            the new user to check. must contain at least a valid
     *            $new_user[$this->user_lastname_field_name] and $new_user[$this->user_firstname_field_name]
     * @param Socket $socket
     *            The socket to the data base.
     * @return string[] false, if no new user was found. Else an array with the last match carrying the
     *         "Status", $this->user_firstname_field_name, $this->user_lastname_field_name,
     *         $this->user_id_field_name.
     */
    public function check_new_user_name_for_duplicates (array $new_user, Socket $socket)
    {
        // check users for identical name. Information will be provided to clarify offline, whether
        // a user has returned or a duplicate name exists. Get those with the same first name.
        $previous_user = [];
        
        if (strlen($this->user_archive_table_name) > 0) {
            $archived_users = $socket->find_records($this->user_archive_table_name, 
                    $this->user_firstname_field_name, $new_user[$this->user_firstname_field_name], 100);
            // check for equality of last name.
            foreach ($archived_users as $archived_user) {
                if (strcasecmp($new_user[$this->user_lastname_field_name], 
                        $archived_user[$this->user_lastname_field_name]) == 0) {
                    $previous_user["Status"] = "archiviert";
                    $previous_user[$this->user_firstname_field_name] = $archived_user[$this->user_firstname_field_name];
                    $previous_user[$this->user_lastname_field_name] = $archived_user[$this->user_lastname_field_name];
                    $previous_user[$this->user_id_field_name] = $archived_user[$this->user_id_field_name];
                }
            }
        }
        // now repeat for all active users.
        $active_users = $socket->find_records($this->user_table_name, $this->user_firstname_field_name, 
                $new_user[$this->user_firstname_field_name], 100);
        // check for equality of last name.
        foreach ($active_users as $active_user) {
            if (strcasecmp($new_user[$this->user_lastname_field_name], 
                    $active_user[$this->user_lastname_field_name]) == 0) {
                $previous_user["Status"] = "aktiv";
                $previous_user[$this->user_firstname_field_name] = $active_user[$this->user_firstname_field_name];
                $previous_user[$this->user_lastname_field_name] = $active_user[$this->user_lastname_field_name];
                $previous_user[$this->user_id_field_name] = $active_user[$this->user_id_field_name];
            }
        }
        return (count($previous_user) == 0) ? false : $previous_user;
    }

    /**
     * Return the respective link set for allowed actions of a verified user regarding the user to modify.
     * 
     * @param int $user_id
     *            the ID of the user for which the action shallt be taken
     * @return an HTML formatted String with the links to the actions allowed
     */
    public function get_action_links (int $user_id)
    {
        $action_links_html = "";
        $a = 0;
        foreach ($this->action_links as $action_link) {
            $parts = explode(":", $action_link);
            if ($this->is_allowed_item($parts[0]))
                $action_links_html .= str_replace("{#ID}", $user_id, $parts[1]);
        }
        return $action_links_html;
    }

}