<?php /*

**************************************************************************

Plugin Name:  Multisite Bulk User Management
Plugin URI:   http://wordpress.org/extend/plugins/bulk-user-management/
Description:  A plugin that lets you manage users across all your sites from one place on a multisite install
Version:      1.1
Author:       Automattic
Author URI:   http://automattic.com/wordpress-plugins/
License:      GPLv2 or later

Network:      true
Text Domain:  bulk-user-management
Domain Path:  /languages/

**************************************************************************/

include('includes/class-bulk-user-table.php');

class Bulk_User_Management {

	const VERSION        = '1.1';
	const PAGE_SLUG      = 'bulk_user_management';
	const PER_PAGE       = 20;

	private $parent_page = 'index.php';
	private $invite_page = 'user-new.php';

	function __construct() {
		add_action( 'init',                                array( $this, 'init' ) );
		add_action( 'admin_print_scripts',                 array( $this, 'admin_print_scripts' ) );
		add_action( 'admin_enqueue_scripts',               array( $this, 'enqueue_scripts' ) );

		add_action( 'admin_menu',                          array( $this, 'register_menus' ) );
		add_action( 'admin_notices',                       array( $this, 'multisite_notice') );
			
		add_action( 'wpmu_activate_user',                  array( $this, 'add_to_blogs' ), 5, 3 );
		add_action( 'wpmu_signup_user_notification_email', array( $this, 'invite_message' ), 5, 5 );

		// Invalidate cache when a user is added or removed from a site
		add_action( 'add_user_to_blog',                    array( $this, 'invalidate_cache' ), 5, 3 );
		add_action( 'remove_user_from_blog',               array( $this, 'invalidate_cache' ), 5, 2 );

		// Handle GET and POST requests
		add_action( 'admin_init', array( $this, 'handle_promote_users_form' ) );
		add_action( 'admin_init', array( $this, 'handle_remove_users_form' ) );

		add_action( 'wp_ajax_bulk_user_management_show_form', array( $this, 'show_users' ) );

		add_filter( 'set-screen-option', array( $this, 'bulk_user_management_set_option' ), 10, 3 );
	}

	public function init() {
		// Allow the parent page to be filtered
		$this->parent_page = apply_filters('bulk_user_management_parent_page', $this->parent_page);
		$this->invite_page = apply_filters('bulk_user_management_invite_page', $this->invite_page);
	}

	public function admin_print_scripts() { ?>
		<script>
			var bulk_user_management_images = "<?php echo plugins_url( 'images', __FILE__ ); ?>";
		</script>
<?php }

	public function enqueue_scripts() {
		wp_register_style( 'bulk-user-management', plugins_url('/css/bulk-user-management.css', __FILE__), false, self::VERSION );
		wp_register_script( 'bulk-user-management-inline-edit', plugins_url('/js/bulk-user-management-inline-edit.js', __FILE__), array('jquery'), self::VERSION );
		
		if ( isset( $_REQUEST['page'] ) && self::PAGE_SLUG == $_REQUEST['page'] ) {
			wp_enqueue_script('bulk-user-management-inline-edit');
			wp_enqueue_style('bulk-user-management');
		}
	}

	public function register_menus() {
		if ( $this->current_user_can_bulk_edit() ) {
			$hook = add_submenu_page( $this->parent_page, esc_html__( 'Bulk User Management', 'bulk-user-management' ), esc_html__( 'User Management', 'bulk-user-management' ), 'manage_options', self::PAGE_SLUG, array( $this, 'users_page' ) );
			add_action( "load-$hook", array( $this, 'per_page' ) );
		}
	}

	public function per_page() {
		$option = 'per_page';

		$args = array(
			'label' => __( 'Users', 'bulk-user-management' ),
			'default' => self::PER_PAGE,
			'option' => 'bulk_user_management_per_page'
		);

		add_screen_option( $option, $args );
	}

	public function bulk_user_management_set_option( $status, $option, $value ) {
		if ( 'bulk_user_management_per_page' == $option ) return $value;
	}

	/**
	 * Display a notice if it's not multisite
	 */
	public function multisite_notice() {
		global $pagenow;
		if ( !is_multisite() && current_user_can( 'install_plugins' ) && ( 'plugins.php' == $pagenow || self::PAGE_SLUG == $_GET['page'] ) ) {
			echo '<div class="error">
				<p>Please enable multisite to use the User Management plugin.</p>
			</div>';
		}
	}

	public function show_users() {
		$bulk_users_table = new Bulk_User_Table();
		$bulk_users_table->prepare_items();
		$bulk_users_table->display();
		if ( $bulk_users_table->has_items() ) {
			$bulk_users_table->inline_edit();
			$bulk_users_table->bulk_remove();
		}
		exit();
	}

