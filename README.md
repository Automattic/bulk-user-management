Bulk User Management
====================

A WordPress plugin that lets you manage users across all your sites from one place on a multisite install.

Filters
-----

* `pre_user_login` - (same as core) applied to the user's URL prior to saving in the database.
* `bulk_user_management_limit_blogs` - array of blogs to limit against.
* `bulk_user_management_parent_page` - sets parent page.

Actions
-------

* `bulk_user_management_invite_form` - prints a form to invite users
* `bulk_user_management_invite` - replace invite method for standard invite form
	* `$blogids` - (array) IDs of blogs to invite to
	* `$emails` - (array) email addresses to invite
	* `$users` - (array) usernames to invite
	* `$role` - (string) role to set users on specified blogs
	* `$message` - (string) custom message for invite email
	* `$noconfirmation` - (bool) don't send confirmation email on true
