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
 * A utility class to hold the user profile management functions which do not depend on the application.
 */
class Tfyh_user
{

    /**
     * Application specific configuration
     */
    protected $action_links;

    public $user_table_name;

    // the numeric user ID - may be empty
    public $user_id_field_name;

    // an alphanumeric ID, but no valid e-mail address
    public $user_account_field_name;

    // a valid e-mail address
    public $user_mail_field_name;

    public $user_archive_table_name;

    public $user_firstname_field_name;

    public $user_lastname_field_name;

    public $use_subscriptions;

    public $use_workflows;

    public $use_concessions;

    public $useradmin_role;

    public $useradmin_workflows;

    public $anonymous_role;

    public $self_registered_role;

    public $owner_id_fields;

    public $session_user;

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
     * Is true for those roles for which those, who get it, shall be listed on role control.
     */
    public $is_priviledged_role;

    /**
     * Construct the Users class. This reads the configuration, initilizes the logger and the navigation menu,
     * asf.
     */
    public function __construct (Tfyh_toolbox $toolbox)
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
                $this->role_hierarchy[$main_role] = $included_roles;
            }
        }
        $settings_tfyh = $toolbox->config->settings_tfyh;
        
        // user data configuration
        $this->action_links = $settings_tfyh["users"]["action_links"];
        $this->user_table_name = $settings_tfyh["users"]["user_table_name"];
        $this->user_id_field_name = $settings_tfyh["users"]["user_id_field_name"];
        $this->user_account_field_name = (isset($settings_tfyh["users"]["user_account_field_name"])) ? $settings_tfyh["users"]["user_account_field_name"] : "account";
        $this->user_mail_field_name = (isset($settings_tfyh["users"]["user_mail_field_name"])) ? $settings_tfyh["users"]["user_mail_field_name"] : "EMail";
        $this->user_archive_table_name = $settings_tfyh["users"]["user_archive_table_name"];
        $this->user_firstname_field_name = $settings_tfyh["users"]["user_firstname_field_name"];
        $this->user_lastname_field_name = $settings_tfyh["users"]["user_lastname_field_name"];
        
        // user role management
        if (! isset($settings_tfyh["users"]["useradmin_role"]) ||
                 ! isset($settings_tfyh["users"]["self_registered_role"]) ||
                 ! isset($settings_tfyh["users"]["anonymous_role"]) ||
                 ! isset($settings_tfyh["users"]["use_subscriptions"]) ||
                 ! isset($settings_tfyh["users"]["use_workflows"]) ||
                 ! isset($settings_tfyh["users"]["use_concessions"]) || (! isset(
                        $settings_tfyh["users"]["useradmin_workflows"]) &&
                 ! is_null($settings_tfyh["users"]["useradmin_workflows"])) || (! isset(
                        $settings_tfyh["users"]["ownerid_fields"]) &&
                 ! is_null($settings_tfyh["users"]["ownerid_fields"]))) {
            // no i18n required
            echo "Error in settings_tfyh: one of useradmin_role, useradmin_workflows, anonymous_role, self_registered_role, " .
             "use_subscriptions, use_workflows, use_concessions, or ownerid_fields not defined. settings_tfyh are:<br>" .
             Tfyh_toolbox::array_to_html($settings_tfyh);
            exit(); // really exit. No test case left over.
        }
        
        // Specifically authorized roles. Table field name: "Rolle" for the user role.
        $this->useradmin_role = $settings_tfyh["users"]["useradmin_role"];
        $this->self_registered_role = $settings_tfyh["users"]["self_registered_role"];
        $this->anonymous_role = $settings_tfyh["users"]["anonymous_role"];
        // User preferences and permissions, table field names: Subskriptionen, Workflows,
        // Concessions
        $this->use_subscriptions = (isset($settings_tfyh["users"]["use_subscriptions"])) ? $settings_tfyh["users"]["use_subscriptions"] : false;
        $this->use_workflows = (isset($settings_tfyh["users"]["use_workflows"])) ? $settings_tfyh["users"]["use_workflows"] : false;
        $this->useradmin_workflows = (isset($settings_tfyh["users"]["useradmin_workflows"])) ? json_decode($settings_tfyh["users"]["useradmin_workflows"]) : "";
        $this->use_concessions = (isset($settings_tfyh["users"]["use_concessions"])) ? $settings_tfyh["users"]["use_concessions"] : false;
        // Owner Id fields in different tables.
        $owner_id_fields = (isset($settings_tfyh["users"]["ownerid_fields"])) ? explode(",", $settings_tfyh["users"]["ownerid_fields"]) : [];
        foreach ($owner_id_fields as $owner_id_field) {
            if (strlen(trim($owner_id_field)) > 0) {
                $nvp = explode(".", trim($owner_id_field));
                if (count($nvp) == 2)
                    $this->owner_id_fields[$nvp[0]] = $nvp[1];
            }
        }
    }

    /* ======================== Access Control ============================== */
    /**
     * Set the session user for cross application reference.
     * 
     * @param array $session_user
     *            the session user. Use null to clear the session user.
     */
    public function set_session_user (array $session_user = null)
    {
        if (! isset($session_user))
            return;
        $this->session_user = $session_user;
        if (! isset($session_user["preferences"]))
            $session_user["preferences"] = "language=de";
        $this->session_user["@firstname"] = (isset($session_user[$this->user_firstname_field_name])) ? $session_user[$this->user_firstname_field_name] : "";
        $this->session_user["@lastname"] = (isset($session_user[$this->user_lastname_field_name])) ? $session_user[$this->user_lastname_field_name] : "";
        $this->session_user["@fullname"] = $this->session_user["@firstname"] . " " .
                 $this->session_user["@lastname"];
        $this->session_user["@id"] = (isset($session_user[$this->user_id_field_name])) ? intval(
                $session_user[$this->user_id_field_name]) : "";
        $this->session_user["@account"] = (isset($session_user[$this->user_account_field_name])) ? $session_user[$this->user_account_field_name] : "";
        $this->session_user["@mail"] = (isset($session_user[$this->user_mail_field_name])) ? $session_user[$this->user_mail_field_name] : "";
        $this->toolbox->config->merge_session_user_preferences();
    }

    /**
     * Check whether an item is hidden on the menu, i. e. it is not shown, but can be accessed. This is
     * declared by a preceding "." prior to the permission of the item.
     * 
     * @param String $permission
     *            the permission of the menu or list item which shall be checked.
     * @return true, if the item is hidden
     */
    public function is_hidden_item ($permission)
    {
        return ($this->is_allowed_or_hidden_item($permission) & 2);
    }

    /**
     * Check whether a role shall get access to the given item. The role will be expanded according to the
     * hierarchy and all included roles are as well checked, except it is preceded by a '!'. If the permission
     * String is preceded by a "." the menu will not be shown, but accessible - same for all accessing roles.
     * 
     * @param String $permission
     *            the permission String of the menu item or list which shall be accessed.
     * @param array $user
     *            The user for which the check shall be performed. Default is the
     *            $this->toolbox->users->session_user.
     * @return true, if access shall be granted
     */
    public function is_allowed_item (String $permission, array $user = null)
    {
        return ($this->is_allowed_or_hidden_item($permission, $user) & 1);
    }

    /**
     * Check for workflows, concessions and subscriptions whether they are allowed for the current user.
     * 
     * @param int $allowed_or_hidden
     *            the $allowed_or_hidden value of all previous checks
     * @param array $permissions_array
     *            permissions of this menu item with leading dots, split into an array
     * @param int $services
     *            the allowed services for the user as integer value representing 32 flags
     * @param String $service_identifier
     *            the identifier String of the servives type: @ - wokflows, $ - concessions, # - subscriptions
     * @return the modified $allowed_or_hidden value after check of this service.
     */
    private function add_allowed_or_hidden_service (int $allowed_or_hidden, array $permissions_array, 
            int $services, String $service_identifier)
    {
        foreach ($permissions_array as $permissions_element) {
            if (strpos($permissions_element, $service_identifier) !== false) {
                $element_hidden = (strpos($permissions_element, ".") === 0);
                $element_service_map = intval(substr($permissions_element, (($element_hidden) ? 2 : 1)));
                $element_allowed = (($services & $element_service_map) > 0);
                if ($element_allowed) {
                    // add allowance, if element is allowed
                    $allowed_or_hidden = $allowed_or_hidden | 1;
                    // remove hidden flag, if allowed and not hidden.
                    if (! $element_hidden && (($allowed_or_hidden & 2) > 0))
                        $allowed_or_hidden = $allowed_or_hidden - 2;
                }
            }
        }
        return $allowed_or_hidden;
    }

    /**
     * Check whether a role shall get access to the given item and, if so, whether it should be displayed in
     * the menu. The role will be expanded according to the hierarchy and all included roles are as well
     * checked, except it is preceded by a '!'. If the permission String is preceded by a "." the menu will
     * not be shown, but accessible - same for all accessing roles.
     * 
     * @param String $permission
     *            the permission String of the menu item or list which shall be accessed.
     * @param array $user
     *            The user for which the check shall be performed. Default is the
     *            $this->toolbox->users->session_user.
     * @return int 0-3 reflecting two bits: for permitted AND with 0x1, for hidden AND with 0x2
     */
    private function is_allowed_or_hidden_item (String $permission, array $user = null)
    {
        if (is_null($user)) {
            if (isset($this->toolbox->users->session_user))
                $user = $this->toolbox->users->session_user;
            else {
                // This happens on access errors
                $user[$this->toolbox->users->user_id_field_name] = - 1;
                $user["Rolle"] = $this->anonymous_role;
            }
        }
        $accessing_role = (isset($user) && isset($user["Rolle"])) ? $user["Rolle"] : $this->anonymous_role;
        $subscriptions = ($this->use_subscriptions && isset($user) && isset($user["Subskriptionen"])) ? $user["Subskriptionen"] : 0;
        $workflows = ($this->use_workflows && isset($user) && isset($user["Workflows"])) ? $user["Workflows"] : 0;
        $concessions = ($this->use_concessions && isset($user) && isset($user["Concessions"])) ? $user["Concessions"] : 0;
        // else it must match one of the role in the hierarchy.
        $roles_of_hierarchy = $this->role_hierarchy[$accessing_role];
        
        // now check permissions. This will for every permissions entry check allowance and display.
        $permissions_array = explode(",", $permission);
        // the $allowed_or_hidden integer carries the result as 0-3 reflecting two bits:
        // for permitted AND with 0x1, for hidden AND with 0x2
        $allowed_or_hidden = 2; // default is not permitted, hidden
        foreach ($permissions_array as $permissions_element) {
            $element_hidden = (strpos($permissions_element, ".") === 0);
            $element_role = ($element_hidden) ? substr($permissions_element, 1) : $permissions_element;
            $element_allowed = in_array($element_role, $roles_of_hierarchy);
            if ($element_allowed) {
                // add allowance, if element is allowed
                $allowed_or_hidden = $allowed_or_hidden | 1;
                // remove hidden flag, if allowed and not hidden.
                if (! $element_hidden && (($allowed_or_hidden & 2) > 0))
                    $allowed_or_hidden = $allowed_or_hidden - 2;
            }
        }
        // or meet the permitted subscriptions.
        if ($subscriptions > 0)
            $allowed_or_hidden = $this->add_allowed_or_hidden_service($allowed_or_hidden, $permissions_array, 
                    $subscriptions, '#');
        // or meet the permitted workflows.
        if ($workflows > 0)
            $allowed_or_hidden = $this->add_allowed_or_hidden_service($allowed_or_hidden, $permissions_array, 
                    $workflows, '@');
        // or meet the permitted concessions.
        if ($concessions > 0)
            $allowed_or_hidden = $this->add_allowed_or_hidden_service($allowed_or_hidden, $permissions_array, 
                    $concessions, '$');
        return $allowed_or_hidden;
    }

    /**
     *
     * @param Tfyh_socket $socket
     *            the common data base access socket
     * @param bool $for_audit_log
     *            set true to return only the counts for the audit log
     * @return String an HTML formatted overview on granted accesses for plausibility checking.
     */
    public function get_all_accesses (Tfyh_socket $socket, bool $for_audit_log = false)
    {
        $html_str = "<h4>" . i("8RhH9W|Roles") . "</h4>";
        $audit_log_str = i("OPc8WE|Count of privileged role...") . " ";
        foreach ($this->is_priviledged_role as $_role => $_is_priviledged) {
            if ($_is_priviledged) {
                $html_str .= "<h5>$_role</h5><p>";
                $audit_log_str .= $_role . " - ";
                $count_role_users = 0;
                $all_priviledged = $socket->find_records($this->user_table_name, "Rolle", $_role, 1000);
                if ($all_priviledged != false)
                    foreach ($all_priviledged as $priviledged) {
                        $user_reference = (isset($priviledged["ID"])) ? "<a href='../forms/nutzer_aendern.php?id=" .
                                 $priviledged["ID"] . "'>" . $priviledged[$this->user_id_field_name] . "</a>" : $priviledged[$this->user_id_field_name];
                        $html_str .= "&nbsp;&nbsp;#" . $user_reference . ": " .
                                 ((isset($priviledged["Titel"])) ? $priviledged["Titel"] : "") . " " .
                                 $priviledged[$this->user_firstname_field_name] . " " .
                                 $priviledged[$this->user_lastname_field_name] . ".<br>";
                        $count_role_users ++;
                    }
                if (! $all_priviledged)
                    $html_str .= "&nbsp;&nbsp;" . i("355gfL|No one") . "<br>";
                $audit_log_str .= $count_role_users . "; ";
            }
        }
        
        $audit_log_str .= "\n" . i("8fGF1t|Count of non-privileged ...") . " ";
        foreach ($this->is_priviledged_role as $_role => $_is_priviledged) {
            if (! $_is_priviledged) {
                $html_str .= "<h5>$_role</h5><p>";
                $all_non_priviledged = $socket->find_records($this->user_table_name, "Rolle", $_role, 5000);
                if (! $all_non_priviledged)
                    $html_str .= "&nbsp;&nbsp;" . i("DEPfjp|No one") . "<br>";
                else
                    $html_str .= "&nbsp;&nbsp;" . i("5Bd5LG|In Total %1 users.", count($all_non_priviledged)) .
                             "<br>";
                $audit_log_str .= $_role . " - " .
                         (($all_non_priviledged) ? strval(count($all_non_priviledged)) : "0") . "; ";
            }
        }
        $audit_log_str .= "\n";
        $html_str .= "</p><p>";
        
        $services_text = "";
        if ($this->use_workflows)
            $services_text .= $this->get_service_users_listed("workflows", "Workflows", false, $socket, 
                    $for_audit_log) . "\n";
        if ($this->use_concessions)
            $services_text .= $this->get_service_users_listed("concessions", "Concessions", false, $socket, 
                    $for_audit_log) . "\n";
        if ($this->use_subscriptions)
            $services_text .= $this->get_service_users_listed("subscriptions", "Subskriptionen", true, 
                    $socket, $for_audit_log) . "\n";
        
        if ($for_audit_log)
            return $audit_log_str . $services_text;
        else
            return $html_str . str_replace("\n", "</p><p>", $services_text) . "</p>";
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
     * @param Tfyh_socket $socket
     *            the data base socket to retrieve data
     * @param bool $for_audit_log
     *            set true to return only the counts for the audit log
     * @return string
     */
    private function get_service_users_listed (String $type, String $field_name, bool $count_only, 
            Tfyh_socket $socket, bool $for_audit_log)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/$type");
        $services_list = (count($services_set) > 0) ? "<h4>$field_name</h4>" : "";
        $audit_log = $field_name . ": ";
        $no_users_at = "";
        
        foreach ($services_set as $service) {
            $titel = ((strcasecmp("workflows", $type) == 0) ? "@" : ((strcasecmp("concessions", $type) == 0) ? "$" : "#")) .
                     $service["Flag"] . ": " . i($service["Titel"]);
            $service_users = $socket->find_records_sorted($this->user_table_name, $field_name, 
                    $service["Flag"], 5000, "&", $this->user_firstname_field_name, true);
            $count_of_service_users = ($service_users) ? count($service_users) : 0;
            if ($count_of_service_users == 0)
                $no_users_at .= $titel . ", ";
            else {
                $services_list .= "<h5>" . $titel . "</h5><p>";
                $services_list .= i("AkTh7r|In Total %1 users.", $count_of_service_users) . "<br>";
                $audit_log .= $titel . " - " . $count_of_service_users . "; ";
                if (! $count_only && is_array($service_users))
                    foreach ($service_users as $service_user)
                        $services_list .= "<a href='../forms/" . strtolower($field_name) . "_aendern.php?id=" .
                                 $service_user["ID"] . "'>#" . $service_user[$this->user_id_field_name] .
                                 "</a>: " . ((isset($service_user["Titel"])) ? $service_user["Titel"] : "") .
                                 " " . $service_user[$this->user_firstname_field_name] . " " .
                                 $service_user[$this->user_lastname_field_name] . ".<br>";
                $services_list .= "</p>";
            }
        }
        
        if (strlen($no_users_at) > 0) {
            $services_list .= "<h5>" . i("4C9I5e|No users for") . "</h5><p>" . $no_users_at . "</p>";
            $audit_log .= "\n " . i("wyHjxb|No users for") . " " . $no_users_at;
        }
        return ($for_audit_log) ? $audit_log : $services_list;
    }

    /**
     * Provide a list of service titles for subscriptions, workflows and concessions which the user is
     * granted. In case of subscriptions a change link is added.
     * 
     * @param String $type
     *            either "subscriptions", "workflows" or "concessions", i. e. the sevices file name in
     *            /config/access.
     * @param String $key
     *            either "Subskriptionen", "Workflows" or "Concessions", i. e. the field name in the user
     *            record
     * @param String $value
     *            the value of the respective field in the user record
     * @return string list of service titles for subscriptions and workflows
     */
    public function get_user_services (String $type, String $key, String $value)
    {
        $services_set = $this->toolbox->read_csv_array("../config/access/$type");
        $services_list = "[" . $value . "] ";
        foreach ($services_set as $service)
            if ((intval($value) & intval($service["Flag"])) > 0)
                $services_list .= i($service["Titel"]) . ", ";
        $change_link = (strcasecmp($type, "subscriptions") == 0) ? "<br><a href='../forms/subskriptionen_aendern.php'> &gt; " .
                 i("08PFcm|change") . "</a>" : "";
        return "<tr><td><b>" . $key . "</b>&nbsp;&nbsp;&nbsp;</td><td>" . $services_list . $change_link .
                 "</td></tr>\n";
    }

    /* ======================== Generic user property management ============================== */
    /**
     * Provide a list of attributes for which the user is registered
     * 
     * @param int $user_id
     *            Mitgliedsnummer of user.
     * @param String $attribute
     *            either "Functionen", "Ehrungen" or "Spinde", i. e. the table name of the attribute table
     * @param String $period_definition
     *            the definition of the time stamp relations, e.g. "am", "seit", "von - bis" of the respective
     *            field in the user record
     * @param int $attr_at
     *            the position of the attribute name within the table row
     * @param int $start_at
     *            the position of the period start within the table row
     * @param int $end_at
     *            the position of the period end within the table row
     * @param bool $short
     *            set true to get a simple string instead of table rows. Default: false
     * @return string an html formatted attributes table
     */
    public function get_user_attributes (int $user_id, Tfyh_socket $socket, String $attribute, 
            String $period_definition, int $attr_at, int $start_at, int $end_at, bool $short = false)
    {
        $list_args = ["{Mitgliedsnummer}" => $user_id
        ];
        include_once "../classes/tfyh_list.php";
        $list_of_attributes = new Tfyh_list("../config/lists/queries", 0, $attribute, $socket, $this->toolbox, 
                $list_args);
        $rows = $list_of_attributes->get_rows();
        if (! is_array($rows) || (count($rows) == 0))
            return "";
        $html_str = "<tr><td><b>$attribute</b>&nbsp;&nbsp;&nbsp;</td><td>$period_definition:</td></tr>\n";
        $html_short = "<tr><td><b>$attribute</b></td><td>\n";
        foreach ($rows as $row) {
            if (! is_null($row)) {
                $html_str .= "<tr><td>&nbsp;&nbsp;&nbsp;" . htmlspecialchars($row[$attr_at]) . "</td><td>" .
                         $row[$start_at];
                $html_short .= "&nbsp;" . htmlspecialchars($row[$attr_at]);
                $end_string = (! is_null($row[$end_at]) && (strpos($row[$end_at], "0000-00-00") === false)) ? $row[$end_at] : i(
                        "EBIhOz|today");
                if (strpos($period_definition, "-") != false) {
                    $html_str .= " - " . $end_string;
                    $html_short .= ":&nbsp;" . $row[$start_at] . " - " . $end_string;
                } else
                    $html_short .= "&nbsp;$period_definition: " . $row[$start_at];
                
                $html_str .= "</td></tr>\n";
                $html_short .= " / ";
            }
        }
        if (strlen($html_short) > 2)
            $html_short = mb_substr($html_short, 0, mb_strlen($html_short) - 2);
        return ($short) ? $html_short : $html_str;
    }

    /**
     * Check within Mitgliederliste and Mitgliederarchiv whether a first name and name already exist to avoid
     * name duplicates.
     * 
     * @param array $new_user
     *            the new user to check. must contain at least a valid
     *            $new_user[$this->user_lastname_field_name] and $new_user[$this->user_firstname_field_name]
     * @param Tfyh_socket $socket
     *            The socket to the data base.
     * @return string[] false, if no new user was found. Else an array with the last match carrying the
     *         "Status", $this->user_firstname_field_name, $this->user_lastname_field_name,
     *         $this->user_id_field_name.
     */
    public function check_new_user_name_for_duplicates (array $new_user, Tfyh_socket $socket)
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
                    $previous_user["Status"] = i("i0VBLI|archived");
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
                $previous_user["Status"] = i("2IHJZD|active");
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
     * @param String $uid
     *            the unique id of the user record, if ID $user_id may not be unique (versionized records).
     * @return an HTML formatted String with the links to the actions allowed. {#ID} will be replaced by
     *         $user_id, {#uid} will be replaced by $uid
     */
    public function get_action_links (int $user_id, String $uid = null)
    {
        $action_links_html = "";
        $a = 0;
        foreach ($this->action_links as $action_link) {
            $parts = explode(":", $action_link);
            if ($this->is_allowed_item($parts[0])) {
                // i18n support
                $text_start = strpos($parts[1], "i('") + 3;
                $text_end = strpos($parts[1], "')");
                if (($text_start !== false) && ($text_end !== false) && ($text_end > $text_start)) {
                    $text = substr($parts[1], $text_start, $text_end - $text_start);
                    $text_i18n = i($text);
                    $parts[1] = substr($parts[1], 0, $text_start - 3) . $text_i18n .
                             substr($parts[1], $text_end + 2);
                }
                $action_link_html = str_replace("{#ID}", $user_id, $parts[1]);
                if (! is_null($uid))
                    $action_link_html = str_replace("{#uid}", $uid, $action_link_html);
                $action_links_html .= $action_link_html;
            }
        }
        return $action_links_html;
    }

    /**
     * Get an empty user for this application
     */
    public function get_empty_user ()
    {
        $user = array();
        $user[$this->user_id_field_name] = - 1;
        $user["ID"] = 0;
        $user["Rolle"] = $this->anonymous_role;
        if ($this->use_subscriptions)
            $user["Subskriptionen"] = 0;
        if ($this->use_workflows)
            $user["Workflows"] = 0;
        if ($this->use_concessions)
            $user["Concessions"] = 0;
        return $user;
    }

    /**
     * Get the user record for a user id. This takes into account versionized records and will only return
     * valid user record, no invalid ones. It can be used to get the session user record.
     * 
     * @param int $user_id
     *            the user id of the user to get
     * @param Tfyh_socket $socket
     *            the data base socket to retrieve the record(s)
     * @return boolean|array the user record, if a valid user record exists. False else.
     */
    public function get_user_for_id (int $user_id, Tfyh_socket $socket)
    {
        $user_records = $socket->find_records_matched($this->user_table_name, 
                [$this->user_id_field_name => $user_id
                ], 10);
        if (($user_records === false) || ! is_array($user_records))
            return false;
        if ((count($user_records) == 1) & (! array_key_exists("invalid_from", $user_records[0]) ||
                 ($user_records[0]["invalid_from"] == 0)))
            return $user_records[0];
        for ($i = 0; $i < count($user_records); $i ++)
            if (floatval($user_records[$i]["invalid_from"]) > Tfyh_toolbox::timef())
                return $user_records[$i];
        return false;
    }
}
