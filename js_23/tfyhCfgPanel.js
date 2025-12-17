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

class TfyhCfgPanel {
	
	#config = null;
	#modal = null;
	#top = null;
	#mode = "show";
	
    /**
	 * Construct a configuration item. It must always have a parent, except the
	 * root. For the roor set parent = null.
	 */
    constructor (config, modal, mode, top)
    {
    	this.#config = config;
    	this.#modal = modal;
    	this.#top = config.get_by_path(top);
    	this.#mode = mode;
    }

	// run any action on a specific item truiggered by the UI
	#changeItem(action, uid) {
		let item = dilboConfig.getItemForUid(uid);
		if (action.localeCompare("expand") == 0) {
			if (item.state > 0)
				item.state = 2;
		} else if (action.localeCompare("collapse") == 0) {
			if (item.state > 0)
				item.state = 1;
		} else if (action.localeCompare("add") == 0) {
			dilboFormHandler.createChild_do(item);
		} else if (action.localeCompare("edit") == 0) {
			dilboFormHandler.editItem_do(item);
		} else if (action.localeCompare("delete") == 0) {
			let parent = item.get_parent();
			if (parent) {
				let deleted = parent.remove_branch(item.get_name());
				if (deleted)
					dilboConfig.postModify(3, item, ""); // no callback
															// needed
			}
		} else if ((action.localeCompare("moveUp") == 0) || (action.localeCompare("moveDown") == 0)) {
			let parent = item.get_parent();
			let isMoveUp = (action.localeCompare("moveUp") == 0);
			if (parent) {
				let moved = parent.move_child(item, (isMoveUp) ? -1 : 1);
				if (moved)
					dilboConfig.postModify((isMoveUp) ? 4 : 5, item, "");  // no
																			// callback
																			// needed
			}
		} else if (action.localeCompare("show") == 0) {
			dilboModal.showHtml(dilboConfig.itemToTableHTML(item));
		}
	}

	// will bind all panel events triggered for .cfg-item, .cfg-button with a
	// providerId of "tfyhCfgPanel"
	#bindEvents () {
		// for debugging: do not inline statement.
		let cfgelements = $('.cfg-item, .cfg-button'); 
		cfgelements.unbind();
		let that = this;
		cfgelements
				.click(function(e) {
					// for debugging: do not inline statement.
					var thisElement = $(this); 
					var id = thisElement.attr("id");
					if (!id)
						return;
					let action = id.split(/\_/g);
					if (action[0] !== 'tfyhCfgPanel')
						return;
					let uid = action[2];
					let item = that.#config.getItemForUid(uid);
					if (!item)
						return;
					if (e.shiftKey) {
						// TODO: no special functions for shift
					} else if (e.ctrlKey) {
						// TODO: no special functions for ctrl
					} else {
						that.#changeItem(action[1], uid)
						that.refresh();
					}
				});
	}
	
	/**
	 * refresh the configuration manager panel.
	 */
	refresh() {
		$('#tfyhCfg-header').html("<h4>" + _("QES6P2|Configuration editor") + "<h4>");
		let itemsTree = this.#config.getConfigBranchHTML(this.#top, "", true, this.#mode);
		$('#tfyhCfg-branch').html(itemsTree);
		this.#bindEvents();
		$('#tfyhCfg-footer').html("<p><small>&copy; dilbo.org</small></p>");
	}

	// refresh the configuration manager panel.
	displayError(errorMessageHTML) {
		this.#modal.showHtml(errorMessageHTML); 	
	}
}
