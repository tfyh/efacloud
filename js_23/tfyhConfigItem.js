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
 * A utility class to hold a configuration item
 */
class TfyhConfigItem
{
	// for display management only, temporary property
	state = 0;  
	selected = 0;
	position = -1;
	#layer = 2;  // layer 0 = user, 1 = club, 2 = application
    #uidIndex;  // the uidIndex this item is listed in
	
    #data_item; // its data item
    #parent;  // its parent
    #children;  // its children
    #out;  // the branch csv output
    #out_fields;  // the collection of names of non-empty fields used to
					// create a branch csv output
    
    #trace = false;
    #dataItemClone; 
    #childrensNamelist; 
    #traceFile;

    /**
	 * Enable or disable the trace point. Enablings resets the trace file,
	 * disabling returns and deletes it. The trace file contents is returned.
	 */
    set_trace (enabled)
    {
        this.#trace = false; // exclude the following statements from
								// tracing.
        let uid = this.get_uid();
        if (enabled) {
            this.#dataItemClone = this.#data_item.get_clone(this.get_name() + "_clone");
            this.#childrensNamelist = [];
            for (let cname in this.#children)
            	this.#childrensNamelist[this.#children[cname].get_uid()] = cname;
            this.#traceFile += 
                    "\n" + (new Date()).toLocaleString('en-CA').substring(0, 19) + ": Starting trace for " + this.get_path() +
                             "\n--------------------------------------\n";
            this.#traceFile += " - Descriptor: " + this.#data_item.get_descriptor_json() + "\n";
            this.#traceFile += " - Children: " + JSON.stringify(this.#childrensNamelist) + "\n";
            this.#trace = true;
        } else {
            this.#trace = false;
            this.#traceFile = "";
        }
    }

    /**
	 */
    #addTrace (function_name, at_end = false)
    {
        if (! this.#trace)
            return;
        
        let uid = this.#data_item.get_uid();
        this.#traceFile += 
        	(new Date()).toLocaleString('en-CA').substring(0, 19) + ": " + function_name + ((at_end) ? " (completed)" : "") + ".\n";
        
        let diff_descriptor = false;
        if (this.#dataItemClone) 
            diff_descriptor = this.#data_item.diff_descriptor(this.#dataItemClone, true, false);
        if (diff_descriptor) {
            this.#traceFile += " - Descriptor old A > new B: " + diff_descriptor + "\n";
            this.#traceFile += " - Descriptor: " + this.#data_item.get_descriptor_json() + "\n";
        }
        this.#dataItemClone = this.#data_item.get_clone(this.#data_item.get_name() + "_clone");
        
        let children = {};
        for (let cname in this.#children) {
        	let child = this.#children[cname];
            children[child.#data_item.get_uid()] = cname;
        }
        let diff_children = TfyhData.diff_arrays(this.#childrensNamelist, children);
        if (diff_children) {
        	this.#traceFile += " - Children old A > new B: " + diff_children + "\n";
        	this.#traceFile += " - Children: " + JSON.stringify(children) + "\n";
        }
        this.#childrensNamelist = children;
        
        if ((diff_descriptor.length == 0) && (diff_children.length == 0))
        	this.#traceFile += " - no changes.\n";
    }

    /**
	 * Because all traces are kept in memory rather than on the file system like
	 * in PHP, clear traces is not needed.
	 */
    static clear_traces ()
    {
        // ignored
    }

    /**
	 * Construct a configuration item. It becomes child to parent, replacing any
	 * existing child of this name. For the root set parent = null. The
	 * constructor is "private".
	 */
    constructor (name, type, parent, uid, text_local_name, uidIndex)
    {
        this.#parent = parent;
        if (parent) 
        	this.#parent.#children[name] = this;
        this.#children = [];
        if (type.startsWith(".")) {
            // object type
            if (TfyhData.objectTemplates[type]) {
                // an object template exists. Copy it, but use new uids
                this.#data_item = TfyhData.objectTemplates[type].#data_item.get_clone(name);
                if (uid)
                    this.#data_item.set_uid(uid, this, uidIndex);
                if (text_local_name)
                    this.#data_item.set_descriptor({ text_local_name : text_local_name });
                // the templates data type is "object", but the data type of the
				// new child shall be the path
                // of the object template
                this.set_descriptor({ value_type : type });
                TfyhData.objectTemplates[type].copy_children(this, 99);
            } else {
                // no object template exists. text_local_name is the error
				// message
                this.#data_item = new TfyhDataItem(name, "unknown", null, 
                        _("rnZUYS|object type %1 unknown", type));
            }
        } else {
            // simple data type
            this.#data_item = new TfyhDataItem(name, type, uid, text_local_name);
        }

        // these parts are needed for Javascript UI and search only
        this.#layer = (parent != null) ? parent.#layer : 2;
        this.#uidIndex = uidIndex;
        this.#uidIndex[this.#data_item.get_uid()] = this;
        this.#addTrace("constructor", true);
    }

    /**
     * Get a new configuration root. You can provide a type to read the full object branch. Default type is
     * "branch". If the root is an object, it should be initialized like it, if you want objects to be
     * expanded.
	 */
    static get_new_root (uid, layer, uidIndex, type)
    {
        let newRoot = new TfyhConfigItem("root", ((type) ? type : "branch"), null, uid, "root node", uidIndex);
        // these parts are needed for Javascript UI and search only
        newRoot.#layer = layer;
        newRoot.#uidIndex[newRoot.get_uid()] = newRoot;
        if (uid == 'tfyhRoot')
        	newRoot.set_trace(true);
        return newRoot;
    }

    /**
	 * Read a configuration file and attach it to a node. Reassign all node IDs,
	 * because they are temporary structure information. Any parent record must
	 * precede the child record in the file. Thus, the root node is the first.
	 */
    static read_branch (cfg_filename, expand_objects, layer, uidIndex)
    {
        // read the branch.
    	let fileContents = window.localStorage.getItem(cfg_filename);
        let cfg_array = TfyhToolbox.csvToObject(fileContents);
        if (!cfg_array[0])
        	return null;
        	
        // check whether the first node is root
        let value_type = (cfg_array[0].value_type) ? cfg_array[0].value_type : "string";
        let root_node = null;
        for (let i = 0; i < cfg_array.length; i ++) {
            if (!cfg_array[i].name || !cfg_array[i].uid || !cfg_array[i].value_type) 
                return "Missing name, uid or value type in configuration. Please correct the configuration.";
        	let name = cfg_array[i].name;
        	let uid = cfg_array[i].uid;
        	let type = cfg_array[i].value_type;
            if (i == 0) {
                root_node = TfyhConfigItem.get_new_root(uid, layer, uidIndex);
                root_node.#data_item.parse_descriptor(cfg_array[i]);
                // parsing the descriptor changed the uid, update the index.
                uidIndex[root_node.get_uid()] = root_node;
            } else {
                // if no parent id is provided, return error.
                if (! cfg_array[i].parent) 
                	return "Missing parent Id for " + name + " in configuration. Please correct the configuration.";
            	let parent = root_node.#uidIndex[cfg_array[i].parent];
                if (! parent) 
                	return "Invalid parent Id '" + cfg_array[i].parent + "' for " + 
                    		name + " in configuration. Please correct the configuration.";
                // existing children will be removed. They are most likely part
				// of an object template clone.
                let newItem = new TfyhConfigItem(name, cfg_array[i].value_type, parent, uid, 
                		cfg_array[i].text_local_name, uidIndex);
                if (! expand_objects && type.startsWith("."))
                    for (let cname in newItem.get_children())
                    	newItem.remove_branch(cname);
                // parse the own descriptor and add the item to the uid index.
                newItem.parse_descriptor(cfg_array[i]);
                uidIndex[newItem.get_uid()] = newItem;
            }
        }
        if (root_node)
        	root_node.sort_children(99);
        return root_node;
    }

    /**
	 * Get a clone of this configuration item with a selectable name, always a
	 * new uid and neither parent nor child.
	 */
    get_clone(nameOfClone) {
        this.#addTrace("get_clone");
    	let clone = TfyhConfigItem.get_new_root(null, this.#layer, this.#uidIndex);
    	clone.#data_item = this.#data_item.get_clone(nameOfClone);
    	return clone;
    }
    
    /**
	 * Move the children of a node to another node. This will replace those
	 * children of the to_node which have a name of a child of this node. Set
	 * drilldown to true to copy the entire branch.
	 */
    copy_children (to_node, drilldown) {
        this.#addTrace("copy_children");
        for (let childname in this.#children) {
            // the children will have the same name, but a different uid.
        	let clone = this.#children[childname].get_clone(childname);
        	clone.#parent = to_node;
        	to_node.#children[clone.get_name()] = clone;
        	if (drilldown > 0)
        		this.#children[childname].copy_children(clone, drilldown - 1);
        }
    }

    /**
	 * Attach a branch to a parent item, e. g. when read from a file. This will
	 * replace an existing branch of the branch name.
	 */
    attach_branch (branchname, branch_root)
    {
        this.#addTrace("attach_branch");
        this.#children[branchname] = branch_root;
        branch_root.#parent = this;
        branch_root.#data_item.set_name(branchname);
        // these parts are needed for Javascript UI and search only
        this.#addBranchToIndex(branch_root);
        this.#addTrace("attach_branch", true);
    }
    
    /**
	 * Recursive adding of all branch elements to the uid index.
	 */
    // this function is needed for Javascript UI and search only
    #addBranchToIndex(item) {
    	this.#uidIndex[item.get_uid()] = item;
    	for (let childname in item.get_children())
    		this.#addBranchToIndex(item.get_child(childname));
    }

    /**
     * Update a branch using the input form another branch. The descriptor of 'this' will be replaced be a
     * descriptor clone of 'update_branch'. The same happens for children that exist on both sides. Children of
     * 'update_branch' that do not exist at 'this' will be attached. Then drilldown is performed for all children
     * asf. as deep as 'update_branch' provides. Use a full descriptor for upate, always with defaults.
     */
    update_branch (branchname, update_branch) {
        this.#addTrace("update_branch");
        this.replace_descriptor(update_branch.get_descriptor_clone());
        for (let cname in update_branch.get_children()) {
            if (this.has_child(cname))
                this.get_child(cname).update_branch(cname, update_branch.get_child(cname));
            else
                this.attach_branch(cname, update_branch.get_child(cname));
        }
        this.#addTrace("update_branch", true);
    }

    /**
	 * Remove a child branch from a this item. This removes te child entry in
	 * $this and the parent entry in the child item.
	 */
    remove_branch (name)
    {
        this.#addTrace("remove_branch");
        if (!this.has_child(name))
            return false;
        delete (this.#children[name].parent);
        delete (this.#children[name]);
        this.#addTrace("remove_branch", true);
        return true;
    }

    /**
	 * Move a child branch within the children sequence. The sequence is the one
	 * created by adding the items. (See:
	 * https://stackoverflow.com/questions/5525795/does-javascript-guarantee-object-property-order)
	 */
    move_child (item, by)
    {
        this.#addTrace("move_child");
        if (by == 0) // nothing to move
            return true;
        
        // rearrange the chldren names
		let childrenNames = Object.keys(this.#children);
        // identify the current and new item position
		let fromPosition = childrenNames.indexOf(item.get_name());
		let toPosition = fromPosition + by;
        // do not move, if target position is beyond the ends
        if ((toPosition >= childrenNames.length) || (toPosition < 0))
            return false;

        // cache name of object at current position
        let cachedName = childrenNames[fromPosition];
        // now move the names in between fromPosition and toPosition
        // this will duplicate the name at the $to_position
        let end = Math.abs(by);
        let fwd = by / end;
        for (let i = 1; i <= end; i ++)
        	childrenNames[fromPosition + ((i - 1) * fwd)] = childrenNames[fromPosition + (i * fwd)];
        // replace the name at the toPosition by the cached name
        childrenNames[toPosition] = cachedName;

        // now rearrange the children according to the rearranged names.
		let childrenCached = this.#children;
		this.#children = {};
		for (let childname of childrenNames)
			this.#children[childname] = childrenCached[childname];

		this.#addTrace("move_child", true);
        return true;
    }

    /**
	 * Sort all children to get the branches first
	 */
    sort_children(drilldown) {
        this.#addTrace("sort_children");
    	// split children into branches and leafs
    	let branchNames = []; 
    	let leafNames = []; 
        for (let childname in this.#children) {
        	if (this.#children[childname].has_children())
        		branchNames.push(childname);
        	else
        		leafNames.push(childname);
        }
        
        // now rearrange the children according to the rearranged names.
		let childrenCached = this.#children;
		this.#children = {};
		for (let childname of branchNames)
			this.#children[childname] = childrenCached[childname];
		for (let childname of leafNames)
			this.#children[childname] = childrenCached[childname];
		
		// go for further levels, if required.
		if (drilldown > 0)
	        for (let childname in this.#children) 
	        	this.#children[childname].sort_children(drilldown - 1);

		this.#addTrace("sort_children", true);
        return true;
    }

    /**
	 * Return the child of the given name. If no such child exits, create a new
	 * one. If type is null and value is not null, the type will be guessed via
	 * Tfyh_data::guess_type().
	 */
    get_child (name, type, value_str, text_local_name)
    {
        this.#addTrace("get_child");
        if (this.#children[name])
            return this.#children[name];
        if (! type) 
        	type = TfyhData.guess_type(value_str);
        // create child
        let newChild = new TfyhConfigItem(name, type, this, null, text_local_name, this.#uidIndex);
        // set value
        let value = TfyhData.parse(value_str, type);
        newChild.set_descriptor({ value_current : value });

        this.#addTrace("get_child", true);
        return newChild;
    }

    /**
	 * Get the difference of two configuration branches. The report is a string,
	 * empty on no difference.
	 */
    diff (to_compare, exclude_current_value, exclude_defaults, new_line, drilldown)
    {
        this.#addTrace("diff");
        let diff_collected = this.get_name() + " ?= " + to_compare.get_name() + ": ";
        let diff_data_item = to_compare.#data_item.diff(this.#data_item, exclude_current_value, exclude_defaults);
        let value = to_compare.get_value_as_string("csv");
        diff_collected += ((strlen(diff_data_item) == 0) ? _("GtIhLH|equal") + ". " : diff_data_item) + new_line;
        let names_collected = [];
        for (let name in this.#children) {
        	let child = this.#children[name];
            names_collected.push(name);
            if (! to_compare.has_child(name))
                diff_collected += _("ppcb9y|Missing child item") + " name. ";
            else
                diff_collected += child.diff(to_compare.get_child(name), exclude_current_value, exclude_defaults,
                        new_line);
        }
        for (let name in to_compare.#children) {
        	if (! this.has_child(name))
                diff_collected += _("sDUfIr|Extra child item ") + " name. ";
        }
        return diff_collected;
    }

    /**
	 * Output a branch to a String, csv or html type.
	 * 
	 * @return String the String representing the branch of this item.
	 */
    to_string (as_html, language_code = "csv", drilldown = 99, 
            include_defaults = false)
    {
        this.#addTrace("to_string");
        // collect and sort all fields which are used in the branch
        this.#out_fields = [];
        this.#collect_fields(this, drilldown, include_defaults);
        this.#out_fields.sort();
        
        // output of headline for of the fields used
        this.#out = (as_html) ? "<table><tr><th>uid</th><th>parent</th><th>name</th>" : "uid;parent;name";
        for (let i in this.#out_fields)
            this.#out += (as_html) ? "<th>" + this.#out_fields[i] + "</th>" : ";" + this.#out_fields[i];
        this.#out += (as_html) ? "</tr>\n" : "\n";
        // collect data of the fields used
        this.#out_item(this, as_html, language_code, drilldown, include_defaults);
        this.#out += (as_html) ? "</table>\n" : "";
        return this.#out;
    }

    /**
	 * Collect all descriptor fields which have a non-null value in any item of
	 * the branch.
	 */
    #collect_fields (cfg_item, drilldown, include_defaults)
    {
        if (include_defaults) {
            // if you include the defaults also include all fields.
            // this is needed to get a descriptor which allows field deletion.
            this.#out_fields = TfyhData.get_descriptor_field_names();
            return;
        }
        
        let descriptor = cfg_item.#data_item.get_descriptor_clone();
        let data_type = descriptor.value_type;
        if (! include_defaults)
            descriptor = TfyhData.clone_without_defaults(descriptor);
        
        for (let name in descriptor) {
        	let field = descriptor[name];
        	let df_type = TfyhData.get_descriptor_field_data_type(name, descriptor.value_type);
        	let df_parseAs = TfyhData.parseAs(df_type);
        	let is_string = (df_parseAs.localeCompare("string") == 0) && ! Array.isArray(field);
            let is_empty_String = is_string && (!field || (field.length == 0));
            if ((field != null) && ! is_empty_String && (this.#out_fields.indexOf(name) < 0)
            		&& (TfyhData.item_keys.indexOf(name) < 0))
            	this.#out_fields.push(name);
        }
        // drilldown
        if (drilldown > 0)
            for (let childname in cfg_item.#children) {
            	let child = cfg_item.#children[childname];
                this.#collect_fields(child, drilldown - 1, include_defaults);
            }
    }

    /**
	 * Output a branch to a String, csv or html type. Result in self::$out.
	 * 
	 * @return String the String to write
	 */
    #out_item (cfg_item, as_html, language_code, drilldown, include_defaults)
    {
        // define separators
    	let line_start = (as_html) ? "<tr><td>" : "";
        let separator = (as_html) ? "</td><td>" : ";";
        let line_end = (as_html) ? "</td></tr>\n" : "\n";
        // write uid, parent uid and name
        this.#out += line_start + cfg_item.get_uid() + separator +
                 ((cfg_item.#parent) ? cfg_item.#parent.get_uid() : "") + separator +
                 cfg_item.get_name() + separator;
        // get descriptor for export and write it
        let descriptor_out = (include_defaults) ? cfg_item.#data_item.get_descriptor_clone()
        		 : cfg_item.#data_item.get_descriptor_without_defaults();
        this.#out += TfyhConfigItem.#write_descriptor(descriptor_out, as_html, this.#out_fields, language_code);
        // close line
        this.#out += line_end;
        
        // drilldown
        if (drilldown > 0) {
            // start with own properties (children without grandchildren) first
			// for result readability reasons
            let own_property_names = [];
            for (let childname in cfg_item.#children) {
            	let child = cfg_item.#children[childname];
                if (! child.has_children()) {
                    this.#out_item(child, as_html, language_code, drilldown - 1, include_defaults);
                    own_property_names.push(childname);
                }
            }
            // continue with the remainder, i.e. the child branches
            for (let childname in cfg_item.#children) {
            	let child = cfg_item.#children[childname];
                if (own_property_names.indexOf(childname) < 0)
                	this.#out_item(child, as_html, language_code, drilldown - 1, include_defaults);
            }
        }
    }

    /**
	 * Return a String representation of a descriptor
	 */
    static #write_descriptor(descriptor, as_html, field_names, language_code = "csv")
    {
    	let separator = (as_html) ? "</td><td>" : ";";
        if (field_names.length == 0) {
        	let field_names = [];
            for (let descriptor in name) 
                field_names.push(name);
        }
        let out = "";
        // write descriptor fields
        for (let name of field_names) {
        	let descriptor_data_type = (descriptor.value_type) ? descriptor.value_type : "string";
        	let type_field = TfyhData.get_descriptor_field_data_type(name, descriptor_data_type);
            out += separator;
            if (descriptor.hasOwnProperty(name)) {
                if (descriptor[name] == null) 
                	out += TfyhData.format(null, type_field, language_code);
                else if (TfyhData.is_descriptor_field_name(name)) {
                    let text = TfyhData.format(descriptor[name], type_field, language_code);
                    // see
    				// https://stackoverflow.com/questions/24816/escaping-html-strings-with-jquery
                    out +=((as_html) ? $('<div/>').text(text).html() : TfyhToolbox.encodeCsvEntry(text));
                }
            }
        }
        return out.substring(separator.length);
    }

    /**
	 * Parse and set the descriptor values. This starts always with the defaults
	 * for the data type and overwrites any default by the given descriptor
	 * information. NOTA BENE: The previous descriptor, if there was one, is
	 * dropped.
	 */
    parse_descriptor (descriptor_record)
    {
        this.#addTrace("parse_descriptor");
        this.#data_item.parse_descriptor(descriptor_record);
        this.#addTrace("parse_descriptor", true);
    }

    /**
	 * Simple setter. Copy the fields of the source items descriptor and keep
	 * other fields.
	 */
    copy_descriptor (source)
    {
        this.#addTrace("copy_descriptor");
        this.#data_item.set_descriptor(source.#data_item.get_descriptor_clone());
        this.#addTrace("copy_descriptor", true);
    }

    /**
	 * Setter. Only sets provided fields and keeps other fields. Ignores extra
	 * fields.
	 */
    set_descriptor (descriptor)
    {
        this.#addTrace("set_descriptor");
        this.#data_item.set_descriptor(descriptor);
        this.#addTrace("set_descriptor", true);
    }

    get_descriptor_field (name)
    {
        this.#addTrace("get_descriptor_field");
        return this.#data_item.get_descriptor_field (name);
    }

    get_descriptor_clone ()
    {
        this.#addTrace("get_descriptor_clone");
        return this.#data_item.get_descriptor_clone ();
    }

    get_name ()
    {
        this.#addTrace("get_name");
        return this.#data_item.get_name();
    }

    get_uid ()
    {
        this.#addTrace("get_uid");
        return this.#data_item.get_uid();
    }

    get_type ()
    {
        this.#addTrace("get_type");
        return this.#data_item.get_type();
    }

    get_addable_type ()
    {
        this.#addTrace("get_addable_type");
        return (! this.#data_item.get_descriptor_field("node_addable_type")) ? "" : this.#data_item.get_descriptor_field("node_addable_type");
    }

    get_value ()
    {
        this.#addTrace("get_value");
        return this.#data_item.get_value();
    }

    get_value_as_string (language_code = "csv")
    {
        this.#addTrace("get_value_as_string");
        return TfyhData.format(this.get_value(), this.get_type(), language_code);
    }

    get_min ()
    {
        this.#addTrace("get_min");
        return this.#data_item.get_min();
    }

    get_max ()
    {
        this.#addTrace("get_max");
        return this.#data_item.get_max();
    }

    /**
	 * Get the absolute path of this item, e.g. ".app.view.width". The path is
	 * an empty Sting for the root item and never ends with a trailing dot, but
	 * starts with a dot for other items than the root.
	 */
    get_path ()
    {
        this.#addTrace("get_path");
        let path = "";
        let current = this;
        while (current.#parent != null) {
            path = "." + current.get_name() + path;
            current = current.#parent;
        }
        return (path.length == 0) ? "." : path;
    }

    /**
	 * Get the configuration by its path, must be part of the same tree. Steps
	 * up to the root and then down to the item. On errors null ist returned..
	 */
    get_by_path (path)
    {
        this.#addTrace("get_own_root");
        let current = this;
        while (current.#parent != null)
            current = current.#parent;
        let root = current;
        let elements = path.split(/\./g);
        for (let element of elements) { 
        	if (element.length > 0) {
        		if (! current.has_child(element))
        			return null;
        		else
        			current = current.get_child(element);
        	}
        }
        return current;
    }

    get_children ()
    {
        this.#addTrace("get_children");
        return this.#children;
    }

    get_parent ()
    {
        this.#addTrace("get_parent");
        return this.#parent;
    }

    /**
	 * Return true, if the child of the given name exists.
	 */
    has_child (name)
    {
        this.#addTrace("has_child");
        return (typeof this.#children[name] !== 'undefined');
    }

    has_children ()
    {
        this.#addTrace("has_children");
        return Object.keys(this.#children).length > 0;
    }

    // -------------------------------------------------------------
    // The remainder is only for js GUI, not in needed PHP.
    // -------------------------------------------------------------
    
    // UI ONLY - NO PHP TWIN
    // UI ONLY - NO PHP TWIN
    /**
	 * Get a this items descriptor field values as string. Set forForm to false
	 * to get "true"/"false" instead of "on"/"" for boolean.
	 */
    getDescriptor_asStrings(languageCode = "csv", forForm = true) {
    	let descriptorAsStrings = {};
    	let descriptor = this.#data_item.get_descriptor_clone();
    	for (let fieldname in descriptor){
    		let fieldDataType = TfyhData.get_descriptor_field_data_type(fieldname, descriptor.value_type);
    		if (forForm && (fieldDataType.localeCompare("boolean") == 0))
        		descriptorAsStrings[fieldname] = (descriptor[fieldname]) ? "on" : "";
    		else
    			descriptorAsStrings[fieldname] = TfyhData.format(descriptor[fieldname], fieldDataType, languageCode);
    	}
    	return descriptorAsStrings;
    }

    // UI ONLY - NO PHP TWIN
    getEditForm() {
    	return this.#data_item.get_descriptor_field("item_editForm");
    }

    // UI ONLY - NO PHP TWIN
    getLayer() {
    	return this.#layer;
    }

    // UI ONLY - NO PHP TWIN
    getLevel ()
    {
    	if (this.#parent == null)
    		return 0;
        return this.get_path().split(/\./g).length - 1;
    }

}