	public function invalidate_cache( $u, $remove_blogid, $blogid = false ) {
		if ( false === $blogid ) // remove_user_from_blog
			wp_cache_delete( $remove_blogid, 'bum_blog_users' );
		else // add_user_to_blog
			wp_cache_delete( $blogid, 'bum_blog_users' );
	}

	/**
	 * Generate the users page
	 */
	public function users_page() {

		$bulk_users_table = new Bulk_User_Table();
		$bulk_users_table->prepare_items( false );

		$messages = array();
		if ( isset( $_GET['addexisting'] ) ) {
			$messages[] = __( 'Some users were already members of the specified sites.', 'bulk-user-management' );
		}

		if ( isset( $_GET['update'] ) ) {
			switch ( $_GET['update'] ) {
				case "newuserconfimation":
					$messages[] = __( 'Invitation email sent to new users. A confirmation link must be clicked before their account is created.', 'bulk-user-management' );
					break;
				case "addnoconfirmation":
					$messages[] = __( 'Users have been added to your site.', 'bulk-user-management' );
					break;			
				case "invalid_email":
					$messages[] = __( 'Please enter a valid email address.', 'bulk-user-management' );
					break;
				case 'promote':
					$messages[] = __( 'User roles were modified.', 'bulk-user-management' );
					break;
				case 'remove':
					$messages[] = __( 'Users were removed.', 'bulk-user-management' );
					break;
				case 'err_admin_role':
					$messages[] = __( 'The new role of the current user must still be able to promote users.', 'bulk-user-management' );
					break;
				case 'user_email_pair':
					$messages[] = __( 'Each new user must have an email address specified.', 'bulk-user-management' );
					break;
				case 'cant-remove-current':
					$messages[] = __( "Can't remove the current user", 'bulk-user-management' );
					break;
				case 'add_user_errors':
					foreach ( $_POST['errors'] as $email => $error ) {
						$email = sanitize_email( $email );
						if ( is_wp_error( $error ) ) {
							// It's an error, we're safe
							$error_messages = $error->get_error_messages();
							foreach ( $error_messages as $message ) {
								$messages[] = $message . ' (' . $email . ')';
							}
						}
					}
					break;
				case 'invite_form_error':
					if ( is_wp_error( $_POST[ 'error' ] ) ) {
						// It's an error, we're safe
						$error = $_POST[ 'error' ];
						$messages[] = $error->get_error_code();
					}
					break;
			}
		}

		?>

		<div class=wrap>
			<?php screen_icon('users'); ?>

			<h2><?php esc_html_e( 'Bulk User Management', 'bulk-user-management' ); ?></h2>

			<?php
				if ( !empty ( $messages ) ) {
					foreach( $messages as $msg ) {
						echo '<div id="message" class="updated below-h2"><p>' . $msg . '</p></div>';
					}
				}
			?>

			<form>
				<input type=hidden name=page value="bulk_user_management">
				<p class="search-box">
					<label class="screen-reader-text" for="user-search-input">Search Users:</label>
					<input type="search" id="user-search-input" name="s" value="<?php if ( isset( $_REQUEST['s'] ) ) echo esc_attr( $_REQUEST['s'] ); ?>">
					<input type="submit" name="" id="search-submit" class="button" value="Search Users">
				</p>
			</form>
			<form action="" method="post">
			<?php $bulk_users_table->display(); ?>
			<?php
				$bulk_users_table->inline_edit();
				$bulk_users_table->bulk_remove();
			?>
			</form>

			<p class="description">To add a user to your network, add them with the <a href="<?php echo admin_url( $this->invite_page ); ?>">invite form</a> and come back here to manage their access to all of your sites</p>

		</div>

<?php
	}

