// Global vars
var show_map, db_errors, db_successes, map, cluster, marker_layers = {}, layersControl, args_global = {};

$(document).ready(function() {
	//emy.logging = true;

	resumeState();
	initSaveState(); // initialize form elements for autosave
	initClickHandlers();
	initOperatorField();
	initMap();

	// enable textarea inputs to automatically expand
	var elems = document.getElementsByTagName('textarea');
	for (var i = 0, len = elems.length; i < len; i ++) {
		MBP.autogrow(elems[i]);
	}

	// the following are commented out b/c we are disabling zooming in via HTML meta tag
	//MBP.preventZoom(); // Prevent iOS from zooming form fields onfocus
	//MBP.scaleFix(); // Prevent scaling bug in iOS when rotating portrait to landscape
});


// Fire events when user loads a view (called from data-onshow in html)
var initView = {
	home: function() {
		var screen_hash = initView.setScreen(),
			records = getRecords(),
			num_records = Object.keys(records).length;

		// move operator field back to home screen and change back to email
		$('#operator').appendTo('#user').prop('type', 'email');
		// remove any previous sync msg
		$('#syncrecords a span').remove();
		$('#syncrecords a').append(' <span>' + num_records + ' record'.pluralize(num_records) + '</span>');
	},
	form: function() {
		var screen_hash = initView.setScreen();

		// move operator and hidden fields to screen (form) user is viewing
		$('#operator, #hidden-fields').appendTo(screen_hash + ' form');
		// change to type text to prevent html5 validation from failing on non-email (not enforced)
		$('#operator').prop('type', 'text');
		// store form-name in hidden field
		$('#form-name').val(screen_hash.substr(1));

		// set 'Site' field to Spoton site if user hasn't already overridden it
		if (localStorage.spoton_site && !localStorage[screen_hash.substr(1) + '-site']) {
			$(screen_hash + '-site').val(localStorage.spoton_site);
		}
		if (navigator.onLine) { // show photo upload only if user online
			$('.photo').css('display', 'block');
		} else {
			$('.photo').css('display', 'none');
		}
		getLocation(new Date().getTime());
	},
	features: function() {
		var screen_hash = initView.setScreen();

		// disable buttons if offline
		if (navigator.onLine) {
			$('#features li a').removeClass('disabled').not('.download').removeAttr('target');
			$('#featurestatus').html('');
		} else {
			$('#features li a')
				.addClass('disabled')
				.attr('target', '_blank') // add target so that emy doesn't intercept link (so preventDefault works)
				.on('click', function(e) {
					e.preventDefault();
				});
			$('#featurestatus').html('<strong>Your device is currently offline.</strong> You must be connected to the internet to view maps and download data.');
		}
	},
	sync: function() {
		var screen_hash = initView.setScreen(),
			records = getRecords(),
			num_records = Object.keys(records).length;

		$('#syncstatus, #syncresults li').html('');

		// update button and status
		if (num_records > 0) {
			$('#syncbutton').html('Sync ' + num_records + ' ' + 'Record'.pluralize(num_records));
			if (!navigator.onLine) {
				$('#syncbutton').addClass('disabled');
				$('#syncstatus').html('Your device is currently offline.');
			} else {
				$('#syncbutton').removeClass('disabled');
			}
		} else {
			$('#syncbutton').html('Sync Observations');
			$('#syncbutton').addClass('disabled');
			$('#syncstatus').html('You don&rsquo;t have any observations stored on your device.');
		}
	},
	photo: function() {
		var screen_hash = initView.setScreen();
	},
	setScreen: function() {
		var elem = emy.getSelectedView(),
			screen_hash = '#' + elem.id;

		// save screen user is viewing
		localStorage.screen = screen_hash;
		return screen_hash;
	}
};


