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

function openSubMenu(idSuffix) {
	var submenu_items = document.getElementsByClassName("subMenu" + idSuffix);
	for (var i = 0; i < submenu_items.length; i++) {
		if (submenu_items[i].className.indexOf("w3-show") == -1) {
			submenu_items[i].className += " w3-show";
		} else {
			submenu_items[i].className = submenu_items[i].className.replace(
					" w3-show", "");
		}
	}
}

// Open and close sidebar
function w3_open() {
	document.getElementById("menuSidebar").style.display = "block";
	document.getElementById("menuOverlay").style.display = "block";
}

function w3_close() {
	document.getElementById("menuSidebar").style.display = "none";
	document.getElementById("menuOverlay").style.display = "none";
}

// Open/Close configuration group
function toggleConfigGroup(idToToggle) {
	var configGroups = document.getElementsByClassName("configGroup");
	if (idToToggle.localeCompare("Alle") == 0) {
		for (var i = 0; i < configGroups.length; i++) {
			configGroups[i].className = configGroups[i].className.replace(
					"w3-show", "w3-hide");
		}
	} else {
		var configGroupToShow = document.getElementById("configGroup-"
				+ idToToggle);
		if (configGroupToShow.className.indexOf("w3-hide") >= 0)
			configGroupToShow.className = configGroupToShow.className.replace(
					"w3-hide", "w3-show");
		else
			configGroupToShow.className = configGroupToShow.className.replace(
					"w3-show", "w3-hide");
	}
}
