<?php

/**
 * class file for the Menu class This class reads the menu and returns it, filtered to those entries which are
 * permitted to the current user.
 */
class Tfyh_menu
{

    /**
     * HTML snippet at start of menu
     */
    private $html_menu_start = "\n" .
             "<!--============================== menu - start =========================-->" . "\n";

    /**
     * HTML snippet at start of level 1 list
     */
    private $html_list_l1 = '<div class="w3-padding-64 w3-large">' . "\n";

    /**
     * HTML snippet at start of level 1 item. In case of top for submenus use {link} = "javascript:void(0)"
     * for a submenu open trigger, {onclick} = 'onclick="openSubMenu([idOfParent])"', and {caret} =
     * '<b>&#x23f7</b>'. Else set {onclick} = '', {caret} = '', and {link} to target link.
     */
    private $html_item_l1 = '<a{href} class="w3-bar-item menuitem" id="{id}" ' .
             '{onclick}{hidden}>{headline}{caret}</a>' . "\n";

    /**
     * HTML snippet at start of level 2 list. {parent} will be replaced by the parent {menu_title}
     */
    private $html_list_l2 = '';

    /**
     * HTML snippet at start of level 2 item.
     */
    private $html_item_l2 = '<div class="w3-bar-block w3-hide w3-medium subMenu{parent}">' . "\n" .
             '<a{href} class="w3-bar-item w3-bar-item-2 menuitem" id="{id}" ' . '{onclick}{hidden}>{headline}</a>' .
             "\n" . '</div>' . "\n";

    /**
     * HTML snippet at end of menu
     */
    private $html_menu_end = '<footer class="w3-small w3-center" id="footer">' .
             '<br><br><br>##user##<br>##version##<br>##copyright##</footer></div>' . "\n" .
             "<!--============================== menu - end ===========================-->" . "\n";

    /**
     * the menu definition array, as was read from the csv file passed in the constructor
     */
    private $menu_def_array = null;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * will be set to true, if the menu path is not "../config/access/pmenu"
     */
    private $is_not_public;

    /**
     * Construct the menu from its template file. A template file is a flat file of menu items, starting with
     * a programmatic name, folloewd by name=value pairs preceded by ' .' Name value pairs define the menu
     * item. Menu items will be displayed in the sequence of the file. Level 2 item names must start with a
     * "_".
     * 
     * @param String $menu_file_path
     *            the file for the menu definition, e.g. "../config/access/pmenu"
     */
    function __construct (String $menu_file_path, Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $this->is_not_public = (strcasecmp($menu_file_path, "../config/access/pmenu") != 0);
        $raw_menu_def_array = $toolbox->read_csv_array($menu_file_path);
        // reead the implicit menu information on parent, caret and click behaviour
        $last_parent = "";
        foreach ($raw_menu_def_array as $raw_menu_def) {
            
            $menu_def = $raw_menu_def;
            $menu_def["caret"] = '';
            $menu_def["parent"] = "";
            // multiple option for the link: direct call to a javascript function, event definition or page
            // load (default).
            $has_link = (isset($menu_def["link"]) && (strlen($menu_def["link"]) > 0));
            $is_event_call = $has_link && (strcasecmp(substr($menu_def["link"], 0, 6), "event:") == 0);
            $event_call = ($is_event_call) ? "do-" . substr($menu_def["link"], 6) : "";
            $is_script_call = $has_link && (strcasecmp(substr($menu_def["link"], 0, 8), "<script>") == 0);
            $script_call = ($is_script_call) ? str_replace("</script>", "", substr($menu_def["link"], 8)) : "";
            $menu_def["onclick"] = ($is_script_call) ? ' onclick="' . $script_call . ';"' : "";
            $menu_def["href"] = ($has_link && ! $is_script_call && ! $is_event_call) ? ' href="' .
                     $menu_def["link"] . '"' : "";
            $menu_def["hidden"] = ($this->toolbox->users->is_hidden_item($menu_def["permission"])) ? " style='display:none'" : "";
            
            if (strpos($menu_def["id"], "_") === 0) {
                $menu_def["level"] = 2;
                $menu_def["parent"] = $last_parent;
                // The id is used for event binding, if it is an event call.
                if ($is_event_call)
                    $menu_def["id"] = $event_call;
            } else {
                $menu_def["level"] = 1;
                $last_parent = $menu_def["id"];
                if (strlen($menu_def["link"]) == 0) {
                    // if the link is empty, open a sub menu at level 1
                    $menu_def["onclick"] = ' onclick="openSubMenu(\'' . $menu_def["id"] . '\')"';
                    $menu_def["caret"] = '<b>&#x25be</b>';
                }
            }
            $this->menu_def_array[] = $menu_def;
        }
        // menu footer: user, version, copyright.
        $username = (isset($_SESSION["User"]) &&
                 isset($_SESSION["User"][$this->toolbox->users->user_lastname_field_name])) ? $_SESSION["User"][$this->toolbox->users->user_firstname_field_name] .
                 " " . $_SESSION["User"][$this->toolbox->users->user_lastname_field_name] . " (" .
                 $_SESSION["User"]["Rolle"] . ")" : "";
        $version = $toolbox->config->app_info["version_string"];
        $copyright = $toolbox->config->app_info["copyright"];
        $this->html_menu_end = str_replace("##user##", $username, $this->html_menu_end);
        $this->html_menu_end = str_replace("##version##", $version, $this->html_menu_end);
        $this->html_menu_end = str_replace("##copyright##", $copyright, $this->html_menu_end);
    }