// onClick event handlers
function initClickHandlers() {

	// refresh geolocation
	$('form').on('click', '#refresh', function(e) {
		e.preventDefault();
		$('.location').slideUp('fast', getLocation(new Date().getTime()));
	});

	// toggle map
	$('form').on('click', '#showmap', function(e) {
		e.preventDefault();
		if ($('#showmap').text() === 'Show Map') { // show map
			$('#locationmap').slideDown('fast');
			$('#showmap').text('Hide Map');
			show_map = 1;
		} else { // hide map
			$('#locationmap').slideUp('fast');
			$('#showmap').text('Show Map');
			show_map = 0;
		}
		localStorage.show_map = show_map;
	});

	// prevent form submittal until disabled class is removed
	// For ex, during geolocation and rendering of attached photo to canvas
	$('form').on('click', '.record', function(e) {
		if ($(this).hasClass('disabled')) {
			e.preventDefault();
		}
	});

	// attach listener for user adding a photo
	$('[name="file"]').on('change', function(e) {
		var file = e.target.files[0]; // file pointer obj
		loadImage(file);
	});

	// start sync
	$('#sync').on('click', '#syncbutton', function(e) {
		e.preventDefault();
		$(this).addClass('disabled'); // only allow button press once
		syncRecords();
	});

	// display selected set of markers
	$('#features').on('click', '#periods a', function(e) {
		var period = $(this).attr('id') || '',
			qs = 'period=' + period,
			title = $(this).text();

		if ($('#onlymine').prop('checked')) {
			qs += '&operator=' + $('#operator').val();
		}

		// set title (set it directly on h1 tag b/c it doesn't register if you set it on the panel's title attr)
		$('#viewTitle').text('Loading...');

		// remove previously viewed markers
		if (cluster) {
			map.removeLayer(cluster);
			layersControl.removeLayer(marker_layers.pseudo_layer1); // features
		}
		if (marker_layers.pseudo_layer2) {
			layersControl.removeLayer(marker_layers.pseudo_layer2); // check-ins
		}

		// get selected features
		$.ajax({
			url: 'features.json.php?' + qs,
			dataType: 'jsonp',
			jsonpCallback: 'addFeatureLayer',
			timeout: 10000,
			success: function() {
				$('#viewTitle').text(title); // update title
			}
		});

		// get selected check-ins
		$.ajax({
			url: 'checkins.json.php?' + qs,
			dataType: 'jsonp',
			jsonpCallback: 'addCheckinLayer',
			timeout: 10000
		});
	});

	$('#map').on('click', '.popup a', function(e) {
	//$('.popup a').live('click', function(e) {
		alert('click');
		$('#photo').html('photo: ' + $(this).attr('data-fieldnotes-src'));
	});

}


// Grey out form links if operator field not filled in
function initOperatorField() {
	if ($('#operator').val().length < 3) {
		deActivate();
	}

	$('#operator').bind('keyup', function() {
		if($(this).val().length >= 3) {
			$('#home li a').removeClass('disabled').removeAttr('target');
		} else {
			deActivate();
		}
	});

	function deActivate() {
		$('#home li a').addClass('disabled')
			.attr('target', '_blank') // add target so that emy doesn't intercept link (so preventDefault works)
			.on('click', function(e) {
				e.preventDefault();
			});
	}
}


// Initialize map of recorded features
function initMap() {
	var esri_places, esri_terrain, esri_sat, esri_imagery, options, baseMaps, scaleControl;

	// Leaflet init
	map = new L.Map('map', {
		center: new L.LatLng(37.5, -118.5),
		zoom: 5,
		minZoom: 2,
		maxZoom: 17,
		attributionControl: false,
		scrollWheelZoom: false
	});

  options = {
    maxZoom: 18,
    subdomains: ['server', 'services'],
    detectRetina: true
  };

	// Esri base layers
	esri_terrain = L.tileLayer('https://{s}.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
    options
	);
	esri_imagery = L.tileLayer('https://{s}.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
		options
	);
  esri_places = L.tileLayer('https://{s}.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
    options
  );

  esri_sat = L.layerGroup([esri_imagery, esri_places]);

	map.addLayer(esri_terrain);

	// Add layers / scale controllers to map
	baseMaps = {
		"Terrain": esri_terrain,
		"Satellite": esri_sat
	};
	layersControl = new L.Control.Layers(baseMaps, null, { collapsed: false });
	scaleControl = new L.Control.Scale();
	map.addControl(scaleControl).addControl(layersControl);

	// don't want emy to intercept zoom buttons
	$('.leaflet-control-container a').attr('target', '_blank');

	map.on('overlayadd', function(e) {
		var layer_name = e.name;
		cluster.addLayer(marker_layers[layer_name]);
	});
	map.on('overlayremove', function(e) {
		var layer_name = e.name;
		cluster.removeLayer(marker_layers[layer_name]);
	});
}