	/**
	 * Validate and sanitize data from the bulk edit form before
	 * actually assigning new roles to users. Check that the current user
	 * can promote users on the target blogs and that their new role
	 * can promote users if it is changing. Pass valid `$blogs`,
	 * `$userids`, and `$role` to `promote_users()` if there were no errors.
	 */
	public function handle_promote_users_form() {
		global $current_user, $wp_roles;
		$update = "promote";

		// Make sure we should be handling the promote users form
		if ( !isset($_REQUEST['action']) || 'modify' != $_REQUEST['action'] ||
			!isset($_REQUEST['page']) || self::PAGE_SLUG != $_REQUEST['page'] )
			return;

		// Check the nonce
		check_admin_referer( 'bulk-user-management-bulk-users', 'bulk-user-management-bulk-users' );

		// Set up the base redirect
		$redirect = esc_url_raw( add_query_arg( 'page', self::PAGE_SLUG, $_SERVER['REQUEST_URI'] ) );

		// List of users to edit can't be empty
		if ( empty($_REQUEST['users']) ) {
			wp_safe_redirect($redirect);
			exit();
		}

		// Sanitize data
		$blogids = array_map('intval', $_REQUEST['blogs']);
		$userids = array_map('intval', $_REQUEST['users']);
		$role = sanitize_key($_REQUEST['new_role']);

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[ $role ] ) ) {
			$error = new WP_Error( 'no-editable-role', __( 'You can&#8217;t give users that role.', 'bulk-user-management' ) );
			wp_die( $error->get_error_message() );
		}

		// Verify the current user can promote users on all target blogs
		$errors = array();
		foreach ( $blogids as $blogid ) {
			if ( ! ( $this->current_user_can_bulk_edit() && in_array( $blogid, Bulk_User_Table::get_blog_ids( 'promote_user' ) ) ) && ! current_user_can_for_blog( $blogid, 'promote_user' ) ) {
				$error = new WP_Error( 'no-promote-user-cap', sprintf( __( 'You can&#8217;t edit users on that site.', 'bulk-user-management' ) ) );
				// Just throw an error because that shouldn't have been possible
				wp_die( $error->get_error_message() );
			}
		}

		foreach ( $userids as $userid ) {
			// The new role of the current user must also have the promote_users cap or be a multisite super admin,
			// so make sure `$role` can still promote users if the current user is in `$userids`
			if ( $userid == $current_user->ID && ! $wp_roles->role_objects[ $role ]->has_cap('promote_users')
				&& ! ( is_multisite() && is_super_admin() ) ) {
					$update = 'err_admin_role';
					continue;
			}
		}

		if ( 'promote' == $update )
			$this->promote_users($blogids, $userids, $role);

		wp_safe_redirect( add_query_arg('update', $update, $redirect) );
		exit();
	}

	/**
	 * Change the role of all `$userids` on all `$blogids` to `$role`.
	 */
	public function promote_users($blogids = array(), $userids = array(), $role) {
		foreach ( $userids as $id ) {
			foreach ( $blogids as $blogid ) {
				add_user_to_blog( $blogid, $id, $role );
			}
		}
	}

	/**
	 * Validate and sanitize a request to remove users. Check
	 * that the current user can remove users on all target sites.
	 * Then call `remove_users()` to actually remove the users.
	 */
	public function handle_remove_users_form() {
		$update = "remove";

		// Check that we should be handling the remove users form
		if ( !isset($_REQUEST['action']) || 'remove' != $_REQUEST['action'] ||
			!isset($_REQUEST['page']) || self::PAGE_SLUG != $_REQUEST['page'] )
			return;

		// Check the nonce
		check_admin_referer( 'bulk-user-management-bulk-remove-users', 'bulk-user-management-bulk-remove-users' );

		// Set up the base redirect
		$redirect = esc_url_raw( add_query_arg( 'page', self::PAGE_SLUG, $_SERVER['REQUEST_URI'] ) );

		// List of users can't be empty
		if ( empty($_REQUEST['users']) ) {
			wp_safe_redirect($redirect);
			exit();
		}

		// Sanitize data
		$blogids = array_map('intval', $_REQUEST['blogs']);
		$userids = array_map('intval', $_REQUEST['users']);

		// Don't let a user remove themself
		if ( in_array( get_current_user_id(), $userids ) )
			$update = 'cant-remove-current';

		// Check that the current user can remove users on all target blogs
		$errors = array();
		foreach ( $blogids as $blogid ) {
			if ( ! ( $this->current_user_can_bulk_edit() && in_array( $blogid, Bulk_User_Table::get_blog_ids( 'remove_users' ) ) ) && ! current_user_can_for_blog( $blogid, 'remove_users' ) ) {
				$error = new WP_Error( 'no-remove-user-cap', sprintf( __( 'You can&#8217;t remove users on that site.', 'bulk-user-management' ) ) );
				// Just throw an error because that shouldn't have been possible
				wp_die( $error->get_error_message() );
			}
		}

		if ( 'remove' == $update )
			$this->remove_users($blogids, $userids);

		wp_safe_redirect( add_query_arg('update', $update, $redirect) );
		exit();
	}

	/**
	 * Remove users in `$userids` from blogs in `$blogids` with `remove_user_from_blog()`
	 */
	public function remove_users($blogids = array(), $userids = array()) {
		foreach ( $userids as $userid ) {
			foreach ( $blogids as $blogid ) {
				remove_user_from_blog( $userid, $blogid );
			}
		}
	}

	public static function current_user_can_bulk_edit() {
		if ( is_super_admin() )
			return true;

		// Add users by username
		$admins = array_map( 'sanitize_user', apply_filters( 'bulk_user_management_admins_by_username', array() ) ); 
		if ( in_array( wp_get_current_user()->user_login, $admins ) ) 
			return true;

		// Add users by id
		$admins = array_map( 'intval', apply_filters( 'bulk_user_management_admin_users', array() ) );
		if( in_array( get_current_user_id(), $admins ) )
			return true;

		return false;
	}
}

$Bulk_User_Management = new Bulk_User_Management();