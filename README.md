Bulk User Management
====================

Bulk User Management allows a set of specified users to manage permissions across all your sites. Users from every site in your network are displayed in a list and can be added to or removed from any of your sites. Any users that aren’t currently in your network can still be invited to any of your blogs through the normal invite process. After they accept, they can be managed in bulk across the network.

To enable the plugin, you will need to pass an array of user logins to `wpcom_vip_bulk_user_management_whitelist()`. Bulk User Management will only be available for these users and they will be able to manage all the users in your network regardless of their specified capability on those sites.

```
wpcom_vip_bulk_user_management_whitelist( array( 'user1', 'user2', 'user3' ) );
```

To manage users, use the checkboxes to select which users to manage and pick an action from the “Bulk Actions” dropdown. This will reveal a bulk edit section in the table. Select the sites that the changes should apply to and pick a role if necessary. Clicking update will apply the changes and refresh the page.

Filters
-----

* `bulk_user_management_blogs` - array of sites that can be managed
* `bulk_user_management_parent_page` - sets parent page
* `bulk_user_management_admin_users` - array of users by id that the plugin is active for
* `bulk_user_management_admins_by_username` - array of users by username that the plugin is active for
