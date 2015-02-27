<?php
$coords = "51.5286416,-0.1015987"; //You can change the location, by altering the latitude and longitude.
if(isset($_GET['requesttweet'])){
	$data = returnTweet();
	for($i = 0;$i < count($data['statuses']);$i++) echo '<div style="border: 1px solid #ccc;z-index: 1;position: fixed;bottom: 0px;min-height: 80px;width: 100%;background: url(' . $data['statuses'][$i]['user']['profile_image_url'] . ') 5px 5px no-repeat #fff;" title="' . $data['statuses'][$i]['id_str'] . '" id="' . $data['statuses'][$i]['geo']['coordinates'][0] . ',' . $data['statuses'][$i]['geo']['coordinates'][1] . '"><p style="word-wrap: break-word;margin: 0px;display: block;margin-left: 55px;padding: 5px;"><span style="color: #aaa;font-size: 12px;">@' . $data['statuses'][$i]['user']['screen_name'] . '</span> <b>' . $data['statuses'][$i]['user']['name'] . '</b><br/>' . makeClickableLinks(htmlspecialchars($data['statuses'][$i]['text'])) . '</p></div>';
	exit;
}
function makeClickableLinks($s){
	return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" target="_blank">$1</a>', $s);
}
function buildBaseString($baseURI, $method, $params){
	$r = array();
	ksort($params);
	foreach($params as $key=>$value) $r[] = "$key=" . rawurlencode($value);
	return $method."&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
}
function buildAuthorizationHeader($oauth){
	$r = 'Authorization: OAuth ';
	$values = array();
	foreach($oauth as $key=>$value) $values[] = "$key=\"" . rawurlencode($value) . "\"";
	$r .= implode(', ', $values);
	return $r;
}
function returnTweet(){
	global $coords;
	$oauth_access_token = "519468705-nNHeShU0rkYMO3iJp0zC0UkCiz9VNe4XUaiX40vx";
	$oauth_access_token_secret = "3znMnOe1iNGPDgu6znlqmDbgrm4gd7zPJjVS4VU54vaBs";
	$consumer_key = "W8R3m8KpDnNjqZLb1a0TJtGA0";
	$consumer_secret = "e8qulJOewxqWLwSrpzx4XuDGCsxvneoXIJX4G8eCiPJ99jRxRY";
	$twitter_timeline = "search/tweets";
	$request = array(
		'geocode' => $coords . ',50mi',
		'result_type' => 'recent'
	);
	if(isset($_GET['q'])) $request['q'] = $_GET['q'];
	$oauth = array(
		'oauth_consumer_key'        => $consumer_key,
		'oauth_nonce'               => time(),
		'oauth_signature_method'    => 'HMAC-SHA1',
		'oauth_token'               => $oauth_access_token,
		'oauth_timestamp'           => time(),
		'oauth_version'             => '1.0'
	);
	$oauth = array_merge($oauth, $request);
	$base_info = buildBaseString("https://api.twitter.com/1.1/$twitter_timeline.json", 'GET', $oauth);
	$composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_access_token_secret);
	$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
	$oauth['oauth_signature'] = $oauth_signature;
	$header = array(buildAuthorizationHeader($oauth), 'Expect:');
	$params = array('http' => array('method' => 'GET'));
	$params['http']['header'] = $header;
	$ctx = stream_context_create($params);
	$fp = @fopen("https://api.twitter.com/1.1/$twitter_timeline.json?". http_build_query($request), 'rb', false, $ctx);
	$json = @stream_get_contents($fp);
	fclose($fp);
	return json_decode($json, true);
}
?>
<!DOCTYPE html>
<head>
<title>Twitter Locator</title>
<style>
html {
	width: 100%;
	height: 100%;
}
body {
	font: 15px Arial, Helvetica, sans-serif;
	width: 50%;
	height: 50%;
	margin: 0px;
	padding: 0px;
}
a {
	color: #49f;
	text-decoration: none;
}
a:hover {
	text-decoration: underline;
}
</style>
<script src="http://maps.googleapis.com/maps/api/js"></script>
<script>
var map = -1;
var messages = [];
var last = -1;
function toggleDiv(objid){
	if(last != -1) document.body.removeChild(last);
	if(objid.getElementsByTagName("p")[0]) objid.getElementsByTagName("p")[0].style.paddingRight = "320px";
	document.body.appendChild(objid);
	last = objid;
}
window.onload = function () {
	var mapProp = { center:new google.maps.LatLng(<?php echo $coords; ?>), zoom:8, mapTypeId:google.maps.MapTypeId.ROADMAP };
	map = new google.maps.Map(document.getElementById("googleMap"), mapProp);
	getPage();
	setInterval(function(){getPage()}, 10000);
};
function getPage(){
	var http;
	if(window.XMLHttpRequest){
		http = new XMLHttpRequest();
	}else if(window.ActiveXObject){
		try{
			http = new ActiveXObject("Msxml2.XMLHTTP");
		}catch(e){
			http = new ActiveXObject("Microsoft.XMLHTTP");
		}
	}
	if(http){
		http.onreadystatechange = function(){
			if(http.readyState == 4){
				document.getElementById("content").innerHTML = http.responseText;
				var x = document.getElementById("content").getElementsByTagName("div");
				for(var i = 0;i < x.length;i++){
					var found = false;
					for(var z = 0;z < messages.length;z++) if(messages[z] == x[i].title) found = true;
					if(!found && x[i].id){
						messages[messages.length] = x[i].title;
						var coords = x[i].id.split(",");
						var marker = new google.maps.Marker({position:new google.maps.LatLng(parseFloat(coords[0]),parseFloat(coords[1])), map: map});
						marker.obj = x[i];
						document.getElementsByTagName("ul")[0].innerHTML += "<li style=\"background: " + x[i].style.backgroundImage + " no-repeat 5px 5px;min-height: 60px;\">" + x[i].innerHTML + "</li>";
						google.maps.event.addListener(marker, 'mouseover', function(){ toggleDiv(this.obj); });
					}
				}
			}
		};
		http.open("GET", "?requesttweet<?php if(isset($_GET['q'])) echo '&q=' . $_GET['q']; ?>&time=" + new Date().getTime(), true);
		http.send(null);
	}
}
</script>
</head>
<body>
<ul style="list-style: none;padding: 0px;margin: 0px;z-index: 2;position: fixed;right: 0px;top: 0px;width: 320px;height: 100%;background: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAANSURBVBhXY2BgYJgJAACeAJpsNturAAAAAElFTkSuQmCC');color: #fff;overflow-y: scroll;"><li><form method="get"><input type="text" style="border: 1px solid #000;width: 100%;" name="q" /></form></li></ul>
<span id="content" style="display: none;"></span>
<span id="googleMap" style="width:100%;height:100%;display:block;"></span>
</body>
