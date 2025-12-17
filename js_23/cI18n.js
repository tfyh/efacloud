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
 * The mulitplanguage support.
 */
var i18n = {
	// localization
	languageResource : {},
	
    // load the language resource file into the i18n array, call it from $(document).ready().
	init:function(onI18nLoaded) {
		$.get( "../i18n/" + $_languageCode + ".lrf", function( lrf ) {
			i18n.languageResource = {};
			if (lrf) {
				var lines = lrf.split(/\n/g);
				var thisLine = "";
				for (l in lines) {
					if (lines[l].startsWith("|"))
						thisLine += "\n" + lines[l].substring(1);
					else {
						if (thisLine) 
							i18n.languageResource[thisLine.substring(0, 6)] = thisLine.substring(7);
						thisLine = lines[l];
					}
				}
			}
			if (onI18nLoaded)
				onI18nLoaded();
		});
	}
}

// Call this function to get the proper translation of your texts. Up to 5
// non-transaltable arguments can be used within the text.
function _(i18nResourceId, ...args) {
	if (i18nResourceId.indexOf("|") != 6)
		return i18nResourceId;
	var token6 = i18nResourceId.substring(0, 6);
	var text = i18n.languageResource[token6];
	if (!text) 
		return "[" + i18nResourceId + "]";
	if (! args) return text;
	if (args.length > 0)
		text = text.replace(/%1/g, args[0]);
	if (args.length > 1)
		text = text.replace(/%2/g, args[1]);
	if (args.length > 2)
		text = text.replace(/%3/g, args[2]);
	if (args.length > 3)
		text = text.replace(/%4/g, args[3]);
	if (args.length > 4)
		text = text.replace(/%5/g, args[4]);
	if (args.length > 5)
		text = text.replace(/%6/g, args[5]);
	if (args.length > 6)
		text = text.replace(/%7/g, args[6]);
	return text;
};