    /**
     * Return a list of allowed activities per role as text
     * 
     * @param String $menu_file_path
     *            the file for the menu definition, e.g. "../config/access/pmenu"
     * @return String allowed activities per role as text
     */
    public function get_allowance_profile_html (String $menu_file_path)
    {
        $raw_menu_definitions = $this->toolbox->read_csv_array($menu_file_path);
        $allowance_array = [];
        foreach ($raw_menu_definitions as $raw_menu_definition) {
            $roles = explode(",", str_replace(".", "", $raw_menu_definition["permission"]));
            $activity = trim($raw_menu_definition["headline"]);
            foreach ($roles as $role) {
                $prefix = substr($role, 0, 1);
                if (($prefix != '#') && ($prefix != '@') && ($prefix != '$')) {
                    if (! isset($allowance_array[$role]))
                        $allowance_array[$role] = $activity;
                    else
                        $allowance_array[$role] .= ", " . $activity;
                }
            }
        }
        $allowance_str = "<ul>";
        $roles = file_get_contents("../config/access/role_hierarchy");
        $roles_list = explode("\n", $roles);
        foreach ($roles_list as $role_def) {
            if (strlen($role_def) > 0) {
                $nvp = explode("=", trim($role_def));
                $role = str_replace("*", "", $nvp[0]);
                $allowance_str .= "<li><b>" . $role . "</b>: " .
                         ((! isset($allowance_array[$role])) ? "nicht verwendet." : $allowance_array[$role]) .
                         "</li>\n";
            }
        }
        return $allowance_str . "</ul>";
    }