// Add selected features to map
function addFeatureLayer(markers) {
	// close any previously opened popups
	map.closePopup();

	// update map container - emy framework confuses leaflet map and this forces map to display correctly
	map.invalidateSize();

	// plot selected markers
	if (markers) {
		var blue = L.icon({
			iconUrl: 'img/pin-m-feature+00c.png',
			iconSize: [30, 70],
			iconAnchor: [15, 34],
			popupAnchor: [1, -30]
		}),
		layer_name = 'Features',
		count = 0;

		marker_layers[layer_name] = new L.GeoJSON(markers, {
			pointToLayer: function(feature, latlng) {
				count ++;
				return new L.Marker(latlng, { icon: blue });
			},
			onEachFeature: createPopup
		});
		//layer_name += ' (' + count + ')';

		// don't want to "tie" features layer to layers control b/c both features and check-ins are contained in the same cluster but controlled as separate layers
		// (the layers are manually toggled via an event listener in initMap)
		// add a "pseudo" layer to map (and reference it in layers control) so that the "Features" layer check box is toggled on
		marker_layers.pseudo_layer1 = L.layerGroup();
		map.addLayer(marker_layers.pseudo_layer1);
		layersControl.addOverlay(marker_layers.pseudo_layer1, layer_name);

		cluster = new L.MarkerClusterGroup({showCoverageOnHover: false, maxClusterRadius: 20, spiderfyOnMaxZoom: true});
		map.addLayer(cluster);
		cluster.addLayer(marker_layers[layer_name]);
		map.fitBounds(cluster.getBounds());
	} else {
		// no markers, reset map view
		map.locate({setView: true, maxZoom: 6});
	}
}


// Add selected check-ins to map
function addCheckinLayer(markers) {
	if (markers) {
		var grey = L.icon({
			iconUrl: 'img/pin-m-checkin+999.png',
			iconSize: [30, 70],
			iconAnchor: [15, 34],
			popupAnchor: [1, -30]
		}),
		layer_name = 'Check-ins',
		count = 0;

		marker_layers[layer_name] = new L.GeoJSON(markers, {
			pointToLayer: function(feature, latlng) {
				count ++;
				return new L.Marker(latlng, { icon: grey });
			},
			onEachFeature: createPopup
		});
		//layer_name += ' (' + count + ')';
		marker_layers.pseudo_layer2 = L.layerGroup();
		layersControl.addOverlay(marker_layers.pseudo_layer2, layer_name);
	}
}


// create popup html and attach it to the marker
function createPopup(feature, layer) {
	var img,
		properties = feature.properties,
		title = properties.form || 'Check-in',
		html = '<div class="popup"><h1>' + title + '</h1>';

	// if device didn't pass a timestamp, use the datetime it was recorded to db
	if (properties.timestamp) {
		html += '<p class="time">' + properties.timestamp + ' ' + properties.timezone + '</p>';
	} else {
		html += '<p class="time">' + properties.recorded || properties.synced + '</p>';
	}
	if (properties.attachment) {
		// use thumbnail photo created during upload
		img = properties.attachment.replace(/\.(jpe?g|gif|png)$/i, "-tn.png");
		//html += '<a href="#photo" data-fieldnotes-src="' + properties.attachment + '">';
		html += '<img src="' + img + '" height="125" alt="site photo" />';
		//html += '</a>';
	}
	html += '<table>';
	if (properties.site) {
		html += '<tr><th>Site</th><td>' + properties.site + '</td</tr>';
	}
	if (properties.description) {
		html += '<tr><th>Location</th><td>' + properties.description + '</td</tr>';
	}
	html += '</table>';
	if (properties.notes) {
		html += '<p>' + properties.notes + '</p>';
	}
	html += '<p class="user">Created by ' + properties.operator + '</p>';
	layer.bindPopup(html, {maxWidth: '265', closeButton: false, autoPanPadding: new L.Point(5, 50)});
}


