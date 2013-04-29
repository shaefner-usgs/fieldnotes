
// usage: log('inside coolFunc', this, arguments);
// paulirish.com/2009/log-a-lightweight-wrapper-for-consolelog/
window.log = function(){
	log.history = log.history || []; // store logs to an array for reference
	log.history.push(arguments);
	if(this.console) {
		arguments.callee = arguments.callee.caller;
		var newarr = [].slice.call(arguments);
		(typeof console.log === 'object' ? log.apply.call(console.log, console, newarr) : console.log.apply(console, newarr));
	}
};

// make it safe to use console.log always
(function(b){function c(){}for(var d="assert,clear,count,debug,dir,dirxml,error,exception,firebug,group,groupCollapsed,groupEnd,info,log,memoryProfile,memoryProfileEnd,profile,profileEnd,table,time,timeEnd,timeStamp,trace,warn".split(","),a;a=d.pop();){b[a]=b[a]||c}})((function(){try
{console.log();return window.console;}catch(err){return window.console={};}})());


// place any jQuery/helper plugins in here, instead of separate, slower script files.

String.prototype.capitalize = function() {
	return this.charAt(0).toUpperCase() + this.substring(1);
};

String.prototype.pluralize = function(count, plural) {
	if (plural === undefined) {
		plural = this + 's';
	}
	return (count === 1 ? this : plural);
};

// parse url params from a supplied string or from browser's URL; returns an object containing name/value pairs
var getUrlParams = function (str) {
	var match,
		pl = /\+/g, // Regex for replacing addition symbol with a space
		search = /([^&=]+)=?([^&]*)/g,
		decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
		query = str ? str : window.location.search.substring(1),
		urlParams = {};
	while (match = search.exec(query)) {
		urlParams[decode(match[1])] = decode(match[2]);
	}
	return urlParams;
};

// get encrypted string from passed URL param that contains info from SpotOn; returns the decrypted string
var decryptSpotonInfo = function (param) {
	var match,
		search = /([^&=]+)=?([^&]*)/g,
		query = window.location.search.substring(1),
		hashObj = new jsSHA("mySuperPassword", "ASCII"),
		password = hashObj.getHash("SHA-512", "HEX"),
		decrypted_str = '';
	
	while (match = search.exec(query)) {
		if (match[1] === param) { // matches passed param containing spoton info
			decrypted_str = $.jCryption.decrypt(match[2], password);
		}
	}
	return decrypted_str;
};


// Check if a new cache is available on page load.
// Avoids double load to get new content: one to download a new appcache, and another to download the changed file(s)
window.addEventListener('load', function(e) {

	window.applicationCache.addEventListener('updateready', function(e) {
		if (window.applicationCache.status === window.applicationCache.UPDATEREADY) {
			// Browser downloaded a new app cache.
			// Swap it in and reload the page to get the new hotness.
			window.applicationCache.swapCache();
			if (confirm('A new version of this app is available. Load it?')) {
				window.location.reload();
			}
		} else {
			// Manifest didn't changed. Nothing new to server.
		}
	}, false);

}, false);


/* Handle querystrings */

function QueryData(queryString, preserveDuplicates){

	// if a query string wasn't specified, use the query string from the URL
	if (queryString === undefined){
		queryString = location.search ? location.search : '';
	}

	// remove the leading question mark from the query string if it is present
	if (queryString.charAt(0) === '?') queryString = queryString.substring(1);

	// check whether the query string is empty
	if (queryString.length > 0){

		// replace plus signs in the query string with spaces
		queryString = queryString.replace(/\+/g, ' ');

		// split the query string around ampersands and semicolons
		var queryComponents = queryString.split(/[&;]/g);

		// loop over the query string components
		for (var index = 0; index < queryComponents.length; index ++){

			// extract this component's key-value pair
			var keyValuePair	= queryComponents[index].split('=');
			var key						= decodeURIComponent(keyValuePair[0]);
			var value					= keyValuePair.length > 1
												? decodeURIComponent(keyValuePair[1])
												: '';

			// check whether duplicates should be preserved
			if (preserveDuplicates){

				// create the value array if necessary and store the value
				if (!(key in this)) this[key] = [];
				this[key].push(value);

			} else{

				// store the value
				this[key] = value;

			}
		}
	}
}