    /**
     * Check whether the $_SESSION["User"] shall get access to the given path. The file name and parent
     * directory name must be the same as in the item definition. This will essentially link the file path to
     * the item and then use the toolbox to check the items permission against the users permissions. Files
     * may have multiple invocations within the menu. All will be checked until a permission is found.
     * 
     * @param String $path
     *            the path of the page which shall be accessed. Only file name and parent directory are used,
     *            so the path can be provided as relative and as absolute path.
     * @param array $user
     *            The user for which the check shall be performed. Default is the $_SESSION["User"], but for
     *            API-Access such user is not set.
     * @return true, if access shall be granted. If the path does not fit any of the menu items links, false
     *         is returned.
     */
    public function is_allowed_menu_item (String $path, array $user = null)
    {
        if (is_null($user))
            $user = $_SESSION["User"];
        $path_elements = explode("/", $path);
        $cpe = count($path_elements);
        // now run specific checks
        $name_to_check = substr($path, strrpos($path, "/") + 1);
        $is_allowed_item = false;
        foreach ($this->menu_def_array as $item) {
            if (strlen($item["link"]) > 0) {
                $link_elements = explode("/", trim($item["link"]));
                $cle = count($link_elements);
                // split off any paramters from path
                if (strpos($link_elements[$cle - 1], "?") !== false)
                    $link_elements[$cle - 1] = substr($link_elements[$cle - 1], 0, 
                            strpos($link_elements[$cle - 1], "?"));
                // error page display is always allowed. Check whether link ends with
                // 'pages/error.php'
                if ((strcasecmp("error.php", $path_elements[$cpe - 1]) == 0) && (strcasecmp("pages", 
                        $path_elements[$cpe - 2]) == 0))
                    return true;
                // do normal role check: compare the paths fo the menu item and the requested path.
                if ((strcasecmp($link_elements[$cle - 1], $path_elements[$cpe - 1]) == 0) && (strcasecmp(
                        $link_elements[$cle - 2], $path_elements[$cpe - 2]) == 0)) {
                    $is_allowed_item = $is_allowed_item || $this->toolbox->users->is_allowed_item(
                            $item["permission"], $user);
                }
            }
        }
        
        // If the page is not allowed, this may also be a publicly allowed page, but now in a session
        // with an authenticated user. In order not to blow up the internal menu, Access allowance of the
        // public
        // menu is now checked, and if allowed access is granted.
        if (! $is_allowed_item && $this->is_not_public) {
            $pmenu = new Tfyh_menu("../config/access/pmenu", $this->toolbox);
            $is_allowed_item = $pmenu->is_allowed_menu_item($path, $user);
            unset($pmenu);
        }
        
        // return result.
        return $is_allowed_item;
    }

    /**
     * Check whether a different role shall be allowed to be used by a verified user, usually for test
     * purposes.
     * 
     * @param String $user_role
     *            the role of the verified user.
     * @param String $use_as_role
     *            the role which the user wants to use.
     * @return true, if the $use_as_role is lower in hierarchy than or equal to $user_role.
     */
    function is_allowed_role_change (String $user_role, String $use_as_role)
    {
        if (strcasecmp($use_as_role, $user_role) == 0)
            return true;
        $roles_of_hierarchy = $this->toolbox->users->role_hierarchy[$user_role];
        foreach ($roles_of_hierarchy as $r)
            if (strcasecmp($use_as_role, $r) == 0)
                return true;
        return false;
    }

    /**
     * Get the menu based on the role of $_SESSION["User"].
     * 
     * @param String $role
     *            the role which requests access. The role will be expanded according to the hierarchy and all
     *            included roles are as well checked. If $role is null, allowance is checked for role
     *            $this->toolbox->users->anonymous_role.
     */
    function get_menu ()
    {
        $m_html = $this->html_menu_start;
        if ($this->toolbox->config->debug_level > 0)
            $m_html .= "<span style='color:#b00;background-color:#fff;text-align:center;' class='w3-bar-item'><b>DEBUG-MODUS</b></span>\n";
        $m_html .= $this->html_list_l1;
        $l = 1;
        $close_list = "";
        $l1_i = 0;
        
        foreach ($this->menu_def_array as $item) {
            $id = $item["id"];
            if ($item["level"] === 2) {
                // level 2 menu item.
                $i_html = $this->html_item_l2;
                // if last item was level 1, change level and remove list close tag '</ul>'
                if ($l == 1) {
                    // change level
                    $l = 2;
                    // the current level 1 item may have been a disallowed item, then there is
                    // no close tag, which can be removed.
                    if ($l1_i > 0) {
                        $m_html .= $this->html_list_l2;
                    }
                }
            } else {
                // level 1 menu item.
                $i_html = $this->html_item_l1;
                // if last item was level 2, change level
                if ($l == 2) {
                    $l = 1;
                    if ($l1_i > 0) {
                        $l1_i = 0;
                    }
                }
            }
            if ($this->toolbox->users->is_allowed_item($item["permission"])) {
                foreach (["headline","parent","id","hidden","href","onclick","caret"
                ] as $item_def_field)
                    $i_html = str_replace("{" . $item_def_field . "}", $item[$item_def_field], $i_html);
                $m_html .= $i_html;
                if ($l == 1)
                    $l1_i ++;
            }
        }
        $m_html .= $this->html_menu_end;
        return $m_html;
    }
}