// Get user's current location from device
function getLocation(timestamp) {
	if (!Modernizr.geolocation) {
		return false;
	}
	var screen_hash = localStorage.screen;

	// disable submit button until device location determined
	$('.record').addClass('disabled');

	// remove any previous location info / map
	$('.location').remove();

	$(screen_hash + '-location')
		.after('<div class="location"><p id="coords">Locating&hellip;</p></div>');

	navigator.geolocation.getCurrentPosition(setLocation, locationError, {enableHighAccuracy: true, maximumAge: 1000, timeout: 5000});
}


// Set user's location -- form fields
function setLocation(_position) {
	var ts = _position.timestamp,
		timestamp = new Date(ts),
		gmt_offset = timestamp.getTimezoneOffset() / 60 * -1,
		lat, lon;

	// if timestamp is in milliseconds (e.g. iOS 6), reset to sec.
  if (ts.toString().length === 16) {
		ts = ts / 1000;
  }

	// set values of hidden form fields
	$('#timestamp').val(moment(timestamp).format("YYYY-MM-DD HH:mm:ss")); // local time
	$('#gmt_offset').val(gmt_offset);
	$('#lat').val(_position.coords.latitude);
	$('#lon').val(_position.coords.longitude);
	$('#accuracy').val(_position.coords.accuracy);
	$('#z').val(_position.coords.altitude);
	// no idea why, but this param MUST be set last...anything after it not filled in
	$('#zaccuracy').val(_position.coords.altitudeAccuracy);

	// use SpotOn location if available
	if (localStorage.spoton_lat && localStorage.spoton_lon) {
		lat = localStorage.spoton_lat;
		lon = localStorage.spoton_lon;
	} else {
		lat = Math.round(_position.coords.latitude * 1000) / 1000;
		lon = Math.round(_position.coords.longitude * 1000) / 1000;
	}
	displayLocation(lat, lon, timestamp);

	// activate submit button
	$('.record').removeClass('disabled');
}


// Display user's location -- map, coords, etc
function displayLocation(lat, lon, timestamp) {
	var coords, spoton;

	if (localStorage.spoton_lat && localStorage.spoton_lon) {
		spoton = true;
	}

	// display coords
	coords = lat + ', ' + lon;
	if (timestamp && !spoton) {
		coords += ' <span>at ' + moment(timestamp).format("h:mm:ss a") + '</span>';
	}

	$('#coords')
		.html(coords)
		.after('<ul id="options" class="normal"></ul>');

	if (!spoton) {
		// refresh link (add target="_blank" so that emy doesn't intercept links)
		$('#options').append('<li><a href="#" target="_blank" id="refresh">Refresh</a></li>');
	}

	// display map if user online
	if (navigator.onLine) {
		var map_url = 'https://api.tiles.mapbox.com/v3/shaefner.map-8sg8c9nv/pin-m-star+cc3311(' + lon + ',' + lat + ')/' + lon + ',' + lat + ',15/544x544.jpg',
			map_app = 'https://maps.apple.com/?q=' + lat + ',' + lon + '&t=m&z=13';

		// map toggle (add target="_blank" so that emy doesn't intercept links)
		$('#options').append('<li><a href="#" target="_blank" id="showmap">Hide Map</a></li>');
		$('#options').after(
			'<div id="locationmap">' + // map
				'<img src="' + map_url + '" width="272" height="272" alt="map showing current location">' +
				'<span class="launchapp"><a href="' + map_app + '" target="_blank">Open in Maps</a></span>' +
			'</div>'
		);

		if (!show_map) {
			$('#locationmap').hide();
			$('#showmap').text('Show Map');
		}
	}

	// display location info
	$('.location').hide().slideDown('fast');
}


