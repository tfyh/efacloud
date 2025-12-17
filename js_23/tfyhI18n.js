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
 * A generic translation helper
 */
class TfyhI18n {
		
	// localization
	static i18n = [];
	static #i18nURI = "../i18n/#.lrf";
	static loaded = false;
	
	static parseLrf(lrf) {
		let lines = lrf.split(/\n/g);
		let token = false;
		let text = "";
		for (let line of lines) {
			if (line.indexOf("|") != 6)
				text += "\n" + line;
			else {
				if (token)
					TfyhI18n.i18n[token] = text;
				token = line.substring(0, 6);  
				text = line.substring(7);
			}
		}
	}
	
	/**
	 * load the i18n data. This is asynchronous. Use te callback function to continue.
	 */
	static i18n_init (localeToUse, callback) {
		TfyhI18n.#i18nURI = TfyhI18n.#i18nURI.replace("#", localeToUse)
		// prepare the post request.
		let getRequest = new XMLHttpRequest();
		getRequest.timeout = 10000; // milliseconds
		// provide the callback for a response received
		getRequest.onload = function() {
			TfyhI18n.parseLrf(getRequest.response);
			TfyhI18n.loaded = true;
			callback(true);
		};
		// provide the callback for any error
		getRequest.onerror = function() {
			alert("Fatal error loading applicaion texts for internationalization. Texts will be empty");
			TfyhI18n.loaded = true;
			callback(false);
		};
		// provide the callback for timeout
		getRequest.ontimeout = function() {
			alert("Fatal error loading applicaion texts for internationalization. Texts will be empty");
			TfyhI18n.loaded = true;
			callback(false);
		};
		// send the post request
		getRequest.open('GET', TfyhI18n.#i18nURI);
		getRequest.send();
	}

}

// Call this function to get the proper translation of your texts. Up to 5
// non-transaltable
// arguments can be used within the text.
function _(key, ...args) {
	if (!key || (key.length == 0))
		return "";
	let token = key.substring(0, 6);
	let text = TfyhI18n.i18n[token];
	if (!text) {
		if (!TfyhI18n.loaded)
			text = key + '(...)';
		else
			text = key;
	}
	if (typeof text !== 'string')
		// if the key is a valid identifier of an own prpoerty of the Array
		// prototype it can not be resolved, but will return the Array prototy
		// function or whatever.
		return "[" + key + "]";
	if (! args) return text;
	if (!text)
		alert("Severe i18n error - no text after token");
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
	return text;
};