/**
 * a set of global variables to be used within the application.
 */

// the get parameters of the URL selected
var $_GET = [];
// the URL selected
var $_URL = "";

let cookie = document.cookie;
var elements = cookie.split(/;/g);
var $_COOKIE = {};
for (element of elements) 
	$_COOKIE[element.trim().split("=")[0]] = element.trim().split("=")[1];
var $_efaCloudUserID = $_COOKIE["tfyhUserID"];
var $_efaCloudSessionID = $_COOKIE["tfyhSessionID"];
var $_allowedMenuItems = [];
$(".menuitem").each(function() {
	var thisElement = $(this);   // for debugging: do not inline statement.
	var id = thisElement.attr("id");
	if (id && (id.substring(0, 3).localeCompare("do-") == 0))
		$_allowedMenuItems.push(id.substring(3));
});


// interface URL
const $_clientInterfaceURI = "../api/posttx.php";
const $_efacloudI18nURI = "../resources/efacloud_i18n.csv";

// All 0.2 secs the transactions queue is checked
const $_apiPendingMillis = 300;
// All 0.5 secs the user interface is refreshed
const $_uiRefreshMillis = 500;
// After 30 seconds a transaction is aborted and put to the retry queue
const $_apiTimeoutMillis = 30000;
// All 10 mins a retry is triggered after connection failure
const $_apiRetryMillis = 600000;

// Once per day a refresh of all lists is triggered.
const $_globalRefreshAt = "03:00:00";
const $_oneDayMillis = 24 * 3600 * 1000;

// other write access check period (seconds)
var $_synch_check_period = 90;
var $_last_synch_check = parseInt(Date.now() / 1000);
// download synchronisation period (seconds)
var $_synch_period = 1800;
var $_last_synch = parseInt(Date.now() / 1000); // the first full download will
												// be triggered by the nop
												// response
// add the server welcome message
var $_server_welcome_message = "nicht verbunden.";


// server response translation keys.
const $_serverResponseTexts = {
	300 : "Transaction completed.",
	301 : "Container parsed. User yet to be verified.", 
	// 301: server side internal code
	303 : "Transaction completed and data key mismatch detected.",
	400 : "XHTTPrequest Error.", 
	// 400: client side generated error, javascript version only
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
    503 : "No server response in returned transaction response container", 
    // 503 ff.: for future use
    504 : "Transaction response container failed",
    505 : "Server response empty",
    506 : "Internet connection aborted",
    507 : "Could not decode server response"
};

const $_logLimit = 3;

const $_locale = "de-DE";

const $_dateFormatDayShort = {
	day : "2-digit",
	month : "2-digit",
	year : "numeric"
};

var $_initTime = new Date();
var current_logbook_element = $('.current-logbook')[0];
var current_logbook = $(current_logbook_element).attr('id');
var $_logbookname = (current_logbook) ? current_logbook : "" + $_initTime.getFullYear();

var $_version;
jQuery.get('../public/version', function(data) {
	$_version = data;
});

var $_copyright;
jQuery.get('../public/copyright', function(data) {
	$_copyright = data;
});