// Location error handling
function locationError(_error) {
	var errors = ["Unknown error", "Permission denied by user", "Position unavailable", "Time out"];

	$('#coords').append(
		'<p class="error">' + errors[_error.code] + '</p>' +
		// add target="_blank" so that emy doesn't intercept links
		'<ul id="options" class="normal"><li><a href="#" target="_blank" id="refresh">Try again</a></li></ul>'
	);

	// show spoton location if available instead of error message
	if (localStorage.spoton_lat && localStorage.spoton_lon) {
		displayLocation(localStorage.spoton_lat, localStorage.spoton_lon);
	}

	// activate submit button
	$('.record').removeClass('disabled');
}


// Store record in browser's localStorage; called from on(off)line.html
function storeRecord(querystring) {
	if (!Modernizr.localstorage) {
		$('#results').attr('data-title', 'Error').html('<p>Can&rsquo;t store record. Your device is in &ldquo;private&rdquo; mode or doesn&rsquo;t support storage.</p><p>Be certain to reload after disabling private mode.</p>');
		return false;
	}

	var key = moment().valueOf(), // milliseconds since Unix epoch
		screen_hash = localStorage.screen,
		file_id = screen_hash.substr(1) + '-photo',
		file;

	// not all forms have file upload, so check
	if (document.getElementById(file_id)) {
		file = document.getElementById(file_id).files[0];
	}

	// add extra params to querystring that get stored in db
	if (file) {
		var ext = file.name.substr(file.name.lastIndexOf('.') + 1).toLowerCase();
		// append generated filename
		querystring += '&photo=' + key + '.' + ext;
	}
	if (localStorage.spoton) {
		// add spoton info if it's there
		querystring += localStorage.spoton;
	}

	/*TO DO:
		it's conceivable that a photo upload could fail but get still get it's filename inserted in the db
		store photo contents in localstorage as well as the form details
	*/
	// store record in localstorage (and insert in db / upload photo if user is online)
	localStorage[key] = querystring;
	if (navigator.onLine) {
		if (file) { // upload photo if included
			uploadPhoto(file, key);
		}
		insertRecord(key, querystring);
	}

	returnHtml(); // display results
	clearState(args_global['form-name']); // remove stored form field values from localstorage
	resumeState(); // remove previous entries from html form
}


// Insert record into db
function insertRecord(key, querystring) {
	var screen_hash = localStorage.screen;

	$.get('insert.php?' + querystring, function(error) {
		if (screen_hash === '#sync') { // sync screen
			if (error) {
				db_errors ++;
				$('#sync .error').html(db_errors + ' record'.pluralize(db_errors) + ' failed to sync: ' + error);
			} else {
				db_successes ++;
				$('#sync .success').html(db_successes + ' record'.pluralize(db_successes) + ' synced');
			}
		}
		if (!error) {
			// remove from localStorage on successful db insert
			localStorage.removeItem(key);
		}
	});
}


// Sync records stored in browser's localStorage to db
function syncRecords() {
	var records, record, key;

	// reset error / success vars from any previous inserts
	db_errors = 0;
	db_successes = 0;

	records = getRecords();
	for (key in records) {
		// add recorded time to querystring
		record = records[key] + '&recorded=' + encodeURIComponent(key);
		insertRecord(key, record);
	}
}


// Get records from localStorage
function getRecords() {
	if (!Modernizr.localstorage) {
		return false;
	}
	var i, key, records = {};

	for (i = 0; i < localStorage.length; i ++) {
		key = localStorage.key(i);
		if ( // stored records use date string for key
			key.match(/^\d{13}$/) ||
			// deprecated format; kept for compatibility w/ previously stored records
			key.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)
		) {
			records[key] = localStorage[key];
		}
	}
	return records;
}

