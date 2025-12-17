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
 * The client applicatiuon configuration, if provided.
 */

const $_max_api_version_jApp = 3;

// the entire block only works, if the configuration varables wer set in the script section of the calling page
// parse json for efa types.
try {

	var $_efaTypesArray = {};
	efaTypes.forEach(function(efaTypesRow) {
		if (!$_efaTypesArray[efaTypesRow["Category"]])
			$_efaTypesArray[efaTypesRow["Category"]] = [];
		$_efaTypesArray[efaTypesRow["Category"]].push({
				Position : parseInt(efaTypesRow["Position"]),
				Type : efaTypesRow["Type"],
				Value : efaTypesRow["Value"]
		});
	});
	// sort alphabetically per Type
	var $_efaTypes = {};
	var $_efaTypesHtml = {};
	for ($_efaTypesCategory in $_efaTypesArray) {
		$_efaTypesArray[$_efaTypesCategory].sort(function(a, b) { return a.Position - b.Position; });
		$_efaTypes[$_efaTypesCategory] = {};
		$_efaTypesHtml[$_efaTypesCategory] = "<ul>";
		$_efaTypesArray[$_efaTypesCategory].forEach(function(efaType) {
			$_efaTypes[$_efaTypesCategory][efaType["Type"]] = efaType["Value"];
			$_efaTypesHtml[$_efaTypesCategory] += "<li>" + [efaType["Type"]] + " = " + efaType["Value"] + "</li>";
		});
		$_efaTypesHtml[$_efaTypesCategory] += "</ul>";
	}

	// parse json for efa project.
	var $_efaProject = {};
	efaProjectCfg.forEach(function(projectCfg) {
		if (!$_efaProject[projectCfg["Type"] + "s"])
			$_efaProject[projectCfg["Type"] + "s"] = [];
		$_efaProject[projectCfg["Type"] + "s"].push(projectCfg);
	});

	// parse json for efa config.
	var $_efaConfig = {};
	efaConfig.forEach(function(efaConfigRow) {
		$_efaConfig[efaConfigRow["Name"]] = (efaConfigRow["Value"]) ? efaConfigRow["Value"] : "";
	});

	var $_name_format_str = $_efaConfig["NameFormat"];
	var $_last_name_first = ($_name_format_str.localeCompare("LASTFIRST") == 0); 
	
	var $_namesTranslated = {};
	var namesTranslatedLines = namesTranslated_php.split(/\n/g);
	namesTranslatedLines.forEach(function(line) {
		var nvp = line.split("=");
		if (!nvp[1] || (nvp[1].length == 0))
			$_namesTranslated[nvp[0]] = nvp[0];
		else
			$_namesTranslated[nvp[0]] = nvp[1];
	});
	
} catch (ignored) {}

