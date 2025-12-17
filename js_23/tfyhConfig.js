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
 * A utility class to hold all application configuration.
 */

class TfyhConfig
{

    static #cfgBranches;

    appInfo;
    
    #cfgRootItem;
    #uidIndex = {};  // for addressing an item by its uid in the UI
    
    // variables for asynchronous configuration load control
    #loadCtrl = -1;
    #cfgFiles = [];
    
    // field for configuration display
    #cfgPanel = false;
    #treePosition = 0;
    #fieldsWithoutDisplay = [ "sql_type", "sql_size", "sql_null", "sql_default", 
    	"sql_indexed", "sql_trigger_event", "sql_trigger_value", "item_editForm" ];
	#nodeTemplateHTML = 
`<div class='w3-row w3-show' id="item-{uid}">
<div class="cfg-nav" id="dilbocfg-nav">
	<span class="cfg-button" style="padding-left:{level}em;" id="tfyhCfgPanel_{action}_{uid}">{caret}</span>
</div>
<div class="cfg-nav">
	<span class="cfg-item" id="tfyhCfgPanel_show_{uid}"><b>{text_local_name}</b> <small>{value_current}</small></span>
</div>
<div class="cfg-nav" id="dilbocfg-nav">
	{add}
	{delete}
	{edit}
	{moveUp}
	{moveDown}
</div>
</div>
`;
    
    /* ----------------------------------------------------------------- */
    /* ------ BUILD AND LOAD ------------------------------------------- */
    /* ----------------------------------------------------------------- */
	
    /**
	 * Empty constructor.
	 */
    constructor (){}
    
    /**
	 * Initialize the configuration loader
	 */
    init() {
    	let that = this;
    	// identify the user. Do not load the full configuration for anonymous
		// users.
    	$.get('../pages/jsget.php?info=userid', function(data) {
    		// continue, if the user is not anonymous
    		if (parseInt(data.trim()) > 0) {
        		// get the list and modification times of the configuration
				// files
    	    	$.get('../pages/jsget.php?info=parseAs', function(data) {
    	    		TfyhData.initParseAs(data);
	        		// get the list and modification times of the configuration
					// files
	    	    	$.get('../pages/jsget.php?info=mtimes', function(data) {
	    	    		let cfgFiles = data.trim().split(/\;/g);
	    	    		that.#cfgFiles = [];
	            		// get the list and modification times of the
						// configuration files
	    	    		for (let cfgFile of cfgFiles) {
	    	    			let parts = cfgFile.split(/\=/g);
        	    			that.#cfgFiles.push({
        	    				name : parts[0],
        	    				mtime : parseInt(parts[1])
        	    			});
	    	    		}
	    	    		// if completed, start asynchronous configuration
						// loading
	    	    		if (that.#cfgFiles.length > 0)
	    	    			that.#loadCfg();
	    	    	});
    	    	});
    		}
    	});
    }

    /**
	 * Load the full configuration asynchronously, if not cached in the local
	 * storage.
	 */
    #loadCfg() {
    	if (this.#loadCtrl < 0)
    		this.#loadCtrl = 0;
    	let configFile = this.#cfgFiles[this.#loadCtrl];
    	let nowSeconds = Date.now() / 1000;
		let mtimeLocal = window.localStorage.getItem(configFile.name + ".mtime");
		mtimeLocal = (isNaN(parseInt(mtimeLocal))) ? 0 : parseInt(mtimeLocal); 
		if ((configFile.mtime > mtimeLocal) || 
			// configuration was updated on the server or is older
			((nowSeconds - mtimeLocal) > 8640000)) { 
			// than 100 days in seconds
			var that = this;
	    	$.get('../pages/jsget.php?info=' + configFile.name, function(data) {
	    		if (data.substring(0,1) != "<") {
	    			// any error message will start with a tag. Ignore those.
		        	window.localStorage.setItem(configFile.name, data);
		        	window.localStorage.setItem(configFile.name + ".mtime", configFile.mtime);
	    		}
	        	that.#loadCtrl++;
	        	if (that.#loadCtrl < that.#cfgFiles.length) 
	        		that.#loadCfg();
	        	else
	        		that.#initItems();
	    	});
    	} else {
    		this.#loadCtrl++;
        	if (this.#loadCtrl < this.#cfgFiles.length)
        		this.#loadCfg();
        	else
        		this.#initItems();
    	}
    }
    
    /**
	 * Include a file in the configuration. Replace the contents, if already
	 * included
	 */
    includeSettings (settingsFilename)
    {
    	// UI specifi, only javascript
    	const userEditable = ["appSettings", "clubSettings", "uiSettings"];
    	let layer = (userEditable.includes(settingsFilename)) ? 1 : 2;
        // "create file from object template" is only applicable at the server
		// side.

    	let branch_root = TfyhConfigItem.read_branch(settingsFilename, true, layer, this.#uidIndex);
        if ((typeof branch_root == "string") || (branch_root == null)) {
        	alert("Error reading " + settingsFilename + "\n" + branch_root);
        	_stopDirty_; // will throw an error and thus end script
							// execution.
        }
        
        this.#cfgRootItem.attach_branch(settingsFilename, branch_root);
        // object templates initalisation needs the full path, must there be
        // done after attachment to the config root.
        this.#initObjects(branch_root);  // create the obect templates flat
											// array
        this.#expandObjectTemplates();  // expand nested object templates.
    }

    /**
	 * Copy all config item definitions of data type object into the
	 * TfyhData.objectTemplates associative array (path => template branch).
	 */
    #initObjects(item) {
    	if (item.get_type().localeCompare("object") == 0)
    		TfyhData.objectTemplates[item.get_path()] = item;
    	for (let childname in item.get_children())
    		this.#initObjects(item.get_child(childname));
    }

    /**
	 * Object templates can be nested, e.g. the appSettings object with a
	 * apiSettings object as child. Then the apiSettings object in the
	 * appSettings template has no children. Using this template will then
	 * create incomplete object instances. This is cared for here. Actually at
	 * the PHP side, but here for symmetry and completeness reasons also.
	 */
    #expandObjectTemplates ()
    {
        for (let path in TfyhData.objectTemplates) {
        	let templateRoot = TfyhData.objectTemplates[path];
            this.#expandObjectTemplate(templateRoot);
        }
    }

    /**
	 * The recursive section of expand_object_templates.
	 */
    #expandObjectTemplate (item)
    {
        let children_names = Object.keys(item.get_children());
        for (let cname of children_names) {
            let child = item.get_child(cname);
            let ctype = child.get_type();
            if ((child.get_children().length == 0) && child.get_type().startsWith(".")) {
                // the child is an object, but does not have any children.
				// Expand it.
                let cached_descriptor = child.get_descriptor_clone();
                // remove it
                item.remove_branch(cname);
                // add instead the object template
                expanded_child = item.get_child(cname, ctype); 
                // restore the descriptor.
                expanded_child.set_descriptor(cached_descriptor);
                // drill down.
                this.#expandObjectTemplate(expanded_child); 
            }
        }
    }

    /**
	 * Initialize the configuration by reading the item definition files
	 */
    #initItems ()
    {
    	// initialize the data management first.
    	TfyhData.init();
        // create the root node
        this.#cfgRootItem = TfyhConfigItem.get_new_root("tfyhRoot", 2, this.#uidIndex);
        // add available branches
        for (let i in this.#cfgFiles) 
        	this.includeSettings(this.#cfgFiles[i].name);
        
		// Redo the entire process, if the app name changed. This is needed for
		// development purposes when everything is running in the http://
		// localhost root.
        let appName = this.get_by_path(".framework.config.app_name").get_value();
        let lastAppName = window.localStorage.getItem("appName");
        if (lastAppName && (appName != lastAppName)) {
            window.localStorage.clear();
            this.#loadCtrl = -1;
            this.init();
        }

        // no direct data base access, therefore no db configuration read
        // table configuration has been expanded at PHP side already, therefore
		// no table configuration read
        // add the version information from ../public/version, accessable via
		// "$this->app_info"
        this.#initVersionInformation();
        // assignments for direct parameter value access
        this.#initConstants();

        if (this.#cfgPanel) 
        	this.#cfgPanel.refresh();
        
        // continue the initialization sequence in <app>Main
        initModules(); 
    }
    
    /**
	 * some post-load assignments for direct parameter value access of the
	 * application
	 */
    #initConstants ()
    {
        // assign directly accessable framework settings
        this.app_name = this.#getValueByPath(".framework.config.app_name");
        this.app_url = this.#getValueByPath(".framework.config.app_url");
    }

    /**
	 * Initialize the version information in memory for display, logging and
	 * debugging
	 */
    #initVersionInformation ()
    {
    	this.appInfo = {};
    	var that = this;
    	jQuery.get('../public/version', function(data) {
    		that.appInfo["version_string"] = data;
        	jQuery.get('../public/copyright', function(data) {
        		that.appInfo["copyright"] = data;
                if (that.appInfo.version_string.length > 0) {
                    let parts = that.appInfo.version_string.split("_");
                    if (parts.length > 1)
                    	that.appInfo["drop"] = parseInt(parts[1]);
                    let dotted = parts[0].split(/\./g);
                    that.appInfo["release"] = parseInt(dotted[0]);
                    that.appInfo["major"] = (dotted.length > 1) ? parseInt(dotted[1]) : 0;
                    that.appInfo["minor"] = (dotted.length > 2) ? parseInt(dotted[2]) : 0;
                } else {
                	that.appInfo["release"] = 0;
                	that.appInfo["major"] = 0;
                	that.appInfo["drop"] = 0;
                	that.appInfo["drop"] = 0;
                }
        	});
    	});
    }

    /**
	 * In order to not get into a deadlock between configuration and its edit
	 * panel instatiate the configuration without a panel and add the panel
	 * later.
	 */
    setCfgPanel(cfgPanel) {
    	this.#cfgPanel = cfgPanel;
    }
    
    /* ----------------------------------------------------------------- */
    /* ------ GET CONFIGURATION INFORMATION ---------------------------- */
    /* ----------------------------------------------------------------- */

    /**
	 * simple getter of the settings root.
	 */
    get_cfg_root_item ()
    {
        return this.cfg_root_item;
    }

    /**
	 * Get the item by the path provided.
	 */
    get_by_path (path)
    {
        if (typeof this.#cfgRootItem == 'undefined')
        	return null;  // initialization has not been completed yet
        if (!path)
        	return null; 
        let path_elements = path.split(/\./g);
        let current = this.#cfgRootItem;
        let p = 1;
        while ((p < path_elements.length) && current.has_child(path_elements[p])) 
            current = current.get_child(path_elements[p ++]);
        return (p == path_elements.length) ? current : null;
    }

    /**
	 * Resolves the configuration path to the value with the given name. If the
	 * path is not unique, the first path will be used.
	 */
    #getValueByPath (path)
    {
        let item = this.get_by_path(path);
        return (item != null) ? item.get_value() : null;
    }

	/**
	 * Simple getter
	 */
	getItemForUid(uid) {
		return this.#uidIndex[uid];
	}

	/* ----------------------------------------------------------------- */
    /* ------ HTML DISPLAY --------------------------------------------- */
    /* ----------------------------------------------------------------- */
	
	// get the HTML code for a single item
	itemToTableHTML (item) {
		let html = "<h3>" + item.get_path() + "</h3>";
		html += "<table>";
		let descriptor = item.get_descriptor_clone();
		let furtherEmpty = "";
		let furtherNotEmpty = "";
		html += "<tr><th>" + _(descriptor.text_local_name) + "</th><th>" 
			+ TfyhToolbox.escapeHtml(TfyhData.format(descriptor.value_current, descriptor.value_type)) + "</th></tr>";
		for (let fieldname in descriptor) {
			let fieldType = TfyhData.get_descriptor_field_data_type(fieldname, descriptor.value_type);
			let valueToShow = TfyhData.format((fieldname.startsWith("text_")) ? _(descriptor[fieldname]) : descriptor[fieldname], fieldType);
			if (this.#fieldsWithoutDisplay.indexOf(fieldname) < 0)
				html += "<tr><td>" + TfyhData.get_descriptor_field_local_name(fieldname) + "</td><td>" 
					+ TfyhToolbox.escapeHtml(valueToShow).replace(/\n/g,"<br>") + "</td></tr>";	
			else if (valueToShow.length > 0)
				furtherNotEmpty += fieldname + " [" + valueToShow.substring(0, 15) + ((valueToShow.length > 15) ? "... " : "") + "], ";
			else
				furtherEmpty += fieldname + ", ";
		}
		if (furtherNotEmpty)
			html += "<tr><td>" + _("tmmMX3|further not empty fields...") + "</td><td>" 
			+ furtherNotEmpty + "</td></tr>";	
		if (furtherEmpty)
			html += "<tr><td>" + _("aRC0en|further empty fields:") + "</td><td>" 
			+ furtherEmpty + "</td></tr>";	
		if (Object.keys(item.get_children()).length > 0)
			html += "<tr><td>" + _("gAGdJs|children:") + "</td><td></td></tr>";	
		for (let childname in item.get_children()) {
			let child = item.get_children()[childname];
			let valueToShow = child.get_value_as_string(userPreferences.languageCode);
			if (child.get_type().localeCompare("boolean") == 0)
				// special case boolean. In order to be formatted for the form
				// logic, this will always be "on" or "" instead of "true" and
				// "false".
				valueToShow = ((valueToShow.length > 0) && (valueToShow.localeCompare("false") != 0)) ? _("CDffNm|true") : _("Sz2rY8|false");
			if (valueToShow.length == 0) 
				// children with null values, usually branches and objects.
				valueToShow = "(" + child.get_descriptor_field("value_type") + ")";				
			html += "<tr><td>&raquo;&nbsp;" + child.get_descriptor_field("text_local_name") + "</td><td>" 
				+ TfyhToolbox.escapeHtml(valueToShow).replace(/\n/g,"<br>") + "</td></tr>";	
		}
		return html + "</table>";
	}
	
	/**
	 * Count the siblings which are of branch or object type. It is presumed,
	 * that the siblings names are sorted, the branch type siblings first.
	 */
	#countOfBranchTypeSiblings(item, siblingsNames) {
		let cnt = 0;
		if (siblingsNames !== false) {
			for (let siblingName of siblingsNames) {
				let siblingType = item.get_parent().get_child(siblingName).get_type();
				if (siblingType.startsWith(".") || (siblingType.localeCompare("branch") == 0))
					cnt++;
				else
					return cnt;
			}
		}
		return cnt;
	}
	
	// get the HTML code for a singe item
	#getBranchItemHTML (item, mode, levelOffset) {
		
		const iconAdd = "<span class='material-icons'>&#xe145;</span>"; 
		const iconEdit = "<span class='material-icons'>&#xe3c9;</span>"; 
		const iconDelete = "<span class='material-icons'>&#xe872;</span>"; 
		const iconMoveUp = "<span class='material-icons'>&#xe5d8;</span>"; 
		const iconMoveDown = "<span class='material-icons'>&#xe5db;</span>"; 
		
		// prepare function
		if ((item.state === 0) && item.has_children())
			item.state = 1;
		const caret = { 0 : "▫", 1 : "▸", 2 : "▾" };
		const action = { 0 : "show", 1 : "expand", 2 : "collapse" };
		const editOrShowForLayer = { 0 : "edit", 1 : "edit", 2 : "show" };
		if ((mode == "edit") && (editOrShowForLayer[item.getLayer()] != "edit"))
			return "";
		let html = this.#nodeTemplateHTML;
		
		// prepare all values and conditions
		let uid = item.get_uid();
		// thisAddable is true, if items can be added to this as children
		let thisAddable = (item.get_addable_type().length > 0);
		// thisIsParentAddable is true, if this item is addable to the parent -
		// and thus movable and deletable
		let thisIsParentAddable = (item.get_parent())  
				? (item.get_parent().get_addable_type().localeCompare(item.get_type()) == 0) : false;
		let editOrShow = ((mode == "show") || (Object.keys(item.get_children()).length == 0))
				? "show" : editOrShowForLayer[item.getLayer()];
		let addOption = (thisAddable && (mode != "show")) ? '<span class="cfg-button" id="tfyhCfgPanel_add_' + uid + '">' + iconAdd + '</span>' : "";
		let editOption = (item.getLayer() == 2) ? "" : '<span class="cfg-button" id="tfyhCfgPanel_edit_' + uid + '">' + iconEdit + '</span>';
		let deleteOption = (thisIsParentAddable && (mode != "show")) ? '<span class="cfg-button" id="tfyhCfgPanel_delete_' + uid + '">' + iconDelete + '</span>' : "";
		let siblings = (item.get_parent()) ? Object.keys(item.get_parent().get_children()) : false;
		let siblingsCnt = this.#countOfBranchTypeSiblings(item, siblings);
		let childPosition = (siblings === false) ? -1 : (siblings.indexOf(item.get_name()));   
		let moveUpOption = (thisIsParentAddable && (childPosition > 0) && (mode != "show")) 
				? '<span class="cfg-button" id="tfyhCfgPanel_moveUp_' + uid + '">' + iconMoveUp + '</span>' : "";
		let moveDownOption = (thisIsParentAddable && (childPosition < (siblingsCnt - 1)) && (mode != "show")) 
				? '<span class="cfg-button" id="tfyhCfgPanel_moveDown_' + uid + '">' + iconMoveDown + '</span>' : "";
		let valueToShow = item.get_value_as_string(userPreferences.languageCode);
		html = html.replace(/\{uid\}/g, uid)
					.replace(/\{add\}/g, addOption).replace(/\{delete\}/g, deleteOption).replace(/\{edit\}/g, editOption)
					.replace(/\{moveUp\}/g, moveUpOption).replace(/\{moveDown\}/g, moveDownOption)
					.replace(/\{level\}/g, item.getLevel() - levelOffset)
					.replace(/\{text_local_name\}/g, item.get_descriptor_field("text_local_name"))
					.replace(/\{value_current\}/g, TfyhToolbox.escapeHtml(valueToShow))
					.replace(/\{caret\}/g, caret[item.state])
					.replace(/\{action\}/g, action[item.state])
					.replace(/\{editOrShow\}/g, editOrShow);
		return html;
	}
	
	/**
	 * refresh the configuratio panel
	 */
	refreshPanel() {
		if (this.#cfgPanel)
			this.#cfgPanel.refresh();
	}
	
	/**
	 * get the full tree as HTML for management, call it without arguments
	 */
	getConfigBranchHTML (item = null, htmlSoFar, first, mode, levelOffset) {
		if (item == null) item = this.#cfgRootItem;
		if (first) { 
			this.#treePosition = 0;
			item.state = (item.has_children()) ? 2 : 0;
			levelOffset = item.getLevel();
		} else
			this.#treePosition++;
		htmlSoFar += this.#getBranchItemHTML(item, mode, levelOffset);
		item.position = this.#treePosition;
		first = false;
		if (item.state == 2)
			for (let i in item.get_children())
				htmlSoFar = this.getConfigBranchHTML(item.get_children()[i], htmlSoFar, false, mode, levelOffset);
		return htmlSoFar;
	}
	
    /* ----------------------------------------------------------------- */
    /* ------ MODIFY TO SERVER, ONLY JAVASCRIPT, NO PHP ---------------- */
    /* ----------------------------------------------------------------- */
	
	/**
	 * post a modification transaction to the server. mode is 1, 2, 3, 4, 5 for
	 * insert, update, delete, moveUp, moveDown. The csvDefinition may contain a
	 * single item or a complex branch. It is ignored for move & delete. After
	 * completion callback is called with the response text as argument. Use the
	 * first three digits (+;) to get the result code. Use callback and
	 * cachedItem to return after execution.
	 */
	postModify(mode, item, csvData, callback) {
		// Assign handlers immediately after making the request,
		// and remember the jqxhr object for this request
		let that = this;
		let postData = {
				mode : mode,
				uid : item.get_uid(), // for update and delete
				path : item.get_path(), // for create (=insert)
				csv : csvData, // for update and create
		}
		console.log("Configuration modify post: mode = " + mode 
				+ ", item = " + item.get_path() + "(" + item.get_uid() + ")");
		var jqxhr = $
			.post( "../forms/jspost.php", postData)
			.done(function(done) {
				that.#onPostDone(done, callback);
			})
			.fail(function(fail) {
				that.#onPostFail(fail, callback);
			});
	}
	
	/**
	 * called after successful completion.
	 */
	#onPostDone(done, callback) {
		let responseParts = done.split(";");
		let success = ((parseInt(responseParts[0]) < 400) && (parseInt(responseParts[0]) > 0)); 
		if (success)
			console.log("Configuration modifification completed.");
		else
			console.log("Configuration modifification error " + responseParts[0] + ": " + responseParts[1]);
		if (typeof callback == 'function')
			callback(success, done);
	}

	/**
	 * called after failed competeion.
	 */
	#onPostFail(fail, callback) {
		console.log("Configuration modify failed. Error " + fail.responseText);
		if (typeof callback == 'function')
			callback(false, fail.responseText);
	}
	
}
