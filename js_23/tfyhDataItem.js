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
 * A utility class to hold a data item
 */
class TfyhDataItem
{

    static #form_input_properties = ["label","type","class","size","maxlength"
    ];
    static #html_input_style = {
    	"%r" : "<div class='w3-row'>",
    	"%c1" : "<div class='w3-col l1'>",
        "%c2" : "<div class='w3-col l2'>",
        "%c3" : "<div class='w3-col l3'>",
        "%c4" : "<div class='w3-col l4'>",
        "%c6" : "<div class='w3-col l6'>",
        "%/" : "</div>"
    };

    #uid;
    #name;
    #descriptor;

    /**
	 * Construct a configuration item. It must always have a parent, except the
	 * root. For the root set $parent = null.
	 */
    constructor (name, type, uid, text_local_name)
    {
    	this.#name = name;
        this.#uid = (uid) ? uid : TfyhToolbox.generateUid().substring(0, 8);
        if (! TfyhData.typeTemplates[type])
            this.#descriptor = TfyhData.get_descriptor_template("unknown");
        else
            this.#descriptor = TfyhData.get_descriptor_template(type);
        if (text_local_name)
            this.#descriptor.text_local_name = text_local_name;
    }

    /**
	 * Get a clone of this data item with a selectable name and always a new
	 * uid.
	 */
    get_clone (nameOfClone)
    {
    	// create the smallest new item
        let clone = new TfyhDataItem(nameOfClone, "none"); 
        clone.#descriptor = TfyhData.clone_descriptor(this.#descriptor);
        return clone;
    }

    /**
	 * Get a descriptor field. text_local_name and text_explanation are localized.
	 */
    get_descriptor_field (name)
    {
    	if (name.startsWith("text_"))
            return _(this.#descriptor[name]);
        return this.#descriptor[name];
    }

    /**
	 * Get a clone of the descriptor.
	 */
    get_descriptor_clone ()
    {
        return TfyhData.clone_descriptor(this.#descriptor);
    }

    /**
	 * Get a clone of the descriptor.
	 */
    get_descriptor_json ()
    {
        return JSON.stringify(this.#descriptor);
    }

    /**
	 * Get the descriptor, but without the default values.
	 */
    get_descriptor_without_defaults ()
    {
        return TfyhData.clone_without_defaults(this.#descriptor);
    }

    /**
	 * Parse and set the descriptor values. Starts always with the defaults for
	 * the data type and overwrites any default by the given descriptor
	 * information. The previous descriptor, if there was one, is dropped.
	 */
    parse_descriptor (descriptorRecord)
    {
        this.#descriptor = TfyhData.parse_descriptor(descriptorRecord);
        if (descriptorRecord.name)
            this.#name = descriptorRecord.name;
        if (descriptorRecord.uid)
            this.#uid = descriptorRecord.uid;
    }

    get_name ()
    {
        return this.#name;
    }

    set_name (name)
    {
        this.#name = name;
    }

    get_uid ()
    {
        return this.#uid;
    }

    set_uid (uid, cfgItem, uidIndex)
    {
        delete uidIndex[this.#uid];
        this.#uid = uid;
        uidIndex[uid] = cfgItem;
    }

    get_value ()
    {
        return this.#descriptor.value_current;
    }

    get_type ()
    {
        return this.#descriptor.value_type;
    }

    get_min ()
    {
        return this.#descriptor.value_min;
    }

    get_max ()
    {
        if (TfyhData.parseAs(this.#descriptor.value_type).localeCompare("string") != 0)
        	return (typeof this.#descriptor.value_max == 'undefined') ? null : this.#descriptor.value_max;
        return (typeof this.#descriptor.value_size == 'undefined') ? null : this.#descriptor.value_size;
    }

    /**
     * Simple setter. Only set provided fields and keeps the rest. 
	 */
    set_descriptor (descriptor, clear_with_empty = true)
    {
    	// clone to unlink arrays and objects.
    	let descriptorClone = TfyhData.clone_descriptor(descriptor);
        for (name in descriptorClone)
            	this.#descriptor[name] = descriptorClone[name];
    }
    
    /**
    * Simply replaces the current descriptor by the new one. $descriptor is taken as is, use a cloen for it.
	 */
    replace_descriptor (descriptor)
    {
        this.descriptor = descriptor;
    }
    
    /**
	 * Get the difference of two configuration branches. The difference is as
	 * well a branch
	 */
    diff (item_to_compare, exclude_current_value, exclude_defaults)
    {
        let diff = this.get_name() + " ?= " + item_to_compare.get_name() + ": ";
        if (strcmp(this.get_name(), item_to_compare.get_name()) != 0)
            diff += _("V0JhTP|Name A not equal to name...") + " ";
        diff += this.diff_descriptor(item_to_compare, exclude_current_value, exclude_defaults);
    }

    /**
	 * Check whether the item_to_compare has a descriptor different from the
	 * own. Defaults are ignored, only those values are compared which are
	 * different from default.
	 */
    diff_descriptor (item_to_compare, exclude_current_value, exclude_defaults)
    {
        let this_to_compare = (exclude_defaults) ? TfyhData.clone_without_defaults(this.#descriptor) : TfyhData.clone_descriptor(
                this.#descriptor);
        let other_to_compare = (exclude_defaults) ? TfyhData.clone_without_defaults(item_to_compare.#descriptor) : TfyhData.clone_descriptor(
                item_to_compare.#descriptor);
        if (exclude_current_value) {
            delete this_to_compare.value_current;
            delete other_to_compare.value_current;
        }
        return TfyhData.diff_arrays(this_to_compare, other_to_compare);
    }

}
