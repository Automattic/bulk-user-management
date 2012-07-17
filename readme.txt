=== Bulk User Management ===
Contributors: automattic, betzster, danielbachhuber
Tags: admin, users, bulk
Requires at least: 3.4
Tested up to: 3.4.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin that lets you manage users across all your sites from one place on a multisite install.

== Description ==

A plugin that lets you manage users across all your sites from one place on a multisite install.

If you'd like to check out the code and contribute, [join us on GitHub](https://github.com/Automattic/bulk-user-management). Pull requests are more than welcome!

== Installation ==

1. Upload the `bulk-user-management` folder to your plugins directory (e.g. `/wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' menu in WordPress

The following filters will let you customize the plugin:

* `bulk_user_management_blogs` - array of sites that can be managed
* `bulk_user_management_parent_page` - sets parent page
* `bulk_user_management_admin_users` - array of users that the plugin is active for

== Frequently Asked Questions ==

= Why are there no FAQs? =

Because you haven't asked one yet.

== Changelog ==

= 1.0 =
* Initial Release

= 1.0.1 =
* Fix fatal error in PHP 5.2