// Read file and render it to canvas
function loadImage(file) {
	// Prevent any non-image file type from being read.
	if (!file.type.match(/image.*/)) {
		return;
	}

	// disable submit button until image is rendered in canvas
	$('.record').addClass('disabled');

	// setup canvas elem
	var screen_hash = localStorage.screen,
		canvas_id = screen_hash.substr(1) + '-' + 'canvas';

	// remove any previous canvas, p elems
	$('#' + canvas_id).remove();
	$(screen_hash + ' .photo p').remove();

	$(screen_hash + ' .photo')
		.append('<p>' + file.name + ' (' + Math.round(file.size * 10 / 1000) / 10 + ' kB)</p>')
		.append('<canvas id="' + canvas_id + '"></canvas>');

	// use library to read and render img - overcomes iOS resolution limitation and makes rotating / resizing easy
	// https://github.com/stomita/ios-imagefile-megapixel
	// also get img orientation from EXIF data
	EXIF.getData(file, function() {
		var canvas = document.getElementById(canvas_id),
			orientation = EXIF.getTag(this, 'Orientation'),
			mpImg = new MegaPixImage(file),
			max_size = 800;
		mpImg.render(canvas, { maxWidth: max_size, maxHeight: max_size, orientation: orientation }, function() {
			// activate submit button
			$('.record').removeClass('disabled');
		});
	});
}


// Upload attached photo
function uploadPhoto(file, basename) {
	// grab contents of canvas element (resized img)
	var screen_hash = localStorage.screen,
		canvas_id = screen_hash.substr(1) + '-' + 'canvas',
		canvas = document.getElementById(canvas_id);

	canvas.toBlob(function(imgblob) {
		// construct a set of key/value pairs representing form fields and their values
		// https://stackoverflow.com/questions/5392344/sending-multipart-formdata-with-jquery-ajax;
		var formdata = new FormData();
		formdata.append('photo', imgblob, file.name);
		formdata.append('name', basename);

		$('#results p').html('<img src="img/loading.gif" width="16" height="16" /> Uploading photo...');

		$.ajax({
			url: 'upload.php',
			type: 'POST',
			data: formdata,
			cache: false,
			contentType: false, // prevent jQuery from setting contentType
			processData: false // prevent jQuery from transforming data into querystring
		}).done(function(error) {
			if (error) {
				$('#results p').html('<img src="img/error.png" width="16" height="16" /> ' + error);
			} else {
				$('#results p').html('<img src="img/ok.png" width="16" height="16" /> Photo uploaded successfully');
			}
		});

	}, file.type);
}


// Show summary screen after user submits form
function returnHtml() {

	var key, label,
		return_html = '',
		form_name = args_global['form-name'],
		// setup 'friendly' field names to substitute on 'Recorded' return screen (form name w/ nested name-value pairs)
		labels = {
			"rupture": {
				"surface": "rupture",
				"offset[]": "feature"
			},
			"liquefaction": {
				"blows": "fissures"
			}
		};

	for (key in args_global) {

		// don't echo empty fields, form name, operator, or location details in return html
		if (args_global[key] === '' ||
				key === 'form-name' ||
				key === 'operator' ||
				(key.match(/^location-.+/) && !key.match(/location-description/))) {
			continue;
		}
		if (key === 'file') {
			// strip path
			args_global[key] = args_global[key].substr(args_global[key].lastIndexOf('\\') + 1);
		}

		// apply 'friendly' fieldnames
		label = key.capitalize();
		if (key === 'location-description') {
			label = 'Location';
		}
		// loop thru labels configured above
		else if (labels[form_name] && typeof labels[form_name][key] === 'string') {
			label = labels[form_name][key].capitalize();
		}
		// remove array notation '[]' from end of key
		else if (label.match(/\[\]$/)) {
			label = key.substr(0, key.length - 2).capitalize();
		}

		var row = '<div class="row"><label>' + label + '</label><span>' + args_global[key] + '</span></div>';
		return_html += row;
	}
	$('#results fieldset').html(return_html);
	$('#results').attr('data-title', 'Recorded');
}


