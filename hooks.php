<?

/*
 * This is a helper to subscribe index.php to the GitHub API repository events.
 * It basically remedies the fact that the web hook is not subscribable by the
 * admin web UI provided by GitHub.
 *
 * It requires pecl_http to work, since it needs to send custom HTTP requests
 * (e.g. with verbs like PATCH...)
 */

if (!defined('HTTP_METH_PATCH'))
	define('HTTP_METH_PATCH', http_request_method_register('PATCH'));

$url = 'https://api.github.com/';

$user = isset($_POST['user']) ? $_POST['user'] : '';
$pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';
$repository = isset($_POST['repository']) ? $_POST['repository'] : '';

if ($repository != '' && !strpos($repository, '/'))
	$repository = $user . '/' . $repository;

print('GitHub credentials:');
print('<dl>');
print('<form id="credentials" method="POST">');
print('<dt><strong>User</strong></dt><dd><input type="text" name="user" value="' . $user . '" /></dd>');
print('<dt><strong>Password</strong></dt><dd><input type="password" name="pwd" value="' . $pwd . '" /></dd>');
print('<dt><strong>Repository</strong></dt><dd><input type="text" name="repository" value="' . $repository . '" /></dd>');
print('</dl>');
print('<input type="submit" value="Update" />');
print('</form><br />');

if ($pwd == '')
	exit();

print('<hr /><br />');

function send_request($method, $command, $body) {
	global $url, $user, $pwd, $repository;
	$response = http_request($method, $url . 'repos/' . $repository . '/' . $command,
		$body, array('httpauth' => $user . ':' . $pwd));
	$header_end = strpos($response, "\r\n\r\n");
	$headers = substr($response, 0, $header_end + 2);
	$body = substr($response, $header_end + 4);
	return json_decode($body);
}

function form_header($id, $action, $id = false) {
	global $user, $pwd, $repository;
	print('<form id="' . $id . '" method="POST">');
	print('<input type="hidden" name="repository" value="' . $repository . '" />');
	print('<input type="hidden" name="user" value="' . $user . '" />');
	print('<input type="hidden" name="pwd" value="' . $pwd . '" />');
	print('<input type="hidden" name="action" value="' . $action . '" />');
	if ($id)
		print('<input type="hidden" name="id" value="' . $id . '" />');
}

function list_hooks() {
	$result = send_request(HTTP_METH_GET, 'hooks', '');
	print('Hooks:<br />');
	print('<table border="1">');
	print('<tr><th>Id</th><th>Active</th><th>Events</th><th>URL</th><th>Edit</th><th>Delete</th></tr>');
	foreach ($result as $hook) {
		$id = $hook->id;
		print('<tr>');
		print('<td>' . $id . '</td>');
		print('<td>' . ($hook->active ? '' : 'not ') . 'active</td>');
		print('<td>' . join(' ', $hook->events) . '</td>');
		if ($hook->name != 'web')
			print('<td colspan="3">' . $hook->name . ' hooks not editable here</td>');
		else {
			print('<td><a href="' . $hook->config->url. '">' . htmlentities($hook->config->url) . '</a></td>');
			print('<td>');
			form_header('hook-' . $id, 'edit', $id);
			print('<input type="submit" value="Edit" />');
			print('</form>');
			print('</td>');
			print('<td>');
			form_header('delete-hook-' . $id, 'delete', $id);
			print('<input type="submit" value="Delete" />');
			print('</form>');
			print('</td>');
		}
		print('</tr>');
	}
	print('</table>');
	form_header('add-new-hook', 'new');
	print('<input type="submit" value="Add" />');
	print('</form>');
}

function edit_hook($id) {
	global $user, $pwd, $repository;
	if ($id)
		$hook = send_request(HTTP_METH_GET, 'hooks/' . $id, '');
	else
		$hook = (object)array('active' => '1', 'events' => array('push', 'issues', 'issue_comment', 'commit_comment', 'pull_request', 'gollum', 'watch', 'download', 'fork', 'fork_apply', 'member', 'public'), 'secret' => '');
	print('Edit hook ' . ($id ? $id : '') . '<br />');
	form_header($id ? 'edit-hook-' . $id : 'add-hook', $id ? 'patch' : 'add', $id);
	print('<dl>');
	if ($id) {
		print('<dt><strong>Created at</strong></dt><dd>' . $hook->created_at . '</dd>');
		print('<dt><strong>Updated at</strong></dt><dd>' . $hook->updated_at . '</dd>');
	}
	print('<dt><strong>Active</strong></dt><dd><input type="checkbox" name="active" ' . ($hook->active ? 'checked ' : '') . '" /></dd>');
	print('<dt><strong>Event</strong></dt><dd><input type="text" name="event" size="80" value="' . join(' ', $hook->events) . '" /></dd>');
	print('<dt><strong>URL</strong></dt><dd><input type="text" name="url" size="80" value="' . $hook->config->url . '" /></dd>');
	print('<dt><strong>Secret</strong></dt><dd><input type="text" name="secret" value="' . $hook->config->secret . '" /></dd>');
	print('</dl>');
	print('<input type="submit" value="' . ($id ? 'Update' : 'Add') . '" />');
	print('</form>');
}

function patch_hook($id, $event, $url, $secret, $active) {
	$body = json_encode(array(
		'name' => 'web',
		'config' => array(
			'content_type' => 'json',
			'url' => $url,
			'secret' => $secret
		),
		'events' => split(' ', $event),
		'active' => $active
	));
	send_request(HTTP_METH_PATCH, 'hooks/' . $id, $body);
}

function add_hook($event, $url, $secret, $active) {
	$body = json_encode(array(
		'name' => 'web',
		'config' => array(
			'content_type' => 'json',
			'url' => $url,
			'secret' => $secret
		),
		'events' => split(' ', $event),
		'active' => $active
	));
	send_request(HTTP_METH_POST, 'hooks', $body);
}

function delete_hook($id) {
	send_request(HTTP_METH_DELETE, 'hooks/' . $id, '');
}

function print_thing($thing) {
	if (is_array($thing)) {
		print('<ol start="0">');
		foreach ($thing as $value) {
			print('<li>');
			print_thing($value);
			print('</li>');
		}
		print('</ol>');
	}
	elseif (is_object($thing)) {
		print('<dl>');
		foreach ($thing as $key => $value) {
			print('<dt><strong>' . $key . '</strong></dt><dd>');
			print_thing($value);
			print('</dd>');
		}
		print('</dl>');
	}
	else
		print($thing);
}

if (!isset($_POST['action']))
	$_POST['action'] = 'list';

if ($_POST['action'] == 'edit')
	edit_hook($_POST['id']);
else if ($_POST['action'] == 'new')
	edit_hook(false);
else {
	if ($_POST['action'] == 'add')
		add_hook($_POST['event'], $_POST['url'], $_POST['secret'], isset($_POST['active']) && $_POST['active'] ? '1' : '0');
	else if ($_POST['action'] == 'patch')
		patch_hook($_POST['id'], $_POST['event'], $_POST['url'], $_POST['secret'], isset($_POST['active']) && $_POST['active'] ? '1' : '0');
	else if ($_POST['action'] == 'delete')
		delete_hook($_POST['id']);
	list_hooks();
}
?>
