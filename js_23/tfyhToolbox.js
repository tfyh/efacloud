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
 * A generic toolbox for the dilbo_bths program
 */
class TfyhToolbox {
	
	// map to run the html-excape function
	static #escapeHtmlMap = {
	        '&': '&amp;',
	        '<': '&lt;',
	        '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
    };
    
	static base62 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
	static base64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
	static uidExtra = Math.random() * 61;

	/*
	 * MISCELLANEOUS
	 */
	
	/**
	 * Wait function. call with "await TfyhToolbox.sleep(1000) e.g. see
	 * https://stackoverflow.com/questions/951021/what-is-the-javascript-version-of-sleep
	 */
	static sleep (millis) {
		  return new Promise(resolve => setTimeout(resolve, millis));
	}

	/*
	 * ID GENERATION AND RECOGNITION
	 */
	/**
	 * create a random uid value
	 */
	static generateUid (countBytes = 9) {

		var bytes = new Uint8Array(countBytes);
		window.crypto.getRandomValues(bytes);
		let base64 = btoa(String.fromCharCode.apply(null, bytes));
		
		let pos = Math.random() * 61;
		let slashRep = TfyhToolbox.base62.substring(pos, pos + 1);
		pos = Math.random() * 61;
		let plusRep = TfyhToolbox.base62.substring(pos, pos + 1);
		let uid = base64.replace(/\//g, slashRep).replace(/\+/g, plusRep);
		
		return uid;
	}
	
	/**
	 * Check, whether the id complies to the uid format
	 */
	static isUid (id) {
		if (! id)
			return false;
		if (id.length !== 8)
			return false;
		for (let i = 0; i < 8; i++)
			if (TfyhToolbox.base62.indexOf(id.substring(i, i + 1)) < 0)
				return false;
		return true;
	}
	
	/**
	 * Generate a new UUID, see
	 * https://stackoverflow.com/questions/105034/how-do-i-create-a-guid-uuid
	 */
	static generateUUID () {
  	  	return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
  	  		(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
  	  	);
	}

	/**
	 * Check, whether the id complies to the UUID format.
	 */
	static isUUID (id) {
		if (id.length !== 36)
			return false;
		if ((id.charAt(8) != "-") || (id.charAt(13) != "-") || (id.charAt(18) != "-") || (id.charAt(23) != "-"))
			return false;
		let hex = id.replace(/\-/g, "");
		if (isNaN(parseInt(hex.substring(0, 16), 16)))
			return false;
		if (isNaN(parseInt(hex.substring(16), 16)))
			return false;
		return true;
	}

	/**
	 * Convert a microtime (time as float) int a date time string
	 */
	static timef2string(timef, languageCode) {
		if (timef >= TfyhData.forever_seconds)
			return _("jRlk4i|never");
		let date = new Date((timef) ? Math.floor(timef * 1000) : Date.now());
		return TfyhData.format(date, "datetime", languageCode);
	}
	
	
	/*
	 * CSV-SUPPORT
	 */
	
	/**
	 * return the character position of the next line end. Skip line breaks in
	 * between doublequotes. Return length of csvString, if there is no more
	 * line break.
	 */
	static #nextCsvLineEnd (csvString, cLineStart) {
		let lastLinebreak = cLineStart;
		let nextLinebreak = csvString.indexOf('\n', cLineStart);
		let nextDoublequote = csvString.indexOf('"', cLineStart);
		let doublequotesPassed = 0;
		while (((nextDoublequote >= 0) && (nextDoublequote < nextLinebreak))
				|| (doublequotesPassed % 2 == 1)) {
			doublequotesPassed++;
			nextLinebreak = csvString.indexOf('\n', nextDoublequote);
			nextDoublequote = csvString.indexOf('"', nextDoublequote + 1);
		}
		return (nextLinebreak == -1) ? csvString.length : nextLinebreak;
	}

	/**
	 * Split a csv formatted line into an array.
	 */
	static splitCsvRow (line, separator = ";") {
		// split entries by parsing the String, it may contain quoted elements.
		let entries = [];
		if (! line)
			return entries;
		let entryStartPos = 0;

		// TODO: really <=? not <???
		while (entryStartPos <= line.length) {
			// TODO: really <=? not <???

			// trim start if blank chars preced a '"' character
			let firstNonBlankPosition = entryStartPos;
			while (line.charAt(firstNonBlankPosition) == ' ')
				firstNonBlankPosition++;
			if ((firstNonBlankPosition > entryStartPos) && (line.charAt(firstNonBlankPosition) == '"'))
				entryStartPos = firstNonBlankPosition;
			// Check for quotation
			let entryEndPos = entryStartPos;
			let quoted = false;
			// while loop to jump over twin double quotes
			while ((entryEndPos < line.length) && (line.charAt(entryEndPos) == '"')) {
				quoted = true;
				// Put pointer to first character after next doublequote.
				entryEndPos = line.indexOf('"', entryEndPos + 1) + 1;
			}
			entryEndPos = line.indexOf(separator, entryEndPos);
			if (entryEndPos < 0)
				entryEndPos = line.length;
			let entry = line.substring(entryStartPos, entryEndPos);
			if (quoted) {
				// remove opening and closing doublequotes.
				let entryToParse = entry.substring(1, entry.length - 1);
				// replace all inner twin doublequotes by single doublequotes
				let nextSnippetStart = 0;
				let nextDoubleQuote = entryToParse.indexOf('""',
						nextSnippetStart);
				entry = "";
				while (nextDoubleQuote >= 0) {
					// add the segment to the next twin doublequote and the
					// first doublequote in it
					entry += entryToParse.substring(nextSnippetStart,
							nextDoubleQuote + 1);
					nextSnippetStart = nextDoubleQuote + 2;
					// continue search after the second of the twin doublequotes
					nextDoubleQuote = entryToParse.indexOf('""',
							nextSnippetStart);
				}
				// add last segment (or full entry, if there are no twin
				// doublequotes
				entry += entryToParse.substring(nextSnippetStart);
			}
			entries.push(entry)
			entryStartPos = entryEndPos + 1;
		}
		return entries;
	}

	/**
	 * Read a csv String (; and " formatted) into an array[rows][columns]. It is
	 * not checked, whether all rows have the same column width. This is plain
	 * text parsing.
	 */
	static csvToArray (csvstring) {
		if (!csvstring)
			return [];
		let table = [];
		let cLineStart = 0;
		let cLineEnd = TfyhToolbox.#nextCsvLineEnd(csvstring,
				cLineStart);
		while (cLineEnd > cLineStart) {
			let line = csvstring.substring(cLineStart, cLineEnd);
			let entries = TfyhToolbox.splitCsvRow(line);
			table.push(entries);
			cLineStart = cLineEnd + 1;
			cLineEnd = TfyhToolbox.#nextCsvLineEnd(csvstring,
					cLineStart);
		}
		return table;
	}

	/**
	 * Read a csv String (; and " formatted) into an associative array, where
	 * the keys are the entries of the first line. All rows must have the same
	 * column width. However, this is not checked.
	 */
	static csvToObject (csvstring) {
		if (!csvstring)
			return [];
		let table = TfyhToolbox.csvToArray(csvstring);
		let list = [];
		let r = 0;
		let w = 0;
		let header = [];
		for (let row of table) {
			if (r == 0) {
				w = row.length;
				header = row;
			} else {
				let listRow = {};
				let c = 0;
				for(let entry of row) {
					listRow[header[c]] = entry;
					c++;
				}
				list.push(listRow);
			}
			r++;
		}
		return list;
	}

	/**
	 * encode a single entry to be written to the csv file.
	 */
	static encodeCsvEntry (entry) {
		// return numbers unchanged
		if (!isNaN(entry))
			return entry;
		// return entry unchanged, if there is no need for quotation.
		if ((entry.indexOf(";") < 0) && (entry.indexOf("\n") < 0)
				&& (entry.indexOf("\"") < 0))
			return entry;
		// add inner quotes and outer quotes for all other.
		let ret = entry.replace(/"/g, "\"\"");
		return "\"" + ret + "\"";
	}

	/**
	 * Write an array to a csv String. table must have an object table.rows[] of
	 * which each row holds an array. If (associative) the keys are the first
	 * row become the first csv-line column headers, else the first csv-line is
	 * written as provided in the first of rows.
	 */
	static encodeCsvTable (table, associative) {
		let csvstring = "";
		if (associative) {
			let keys = []
			for (let key in table.rows[0]) {
				csvstring += TfyhToolbox.encodeCsvEntry(key) + ";";
				keys.push(key);
			}
			csvstring = csvstring.substring(0, csvstring.length - 1) + "\n";
			for(let row of table.rows) {
				for(key of keys) {
					csvstring += TfyhToolbox.encodeCsvEntry(row[key]) + ";";
				}
				csvstring = csvstring.substring(0, csvstring.length - 1) + "\n";
			}
		} else {
			for(row of table.rows) {
				for(entry of row) {
					csvstring += TfyhToolbox.encodeCsvEntry(entry) + ";";
				}
				csvstring = csvstring.substring(0, csvstring.length - 1) + "\n";
			}
		}
		csvstring = csvstring.substring(0, csvstring.length - 1);
		return csvstring;
	}
	
	/*
	 * STRING TRANSCODING AND CHECKS
	 */

	/**
	 * see
	 * https://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
	 */
	static escapeHtml (text) {
		// do nothing, if the input is undefined or a number
		if (!text || !isNaN(text))
			return text;
		// replace the html reserved characters
        return text.replace(/[&<>"']/g, function(m) { 
        	return TfyhToolbox.#escapeHtmlMap[m]; 
        });
	}

    /**
	 * Add leading zeros before the number to obtain the 'len' expected. If
	 * n.toString.length >= len the number is converted to a String and not
	 * changed.
	 */
	static numToText (n, len) {
    	var s = "" + n;
    	while (s.length < len)
    		s = "0" + s;
    	return s;
    }
    
    /**
	 * Check, whether the pwd complies to password rules.
	 * 
	 * @param String
	 *            pwd password to be checked
	 * @return String list of errors found. Returns empty String, if no errors
	 *         were found.
	 */
	static checkPassword (pwd)  {
		let errors = "";
        if (pwd.length < 8) 
            errors += "Das Kennwort muss mindestens 8 Zeichen lang sein. ";
        let numbers = (/\d/.test(pwd)) ? 1 : 0;
        let lowercase = (pwd.toUpperCase() == pwd) ? 0 : 1;
        let uppercase = (pwd.toLowerCase() == pwd) ? 0 : 1;
        // Four ASCII blocks: !"#$%&'*+,-./ ___ :;<=>?@ ___ [\]^_` ___ {|}~
        let specialchars = (pwd.match(/[!-\/]+/g) || pwd.match(/[:-@]+/g) || pwd.match(/[\[-`]+/g) ||
        		pwd.match(/[{-~]+/g)) ? 1 : 0;
        if ((numbers + lowercase + uppercase + specialchars) < 3)
            errors += "Im Kennwort müssen Zeichen aus drei Gruppen der folgenden vier Gruppen " +
                     "enthalten sein: Ziffern, Kleinbuchstaben, Großbuchstaben, Sonderzeichen. " +
                     "Zulässige Sonderzeichen sind !\"#$%&'*+,-./:;<=>?@[\]^_`{|}~";
        return errors;
    }
	
	/**
	 * mask a String of 42 or less ASCII characters to obfuscate.
	 */
	static mask(plain, scrambler) {
		let base64 = btoa(plain);
		while (base64.endsWith("="))
			base64 = base64.substring(0, base64.length - 1);
		let offset = Math.random() * 50;
		let padded = scrambler.substring(offset, offset + 4) 
					+ base64 + scrambler.substring(offset + 4, offset + 8);
		let masked = "";
		let i = 0;
		while (i < padded.length) {
			let p = TfyhToolbox.base64.indexOf(padded.substring(i, i + 1));
			masked += scrambler.substring(p, p + 1);
			i++;
		}
		return masked;
	}

	/**
	 * unmask a masked String of 42 or less ASCII characters.
	 */
	static unmask(masked, scrambler) {
		let base64 = "";
		let i = 4;
		while (i < masked.length - 4) {
			let p = scrambler.indexOf(masked.substring(i, i + 1));
			base64 += TfyhToolbox.base64.substring(p, p + 1);
			i++;
		}
		while ((base64.length % 4) != 0)
			base64 += "=";
		return atob(base64);
	}

	/*
	 * BASE64 TRANSCODING TO UTF-8
	 */

    /**
	 * Source (adapted): utf.js - UTF-8 <=> UTF-16 conversion.
	 * http://www.onicos.com/staff/iz/amuse/javascript/expert/utf.txt Copyright
	 * (C) 1999 Masanao Izumo <iz@onicos.co.jp> Version: 1.0 LastModified: Dec
	 * 25 1999 This library is free. You can redistribute it and/or modify it.
	 * window.atob will exit with an error when using UTF-8 encoding,
	 */
	static base64apiToUtf8 (base64String) {
        var uint8Array = TfyhToolbox.#base64apiToUint8Array(base64String);
        let c, char2, char3;
        let out = "";
        let len = uint8Array.length;
        let i = 0;
        while (i < len) {
        	c = uint8Array[i++];
        	switch (c >> 4)
        	{ 
        	case 0: case 1: case 2: case 3: case 4: case 5: case 6: case 7:
        		// 0xxxxxxx
        		out += String.fromCharCode(c);
        		break;
        	case 12: case 13:
        		// 110x xxxx 10xx xxxx
        		char2 = uint8Array[i++];
        		out += String.fromCharCode(((c & 0x1F) << 6) | (char2 & 0x3F));
        		break;
        	case 14:
        		// 1110 xxxx 10xx xxxx 10xx xxxx
        		char2 = uint8Array[i++];
        		char3 = uint8Array[i++];
        		out += String.fromCharCode(((c & 0x0F) << 12) | ((char2 & 0x3F) << 6) | ((char3 & 0x3F) << 0));
        		break;
        	}
        }    
        return out;
    }

    /**
	 * base64 String to byte decoding. See Daniel Guerrero:
	 * http://blog.danguer.com/2011/10/24/base64-binary-decoding-in-javascript/
	 * Adapted to use *-_ istead of +/=, removed superfluous parts. Corrected
	 * dangling end byte error.
	 */
	static #base64apiToUint8Array (input) {
		// remove all irrelevant characters ('\n', ' ') asf.
		// Note: this is the API-type base 64 using *-_ instead of +/=
		input = input.replace(/[^A-Za-z0-9\*\-_]/g, "");
		let test;
		if (input.indexOf("*") > 0)
			test = input.indexOf("*");
		// calculate output size
		let bytes = input.length / 4 * 3;
		// remove padding ('_' instead of '=')
		while (input.substring(input.length - 1).localeCompare("_") == 0) {
			input = input.substring(0, input.length - 1);
			bytes --;
		}
		// prepare decoding
		let uarray = new Uint8Array(bytes);
		let chr1, chr2, chr3;
		let enc1, enc2, enc3, enc4;
		let i = 0;
		let j = 0;
		// Note: this is the API-type base 64 using *-_ instead of +/= , see
		// end.
		let keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789*-_";
		// decode
		for (i=0; i<bytes; i+=3) {	
			// get the 3 octects in 4 ascii chars
			enc1 = keyStr.indexOf(input.charAt(j++));
			enc2 = keyStr.indexOf(input.charAt(j++));
			enc3 = keyStr.indexOf(input.charAt(j++));
			enc4 = keyStr.indexOf(input.charAt(j++));
			if ((enc1 < 0) || (enc2 < 0) || (enc3 < 0) || (enc3 < 0))
				enc1 = 0;
			chr1 = (enc1 << 2) | (enc2 >> 4);
			chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
			chr3 = ((enc3 & 3) << 6) | enc4;
			uarray[i] = chr1;			
			if (enc3 != 64) uarray[i+1] = chr2;
			if (enc4 != 64) uarray[i+2] = chr3;
		}
		return uarray;	
	}
    
}