// Save user entered values / current screen to localStorage
function initSaveState() {
	// if the following test fails, private browsing must be on
	if (localStorage) {
		try {
			localStorage['testKey'] = 1;
			localStorage.removeItem('testKey');
		} catch (error) {
			alert('Please disable private browsing *and reload* to use this app');
		}
	}
	if (!Modernizr.localstorage) {
		return false;
	}
	var elem_id, other_elem_id;

	// onKeypress: textareas and input fields (text, number, email, etc)
	$('input:not(:radio,:checkbox), textarea').keyup(function() {
		elem_id = $(this).attr('id');
		localStorage[elem_id] = $(this).val();
	});

	// onChange: pulldown menus and input fields--need change event for input text in case user doesn't type changes (e.g. a number field)
	$('input, select').change(function() {
		elem_id = $(this).attr('id');
		// checkboxes
		if ($(this).attr('type') === 'checkbox') {
			storeBoolean(elem_id);
		}
		// radio buttons
		else if ($(this).attr('type') === 'radio') {
			storeBoolean(elem_id);
			$(this).parent().siblings().children('input').each(function(i) {
				// need to store value of "other" (non-selected) radio button
				other_elem_id = $(this).attr('id');
				storeBoolean(other_elem_id);
			});
		}
		// pulldown menus and text input (incl. number, email, etc)
		else {
			localStorage[elem_id] = $(this).val();
		}
	});

	function storeBoolean(id) {
		if ($('#' + id).is(':checked')) {
			localStorage[id] = 1;
		} else {
			localStorage[id] = 0;
		}
	}
}


// Retrieve user entered values / current screen from localStorage
function resumeState() {
	if (!Modernizr.localstorage) {
		return false;
	}
	var elem_id, hashtag, url, is_checked,
		screen_hash = localStorage.screen;

	show_map = parseInt(localStorage.show_map, 10);

	// show appropriate screen
	if (screen_hash) {
		if (screen_hash === '#home') {
			initView.home();
		}
		if (!window.location.hash) {
			url = 'https://' + window.location.host + window.location.pathname + screen_hash;
			window.location.replace(url);
		}
	}

	// set text areas and pulldown menus
	$('textarea, select').each(function() {
		elem_id = $(this).attr('id');
		$(this).val(localStorage[elem_id]);
	});

	// set input fields (checkbox, radio, text)
	$('input').each(function() {
		elem_id = $(this).attr('id');
		// checkboxes and radio buttons
		if ($(this).attr('type') === 'checkbox' || $(this).attr('type') === 'radio') {
			is_checked = parseInt(localStorage[elem_id], 10);
			if (is_checked) {
				$(this).attr('checked', true);
			} else {
				$(this).attr('checked', false);
			}
		}
		// text, number, email, etc. (can't manipulate file obj)
		else if ($(this).attr('type') !== 'file') {
			$(this).val(localStorage[elem_id]);
		}
	});
}


// Clear form field values from localStorage when form submitted
function clearState(form) {
	var id,
		// get form elements by type
		elem = $('#' + form + ' input, #' + form + ' select, #' + form + ' textarea');

	// loop thru form elements
	$(elem).each(function() {
		id = $(this).attr('id');
		if (id !== 'operator') {
			localStorage.removeItem(id);
		}
	});

	// remove SpotOn info
	localStorage.removeItem('spoton');
	localStorage.removeItem('spoton_site');
	localStorage.removeItem('spoton_lat');
	localStorage.removeItem('spoton_lon');

	// remove references to photo user attached
	$('#' + form + ' .photo p').remove();
	$('#' + form + ' .photo canvas').remove();
}
