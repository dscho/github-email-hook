<?
/*
 * This hook was registered using hooks.php. Its purpose is to notify Jenkins
 * when something new was pushed to 'master' (but no other branch).
 *
 * It cannot properly verify the signature that signs the payload
 * with our secret since the pecl-hash module failed to compile here.
 *
 * It requires pecl-http. On Debian/Ubuntu, you most likely need to call
 *
 *   sudo pecl install pecl_http
 *
 * then add the line extension=http.so to /etc/php5/apache2/php.ini and then
 * restart Apache e.g. by calling
 *
 *   sudo apache2ctl graceful
 */
function tmpWrite($text) {
	if ($text != '' && substr($text, strlen($text) - 1) != "\n") {
		$text .= "\n";
	}
	$file = fopen('/tmp/github-notifier.log', 'a');
	fwrite($file, $text);
	fclose($file);
}

function tmpLog($msg) {
	$date = strftime('%Y-%m-%d-%H:%M:%S');
	$msg = $date . ' ' . $msg . "\n";
	ob_start();
	print("request headers\n");
	var_dump(apache_request_headers());
	print("GET\n");
	var_dump($_GET);
	print("POST\n");
	var_dump($_POST);
	print("SERVER\n");
	var_dump($_SERVER);
	if (isset($_POST['payload'])) {
		print("PAYLOAD\n");
		var_dump(json_decode($_POST['payload']));
	}
	else {
		$body = file_get_contents('php://input'); // http_get_request_body();
		if ($body != '') {
			print("BODY\n");
			var_dump(json_decode($body));
		}
	}
	$msg .= ob_get_clean();
	ob_end_flush();
	tmpWrite($msg);
}

function trigger_jenkins($jenkins_url) {
	$response = http_request(HTTP_METH_GET, $jenkins_url);
	tmpWrite("trigger " . $jenkins_url . "\nresponse:\n" . $response);
}

$remote = $_SERVER['REMOTE_ADDR'];
$event = isset($_SERVER['HTTP_X_GITHUB_EVENT']) ? $_SERVER['HTTP_X_GITHUB_EVENT'] : false;
tmpLog($event);
if (!$event || !preg_match('/\.github\.com$/', gethostbyaddr($remote))) {
	tmpWrite("It's not GitHub");
	die("You're not GitHub");
}
elseif ($event != 'push') {
	tmpWrite("Ignoring event " + $event);
}
else {
	$body = isset($_POST['payload']) ? $_POST['payload'] : file_get_contents('php://input');
	$payload = json_decode($body);
	$repository = $payload->repository;
	$owner = $repository->owner->name;
	$subject = '[github] ';
	$repo_name = $owner . '/' . $repository->name;
	$ref = $payload->ref;

	sleep(5); // grace period
	include('jenkins-settings.php');
	if (isset($jenkins_url)) {
		tmpWrite("Trigger $jenkins_url");
		trigger_jenkins($jenkins_url);
	}
	else {
		tmpWrite("Ignore $repo_name, ref $ref");
	}
}

?>
