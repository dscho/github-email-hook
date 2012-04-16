<?
/*
 * This hook was registered using hooks.php.
 *
 * It requires php-mail to be installed and pecl-http. I cannot properly verify the signature
 * that signs the payload with our secret since the pecl-hash module failed to compile here.
 */
require_once "Mail.php";

function tmpWrite($text) {
	$file = fopen('/tmp/github-notifier.log', 'a');
	fwrite($file, $text);
	fclose($file);
}

function sendNotification($subject, $body) {
	global $mail_from, $mail_host, $mail_username, $mail_password, $owner;
	$from = 'GitHub <' . $mail_from . '>';
	$to = $owner == 'msysgit' ? 'msysgit@googlegroups.com' : 'johannes.schindelin@gmx.de';
	$reply_to = $to;

	$headers = array ('From' => $from,
		'To' => $to,
		'Reply-to' => $reply_to,
		'Subject' => $subject);
	$smtp = Mail::factory('smtp',
		array ('host' => $mail_host,
			'auth' => true,
			'username' => $mail_username,
			'password' => $mail_password));

	$mail = $smtp->send($to, $headers, $body);

	if (PEAR::isError($mail)) {
		tmpWrite($mail->getMessage() . "\n");
	} else {
		tmpWrite("Message successfully sent!\n");
	}
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
		$body = http_get_request_body();
		if ($body != '') {
			print("BODY\n");
			var_dump(json_decode($body));
		}
	}
	$msg .= ob_get_clean();
	ob_end_flush();
	tmpWrite($msg);
}

$remote = $_SERVER['REMOTE_ADDR'];
$event = isset($_SERVER['HTTP_X_GITHUB_EVENT']) ? $_SERVER['HTTP_X_GITHUB_EVENT'] : false;
tmpLog($event);
if (!$event || !preg_match('/\.github\.com$/', gethostbyaddr($remote))) {
	include('hooks.php');
}
else {
	$payload = json_decode(isset($_POST['payload']) ? $_POST['payload'] : http_get_request_body());
	$repository = $payload->repository;
	$owner = $repository->owner->login;
	if ($owner != 'msysgit' && $owner != 'dscho')
		exit('Invalid owner: ' . $owner);
	$subject = '[github] ';
	$repo_name = $repository->owner->login . '/' . $repository->name;

	if ($event == 'issues') {
		$issue = $payload->issue;
		$subject .= 'Issue ' . $issue->number . ' ' . $payload->action . ' (' . $issue->title . ')';
		$body = 'See ' . $issue->html_url . "\n\n"
			. $issue->title . "\n\n"
			. $issue->body;
	}
	elseif ($event == 'issue_comment') {
		$issue = $payload->issue;
		$comment = $payload->comment;
		$subject .= 'Comment ' . $payload->action . ' on issue ' . $issue->number . ' (' . $issue->title . ')';
		$body = 'See ' . $issue->html_url . "\n\n"
			. $payload->sender->login . " added/edited comment:\n\n"
			. $comment->body;
	}
	elseif ($event == 'pull_request') {
		$number = $payload->number;
		$pull = $payload->pull_request;
		$subject .= 'Pull request #' . $pull->number . ' ' . $payload->action . ' (' . $pull->title . ')';
		$body = 'See ' . $pull->html_url . "\n\n"
			. $payload->sender->login . ' ' . $payload->action . ' ' . $pull->title . "\n\n"
			. $pull->body;
	}
	elseif ($event == 'push') {
		$ref = $payload->ref;
		$subject .= 'Pushed ' . $payload->size . ' commit' . ($payload->size > 1 ? 's' : '') . ' to ' . $ref;
		$body = 'See ' . $repository->html_url;
	}
	elseif ($event == 'watch') {
		$subject .= $payload->sender->login . ' ' . $payload->action . ' watching ' . $repo_name;
		$body = 'See ' . $repository->html_url;
	}
	elseif ($event == 'commit_comment') {
		$subject .= 'Commit comment by ' . $payload->sender->login . ' in ' . $repo_name;
		$body = 'See ' . $payload->comment->html_url . "\n\n"
			. $payload->comment->body;
	}
	elseif ($event == 'download') {
		$subject .= 'Download added to ' . $repo_name . ': ' . $payload->download->name;
		$body = 'See ' . $payload->download->html_url . "\n\n"
			. $payload->sender->login . ' added the download "' . $payload->download->name . '" to ' . $repo_name;
	}
	elseif ($event == 'fork') {
		$subject .= $repo_name . ' was forked by ' . $payload->sender->login;
		$body = 'See ' . $payload->forkee->clone_url;
	}
	else {
		$subject .= 'Received event "' . $event . '"';
		ob_start();
		var_dump($payload);
		$body = "Unhandled event:\n\n" . ob_get_clean();
	}

	include('mail-settings.php');
	sendNotification($subject, $body);
}

?>
