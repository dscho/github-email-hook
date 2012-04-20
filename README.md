This project offers a PHP script sending mails in response to GitHub events

To register it to be called upon GitHub events, open _hooks.php_ in a web browser, enter your GitHub credentials and the name of the repository for which you want events to be handled.

Then add a hook, keeping the events in the event list for which you want the handling to trigger.

After that you need to set up a _mail-settings.php_ file (read _sample.mail-settings.php_ for inspiration).

# Requirements

 * It requires _pecl-mail_ (or as Debian calls it, _php-mail_)
 * _hooks.php_ requires _pecl-http_.

# Limitations

It cannot properly verify the signature that signs the payload with our secret since the pecl-hash module failed to compile for this developer.
