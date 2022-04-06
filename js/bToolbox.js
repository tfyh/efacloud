/**
 * A generic toolbox for the efacloud_bths program
 */
var bToolbox = {
		
	// localization
	_i18n : [],
	
	// map to run the html-excape function
    _escapeHtmlMap : {
	        '&': '&amp;',
	        '<': '&lt;',
	        '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
    },

	i18n_init:function(localeToUse) {
		// prepare the post request.
		var getRequest = new XMLHttpRequest();
		getRequest.timeout = $_apiTimeoutMillis;
		// provide the callback for a response received
		getRequest.onload = function() {
			var allTexts = bToolbox.readCsv(getRequest.response, false);
			var cl = 0;
			for (var c = 1; c < allTexts[0].length; c++)
				if (allTexts[0][c].localeCompare(localeToUse) == 0)
					cl = c;
			if (cl == 0) cl = 1;  // default language
			var header = true;
			allTexts.forEach(function(row) {
				if (!header)
					bToolbox._i18n[row[0]] = row[cl];
				header = false;
			});
		};
		// provide the callback for any error
		getRequest.onerror = function() {
			alert("Fatal error loading applicaion texts for internationalization. Textx will be empty");
		};
		// provide the callback for timeout
		getRequest.ontimeout = function() {
			alert("Fatal error loading applicaion texts for internationalization. Textx will be empty");
		};
		// send the post request
		getRequest.open('GET', $_efacloudI18nURI);
		getRequest.send();
	},

	/**
	 * create a random ecrid value
	 */
	generateEcrid : function() {
		var bytes = new Uint8Array(9);
		window.crypto.getRandomValues(bytes);
		let base64 = btoa(String.fromCharCode.apply(null, bytes));
		return base64.replace(/\//g, "-").replace(/\+/g, "*");
	},
	
	/**
	 * return the character position of the next line end. Skip line breaks in
	 * between doublequotes. Return length of csvString, if there is no more
	 * line break.
	 */
	_nextCsvLineEnd : function(csvString, cLineStart) {
		var lastLinebreak = cLineStart;
		var nextLinebreak = csvString.indexOf('\n', cLineStart);
		var nextDoublequote = csvString.indexOf('"', cLineStart);
		var doublequotesPassed = 0;
		while (((nextDoublequote >= 0) && (nextDoublequote < nextLinebreak))
				|| (doublequotesPassed % 2 == 1)) {
			doublequotesPassed++;
			nextLinebreak = csvString.indexOf('\n', nextDoublequote);
			nextDoublequote = csvString.indexOf('"', nextDoublequote + 1);
		}
		return (nextLinebreak == -1) ? csvString.length : nextLinebreak;
	},

	/**
	 * Wait function. call with "await bToolbox.sleep(1000) e.g. see
	 * https://stackoverflow.com/questions/951021/what-is-the-javascript-version-of-sleep
	 */
	sleep:function(ms) {
		  return new Promise(resolve => setTimeout(resolve, ms));
	},

	/**
	 * Split a csv formatted line into an array.
	 */
	splitCsvRow : function(line) {
		// split entries by parsing the String, it may contain quoted elements.
		var entries = [];
		var entryStartPos = 0;
		while (entryStartPos <= line.length) {
			var entryEndPos = entryStartPos;
			var quoted = false;
			// Check for quotation
			while (line.charAt(entryEndPos) == '"') {
				quoted = true;
				// Put pointer to first character after next doublequote.
				entryEndPos = line.indexOf('"', entryEndPos + 1) + 1;
			}
			entryEndPos = line.indexOf(';', entryEndPos);
			if (entryEndPos < 0)
				entryEndPos = line.length;
			var entry = line.substring(entryStartPos, entryEndPos);
			if (quoted) {
				// remove opening and closing doublequotes.
				entryToParse = entry.substring(1, entry.length - 1);
				// replace all inner twin doublequotes by single doublequotes
				var nextSnippetStart = 0;
				var nextDoubleQuote = entryToParse.indexOf('""',
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
	},

	/**
	 * Read a csv String (; and " formatted) into an array[rows][columns]. It is
	 * not checked, whether all rows have the same column width. This is plain
	 * text parsing.
	 */
	readCsv : function(csvstring) {
		if (!csvstring)
			return [];
		var table = [];
		var cLineStart = 0;
		var cLineEnd = this._nextCsvLineEnd(csvstring,
				cLineStart);
		while (cLineEnd > cLineStart) {
			let line = csvstring.substring(cLineStart, cLineEnd);
			var entries = this.splitCsvRow(line);
			table.push(entries);
			cLineStart = cLineEnd + 1;
			cLineEnd = this._nextCsvLineEnd(csvstring,
					cLineStart);
		}
		return table;
	},

	/**
	 * Read a csv String (; and " formatted) into an associative array, where
	 * the keys are the entries of the first line. All rows must have the same
	 * column width. However, this is not checked.
	 */
	readCsvList : function(csvstring) {
		if (!csvstring)
			return [];
		table = this.readCsv(csvstring);
		var list = [];
		r = 0;
		w = 0;
		header = [];
		table.forEach(function(row) {
			if (r == 0) {
				w = row.length;
				header = row;
			} else {
				listRow = {};
				c = 0;
				row.forEach(function(entry) {
					listRow[header[c]] = entry;
					c++;
				});
				list.push(listRow);
			}
			r++;
		});
		return list;
	},

	/**
	 * encode a single entry to be written to the csv file.
	 */
	encodeCsvEntry : function(entry) {
		// return numbers unchanged
		if (!isNaN(entry))
			return entry;
		// return entry unchanged, if there is no need for quotation.
		if ((entry.indexOf(";") < 0) && (entry.indexOf("\n") < 0)
				&& (entry.indexOf("\"") < 0))
			return entry;
		// add inner quotes and outer quotes for all other.
		var ret = entry.replace(/"/g, "\"\"");
		return "\"" + ret + "\"";
	},

	/**
	 * Write an array to a csv String. table must have an object table.rows[] of
	 * which each row holds an array. If (associative) the keys are the first
	 * row become the first csv-line column headers, else the first csv-line is
	 * written as provided in the first of rows.
	 */
	encodeCsvTable : function(table, associative) {
		csvstring = "";
		if (associative) {
			var keys = []
			for (var key in table.rows[0]) {
				csvstring += bToolbox.encodeCsvEntry(key) + ";";
				keys.push(key);
			}
			csvstring = csvstring.substring(0, csvstring.length - 1) + "\n";
			table.rows.forEach(function(row) {
				keys.forEach(function(key) {
					csvstring += bToolbox.encodeCsvEntry(row[key]) + ";";
				});
				csvstring = csvstring.substring(0, csvstring.length - 1) + "\n";
			});
		} else {
			table.rows.forEach(function(row) {
				row.forEach(function(entry) {
					csvstring += bToolbox.encodeCsvEntry(entry) + ";";
				});
				csvstring = csvstring.substring(0, csvstring.length - 1) + "\n";
			});
		}
		csvstring = csvstring.substring(0, csvstring.length - 1);
		return csvstring;
	},

	/**
	 * see
	 * https://stackoverflow.com/questions/1586330/access-get-directly-from-javascript
	 */
	getGetparams : function() {
		$_GET = [];
		var parts = window.location.search.substr(1).split("&");
		$_GET = new Array(parts.length);
		for (var i = 0; i < parts.length; i++) {
			if (parts[i].indexOf("=") > 0) {
				var temp = parts[i].split("=");
				$_GET[decodeURIComponent(temp[0])] = decodeURIComponent(temp[1]
						.replace(/\+/g, '%20'));
			}
		}
	},
	
	/**
	 * see
	 * https://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
	 */
	escapeHtml:function(text) {
		// do nothing, if the input is unduefined or a number
		if (!text || !isNaN(text))
			return text;
		// replace the html reserved characters
        return text.replace(/[&<>"']/g, function(m) { 
        	return bToolbox._escapeHtmlMap[m]; 
        });
	},

	/**
	 * Check, whether the id is (probably) a GUID.
	 */
	isGUID:function(id) {
		var isGUID = (id.length == 36);
		var dash1 = id.charAt(8);
		var dash2 = id.charAt(13);
		var dash3 = id.charAt(18);
		var dash4 = id.charAt(23);
		return (isGUID && (dash1 == '-') && (dash2 == '-') && (dash3 == '-') && (dash4 == '-'));
	},
	
    /**
	 * return the date for an eFa type date String (UK or German)
	 */
    parseEfaDate:function(date, time) {
        var split_time = time.split(/:/g);
        var split_date = date.split(/\\./g);
        if (split_date.length < 3) {
        	split_date = date.split(/-/g);
        	var dateParsed = new Date(parseInt(split_date[0]), parseInt(split_date[1]) - 1, parseInt(split_date[2]), 
        			parseInt(split_time[0]), parseInt(split_time[1]));
        } else
        	var dateParsed = new Date(parseInt(split_date[2]), parseInt(split_date[1]) - 1, parseInt(split_date[0]), 
    			parseInt(split_time[0]), parseInt(split_time[1]));
    	return dateParsed;
    },
    
    /**
	 * Add leading zeros before the number to obtain the 'len' expected. If
	 * n.toString.length >= len the number is converted to a String and not
	 * changed.
	 */
    numToText : function(n, len) {
    	var s = "" + n;
    	while (s.length < len)
    		s = "0" + s;
    	return s;
    },
    
    /**
	 * return the date for an eFa type time String
	 */
    format_efa_time:function(time) {
        var dateParsed = this.parseEfaDate("1970-01-01", time);
        return this.numToText(dateParsed.getHours(), 2) + ":" + this.numToText(dateParsed.getMinutes(), 2) 
          			+ ":" + this.numToText(dateParsed.getSeconds(), 2); 
    },
    
    /**
	 * swap date format "YYY-MM-DD" to "TT.MM.JJJJ"
	 */
    dateISO2DE:function(dateUK) {
    	if (!dateUK || (dateUK.length < 2))
    		return "";
        var split_date = dateUK.split(/-/g);
        return split_date[2] + "." + split_date[1] + "." + split_date[0];
    },
    
    /**
	 * get date of now
	 */
    dateNow:function() {
    	var now = new Date();
    	var day = "" + now.getDate();
    	if (day.length < 2)
    		day = "0" + day;
    	var month = "" + (now.getMonth() + 1);
    	if (month.length < 2)
    		month = "0" + month;
        return now.getFullYear() + "-" + month + "-" + day;
    },
    
    /**
	 * get date of now
	 */
    timeNow:function() {
    	var now = new Date();
    	var hours = "" + now.getHours();
    	if (hours.length < 2)
    		hours = "0" + hours;
    	var minutes = "" + now.getMinutes();
    	if (minutes.length < 2)
    		minutes = "0" + minutes;
        return hours + ":" + minutes;
    },
    
    /**
	 * Check, whether the date_string represents a valid date.
	 * 
	 * @param String
	 *            date_string string to be checked
	 * @return a formatted date string "Y-m-d" (e.g. 2018-04-20), if
	 *         $date_string is a date between 01.01.0100 and 31.12.2999, else
	 *         false. Note: If the year value is < 100 it will be adjusted to a
	 *         four digit year using this year and the 99 preceding to complete.
	 */
    checkAndFormatDate:function(date_string) {
        // return empty String for a null date.
        if (date_string.length < 3)
            return "";
        if (!(typeof date_string === 'string' || date_string instanceof String))
        	return;
        // parse date.
        var unixTimestamp = Date.parse(date_string);
        var d
        if (isNaN(unixTimestamp)) {
            var split_date = date_string.split(/\./g);
            d = new Date(parseInt(split_date[2]), parseInt(split_date[1]), parseInt(split_date[0]));
        } else d = new Date(unixTimestamp);
        // return parsing result
        return d.toISOString().substring(0, 10);
    },

    /**
	 * Check, whether the pwd complies to password rules.
	 * 
	 * @param String
	 *            pwd password to be checked
	 * @return String list of errors found. Returns empty String, if no errors
	 *         were found.
	 */
    checkPassword:function(pwd)  {
        errors = "";
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
    },

    /**
	 * Source (adapted): utf.js - UTF-8 <=> UTF-16 conversion.
	 * http://www.onicos.com/staff/iz/amuse/javascript/expert/utf.txt Copyright
	 * (C) 1999 Masanao Izumo <iz@onicos.co.jp> Version: 1.0 LastModified: Dec
	 * 25 1999 This library is free. You can redistribute it and/or modify it.
	 */
    efaBase64toUtf8 : function (base64String) {
        var uint8Array = this._efaBase64ToUint8Array(base64String);
        var out, i, len, c;
        var char2, char3;
        out = "";
        len = uint8Array.length;
        i = 0;
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
    },

    /**
	 * base64 String to byte decoding. See Daniel Guerrero:
	 * http://blog.danguer.com/2011/10/24/base64-binary-decoding-in-javascript/
	 * Adapted, removed superfluous parts. Corrected dangling end byte error.
	 */
	_efaBase64ToUint8Array : function (input) {
		// remove all irrelevant characters ('\n', ' ') asf.
		// Note: this is the efa-type base 64 using *-_ instead of +/=
		input = input.replace(/[^A-Za-z0-9\*\-_]/g, "");
		var test
		if (input.indexOf("7xoc") > 0)
			test = input.indexOf("7xoc");
		// calculate output size and remove padding.
		var bytes = input.length / 4 * 3;
		while (input.substring(input.length - 1).localeCompare("=") == 0) {
			input = input.substring(0, input.length - 1);
			bytes --;
		}
		// prepare decoding
		var uarray = new Uint8Array(bytes);
		var chr1, chr2, chr3;
		var enc1, enc2, enc3, enc4;
		var i = 0;
		var j = 0;
		var _keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
		var threebytecharfound = false;
		// decode
		for (i=0; i<bytes; i+=3) {	
			// get the 3 octects in 4 ascii chars
			var enc = input.charAt(j);
			enc1 = _keyStr.indexOf(input.charAt(j++));
			enc += input.charAt(j);
			enc2 = _keyStr.indexOf(input.charAt(j++));
			enc += input.charAt(j);
			enc3 = _keyStr.indexOf(input.charAt(j++));
			enc += input.charAt(j);
			enc4 = _keyStr.indexOf(input.charAt(j++));
			chr1 = (enc1 << 2) | (enc2 >> 4);
			chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
			chr3 = ((enc3 & 3) << 6) | enc4;
			if ((chr1 >= 224) || (chr1 >= 224) || (chr1 >= 224))
				threebytecharfound = true;
			uarray[i] = chr1;			
			if (enc3 != 64) uarray[i+1] = chr2;
			if (enc4 != 64) uarray[i+2] = chr3;
		}
		return uarray;	
	}
    
};

// initialize interationalization.
bToolbox.i18n_init($_locale);

// Call this function to get the proper translation of your texts. Up to 5
// non-transaltable
// arguments can be used within the text.
function _(key, ...args) {
	var text = bToolbox._i18n[key];
	if (!text) text = "[" + key + "]";
	if (args) return text;
	if (args.length > 0)
		text = text.replace(/$1/g, arg[0]);
	if (args.length > 1)
		text = text.replace(/$2/g, arg[1]);
	if (args.length > 2)
		text = text.replace(/$3/g, arg[2]);
	if (args.length > 3)
		text = text.replace(/$4/g, arg[3]);
	if (args.length > 4)
		text = text.replace(/$5/g, arg[4]);
	return text;
};