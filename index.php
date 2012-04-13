<?
/*
 * This hook was registered using
 *
 * curl -i -u "user:password" https://api.github.com/hub \
 * -Fhub.mode=subscribe \
 * -Fhub.topic=https://github.com/USER/REPO/events/EVENT \
 * -Fhub.callback=SOMEURL
 *
 * See https://github.com/github/github-services/issues/166
 */
require_once "Mail.php";

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
		echo("<p>" . $mail->getMessage() . "</p>");
	} else {
		echo("<p>Message successfully sent!</p>");
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
	if (isset($_POST['payload']))
		var_dump(json_decode($_POST['payload']));
	$msg .= ob_get_clean();
	ob_end_flush();
	$file = fopen('/tmp/github-notifier.log', 'a');
	fwrite($file, $msg);
	fclose($file);
}

$remote = $_SERVER['REMOTE_ADDR'];
$headers = apache_request_headers();
$event = isset($headers['X-Github-Event']) ? $headers['X-Github-Event'] : false;
tmpLog($event);
if (!$event ||
		!isset($_POST['payload']) ||
		!preg_match('/\.github\.com$/', gethostbyaddr($remote))) {
	include('hooks.php');
}
else {
	$payload = json_decode($_POST['payload']);
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
			. $pull->title . "\n\n"
			. $pull->body;
	}
	elseif ($event == 'push') {
		$ref = $payload->ref;
		$subject .= 'Pushed ' . $payload->size . ' commit' . ($payload->size > 1 ? 's' : '') . ' to ' . $ref;
		$body = 'See ' . $repository->html_url;
	}
	else
		exit('invalid event: ' + $event);

	include('mail-settings.php');
	sendNotification($subject, $body);
}

?>
