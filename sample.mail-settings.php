<?

$mail_from = "example@author.com";
$mail_host = "smtp.com";
$mail_username = "joe.howard";
$mail_password = "now, what's a good password?";

if ($owner == 'one-github-org')
	$mail_to = 'organization@somewhere.net';
elseif ($owner == 'my-github-account')
	$mail_to = 'dev@null';
else
	exit('Invalid owner: ' . $owner);

?>
