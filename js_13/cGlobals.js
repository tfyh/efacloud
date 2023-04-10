/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * a set of global variables to be used within the application.
 */

// the get parameters of the URL selected
var $_GET = [];
// the URL selected
var $_URL = "";

// Access
// ======

// User und session ID are transferred via a cookie, not within the document, for security reasons 
let docCookie = document.cookie;
var elements = docCookie.split(/;/g);
var $_COOKIE = {};
for (element of elements) 
	$_COOKIE[element.trim().split("=")[0]] = element.trim().split("=")[1];
var $_apiUserID = $_COOKIE["tfyhUserID"];
var $_apiSessionID = $_COOKIE["tfyhSessionID"];

// Create menu based access logic.
var $_allowedMenuItems = [];
$(".menuitem").each(function() {
	var thisElement = $(this);   // for debugging: do not inline statement.
	var id = thisElement.attr("id");
	if (id && (id.substring(0, 3).localeCompare("do-") == 0))
		$_allowedMenuItems.push(id.substring(3));
});

// API
// ===

// timing prameters
const $_apiPostURI = "../api/posttx.php";
const $_apiPendingMillis = 300;
const $_apiTimeoutMillis = 30000;
const $_apiRetryMillis = 600000;
//server welcome message
var $_server_welcome_message = "nicht verbunden.";
var $_max_api_version_server = 1;

// Synchronisation defaults
// other write access check period (seconds)
var $_synch_check_period = 90;
var $_last_synch_check = parseInt(Date.now() / 1000);
// download synchronisation period (seconds)
var $_synch_period = 1800;
// the first full download will be triggered by the nop response
var $_last_synch = parseInt(Date.now() / 1000); 
//Once per day a refresh of all lists is triggered.
const $_globalRefreshAt = "03:00:00";

// Server result codes. Shall be identical to the efacloud_api.php $result_messages definition
const $_resultMessages = {
	300 : "Transaction completed.",
	301 : "Container parsed. User yet to be verified.", 
	302 : "API version of container not supported. Maximum API level exceeded.", 
	303 : "Transaction completed and data key mismatch detected.",
	304 : "Transaction forbidden.",
	400 : "XHTTPrequest Error.", 
	401 : "Syntax error.",
	402 : "Unknown client.",
	403 : "Authentication failed.",
	404 : "Server side busy.",
	405 : "Wrong transaction ID.", // (client side generated error)
	406 : "Overload detected.",  
	407 : "No data base connection.",
	500 : "Internal server error.",  // this is never set by the application.
	501 : "Transaction invalid.",
	502 : "Transaction failed.",
    503 : "Transaction missing in container.", 
    504 : "Transaction response container failed.",
    505 : "Server response empty.",
    506 : "Internet connection aborted.",
    507 : "Could not decode server response."
};

// User Interface
// ==============

const $_i18nURI = "../resources/i18n.csv";
//All 0.5 secs the user interface is refreshed
const $_uiRefreshMillis = 500;
//Maximum number of displayed autocomplete options
const $_countDisplayMax = 5;

const $_oneDayMillis = 24 * 3600 * 1000;
const $_logLimit = 3;

// locale settings - see also efaConfig.js!
const $_locale = "de-DE";
const $_dateFormatDayShort = {
	day : "2-digit",
	month : "2-digit",
	year : "numeric"
};
var $_initTime = new Date();
var $_currentYear = $_initTime.getUTCFullYear();

$_languageCode = "de";
try {
	$_languageCode = php_languageCode;
} catch (ignored) {}

// Copyright & Version
// ===================

var $_version;
jQuery.get('../public/version', function(data) {
	$_version = data;
});
var $_copyright;
jQuery.get('../public/copyright', function(data) {
	$_copyright = data;
});
