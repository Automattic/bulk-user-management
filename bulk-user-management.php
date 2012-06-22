<?php /*

**************************************************************************

Plugin Name:  Multisite Bulk User Management
Plugin URI:   
Description:  
Version:      1.0.0
Author:       Automattic
Author URI:   
License:      GPLv2 or later

Text Domain:  bulk-user-management
Domain Path:  /languages/

**************************************************************************/

include('includes/class-bulk-user-table.php');

class Bulk_User_Management {

	private $version               = '1.0.0';
	private $page_slug             = 'bulk_user_management';
	private $parent_page           = 'index.php';
	private $per_page              = 20;

	function __construct() {
		add_action( 'init',                                array( $this, 'init' ) );
		add_action( 'admin_init',                          array( $this, 'admin_init' ) );
    
		add_action( 'admin_menu',                          array( $this, 'register_menus' ) );
		add_action( 'admin_notices',                       array( $this, 'multisite_notice') );
    	
    	add_action( 'bulk_user_management_invite_form',     array( $this, 'invite_users_form' ) );
    	add_action( 'bulk_user_management_invite',          array( $this, 'invite_users'), 5, 6 );
		add_action( 'wpmu_activate_user',                  array( $this, 'add_to_blogs' ), 5, 3 );
		add_action( 'wpmu_signup_user_notification_email', array( $this, 'invite_message' ), 5, 5 );

		//Handle GET and POST requests
		add_action( 'admin_init', array( $this, 'handle_promote_users_form' ) );
		add_action( 'admin_init', array( $this, 'handle_remove_users_form' ) );
		add_action( 'admin_init', array( $this, 'handle_invite_users_form' ) );

		add_filter( 'set-screen-option', array( $this, 'bulk_user_management_per_page_save' ), 10, 3 );
	}

	public function init() {
		// Allow the parent page to be filtered
		$this->parent_page = apply_filters('bulk_user_management_parent_page', $this->parent_page);
	}

	public function admin_init() {
		wp_register_style( 'bulk-user-management', plugins_url('/css/bulk-user-management.css', __FILE__), false, $this->version );
		wp_register_script( 'bulk-user-management-inline-edit', plugins_url('/js/bulk-user-management-inline-edit.js', __FILE__), array('jquery'), $this->version );
		wp_register_script( 'ajax-user-box', plugins_url('/js/ajax-user-box.js', __FILE__), array('jquery'), $this->version );
	}

	public function register_menus() {
		$hook = add_submenu_page( $this->parent_page, esc_html__( 'Bulk User Management', 'bulk-user-management' ), esc_html__( 'User Management', 'bulk-user-management' ), 'manage_options', $this->page_slug, array( $this, 'users_page' ) );
		add_action( "load-$hook", array( $this, 'bulk_user_management_per_page' ) );
	}

	/**
	 * Set up the screen option for the number of users per page
	 */
	public function bulk_user_management_per_page() {
		$option = 'per_page';

		$args = array(
			'label' => 'Users',
			'default' => $this->per_page,
			'option' => 'bulk_user_management_per_page'
		);

		add_screen_option( $option, $args );
	}

	/**
	 * Save the screen option for users per page
	 */
	public function bulk_user_management_per_page_save($status, $option, $value) {
		if ( 'bulk_user_management_per_page' == $option ) return $value;
	}

	public function multisite_notice() {
		global $pagenow;
		if ( !is_multisite() && current_user_can( 'install_plugins' ) && ( 'plugins.php' == $pagenow || $this->page_slug == $_GET['page'] ) ) {
			echo '<div class="error">
			     <p>Please enable multisite to use the User Management plugin.</p>
			 </div>';
		}
	}

	/**
	 * Generate the users page
	 */
	public function users_page() {

		$bulk_users_table = new Bulk_User_Table();
		$bulk_users_table->prepare_items();
		wp_enqueue_script('bulk-user-management-inline-edit');
		wp_enqueue_style('bulk-user-management');

		if ( isset( $_GET['update'] ) ) {
			$messages = array();
			switch ( $_GET['update'] ) {
				case "newuserconfimation":
					$messages[] = __( 'Invitation email sent to new users. A confirmation link must be clicked before their account is created.', 'bulk-user-management' );
					break;
				case "addnoconfirmation":
					$messages[] = __( 'Users have been added to your site.', 'bulk-user-management' );
					break;
				case "addexisting":
					$messages[] = __( 'That user is already a member of this site.', 'bulk-user-management' );
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
					$messages[] =  __( 'The new role of the current user must still be able to promote users.', 'bulk-user-management' );
					break;
				case 'add_user_errors':
					foreach ( $_POST['errors'] as $email => $error ) {
						$email = sanitize_email( $email );
						if ( is_wp_error( $error ) ) {
							$error_messages = $error->get_error_messages();
							foreach ( $error_messages as $message ) {
								$messages[] = $message . ' (' . $email . ')';
							}
						}
					}
					break;
				case 'invite_form_error':
					if ( is_wp_error( $_POST[ 'error' ] ) ) {
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

			<div class='col-container'>

				<div id='col-right'>
					<div class='col-wrap'>
						<form>
							<input type=hidden name=page value="bulk_user_management">
							<?php $bulk_users_table->search_box( __( 'Search Users', 'bulk-user-management' ), 'user' ); ?>
						</form>
						<form action="" method="post">
						<?php $bulk_users_table->display(); ?>
						<?php
							if ( $bulk_users_table->has_items() ) {
								$bulk_users_table->inline_edit();
								$bulk_users_table->bulk_remove();
							}
						?>
						</form>
					</div>
				</div>

				<div id='col-left'>
					<div class='form-wrap'>
						<h3>Add New User</h3>
						<?php do_action('bulk_user_management_invite_form'); ?>
					</div>
				</div>

			</div>
		</div>

<?php
	}

	/**
	 * Generate the add users form
	 */
	public function invite_users_form() {
		wp_enqueue_script('ajax-user-box');
	?>

		<form action="" method="post">
			<?php wp_nonce_field( 'bulk-user-management-add-users', 'bulk-user-management-add-users' ) ?>
			<input type=hidden name=action value="adduser">

			<div id="new-user-and-email" class="form-field">
				<p class="row" style="display:none"><input type=text name="usernames[]" placeholder="Username"> <input type=text name="emails[]" placeholder="Email"></p>
				<?php
					$i=0;
					if ( isset( $_REQUEST[ 'emails' ] ) ) {
						foreach( $_REQUEST[ 'emails' ] as $key => $email ) {
							$email = sanitize_email( $email );
							$user = sanitize_user( $_REQUEST['usernames'][$key] );
							if ( $email == "" && $user == "" ) continue;

							$i++;
							printf( '<p class="row"><input type=text name="usernames[]" placeholder="Username" value="%s"> <input type=text name="emails[]" placeholder="Email" value="%s"></p>', $user, $email );
						}
					}
					for( $i=$i; $i<4; $i++ ) {
						if ( isset( $emails[ $i ] ) || isset( $users[ $i ] ) ) continue;
						echo '<p class="row"><input type=text name="usernames[]" placeholder="Username"> <input type=text name="emails[]" placeholder="Email"></p>';
					}
				?>
			</div>

			<div class="form-field">
				<label for="adduser-role"><?php _e( 'Role', 'bulk-user-management' ); ?></label>
				<select name="new_role" id="new_role-role">
					<?php
						$role = isset( $_POST['new_role'] ) ? esc_attr( $_POST['new_role'] ) : get_option('default_role');
						wp_dropdown_roles( $role );
					?>
				</select>
			</div>

			<div class="form-field">
				<?php _e( 'Sites', 'bulk-user-management' ); ?>
				<fieldset>
				<?php
					$bulk_users_table = new Bulk_User_Table();
					$blogs = $bulk_users_table->get_blog_ids();

					foreach ( $blogs as $id ) {
						$blog = get_blog_details($id);
						$checked = isset( $_POST['blogs'] ) && in_array( $id, $_POST['blogs'] ) ? 'checked' : '';
						printf("<label class='selectit'><input type=checkbox name=blogs[] value='%d'%s> %s</label>", intval($blog->blog_id), $checked, esc_attr($blog->blogname) );
					}
				?>
				</fieldset>
			</div>

			<div class="form-field">
				<label for="message"><?php _e( 'Message', 'bulk-user-management' ); ?></label>
				<textarea id="message" name="message" rows=5 placeholder="Check out my blog!"><?php
						if ( isset( $_POST['message']) ) {
							echo esc_textarea( $_POST['message'] );
						}
					?></textarea>
				<p>(Optional) You can enter a custom message of up to 500 characters that will be included in the invitation to the user(s).</p>
			</div>

			<?php if ( is_super_admin() ): ?>
			<div class="form-field">
				<label><input type=checkbox name="noconfirmation"<?php if ( isset( $_POST['noconfirmation'] ) ) echo "checked";?>> <?php _e( 'Skip Confirmation Email', 'bulk-user-management' ); ?></label>
			</div>
			<?php endif; ?>
			
			<?php submit_button( __( 'Add Users', 'bulk-user-management' ), 'primary', 'adduser', true ); ?>
		</form>

<?php
	}

	/**
	 * Validate and sanitize data from the add users form before creating
	 * them and adding them to the correct blogs
	 */
	public function handle_invite_users_form() {
		global $wpdb;

		if ( !isset($_REQUEST['action']) || 'adduser' != $_REQUEST['action'] ||
			!isset($_REQUEST['page']) || $this->page_slug != $_REQUEST['page'] )
			return;

		if ( empty( $_REQUEST[ 'blogs' ] ) ) {
			$_GET[ 'update' ] = 'invite_form_error';
			$_POST[ 'error' ] = new WP_Error( __( 'No blogs were specified.', 'bulk-user-management' ) );
			return;
		}

		$emails = isset( $_REQUEST[ 'emails' ] ) ? array_filter( $_REQUEST[ 'emails' ] ) : false;
		if ( empty( $emails ) ) {
			$_GET[ 'update' ] = 'invite_form_error';
			$_POST[ 'error' ] = new WP_Error( __( 'No users were specified.', 'bulk-user-management' ) );
			return;
		}

		foreach ( $emails as $email ) {
			if ( ! is_email( $email ) ) {
				$_GET[ 'update' ] = 'invalid_email';
				return;
			}
		}

		check_admin_referer( 'bulk-user-management-add-users', 'bulk-user-management-add-users' );

		$blogids = array_map( 'intval', $_REQUEST['blogs'] );
		$emails = array_filter( array_map( 'sanitize_email', $_REQUEST['emails'] ) );
		$users = array_filter( array_map( 'sanitize_user', $_REQUEST['usernames'] ) );
		$role = sanitize_key( $_REQUEST['new_role'] );
		$message = sanitize_text_field( $_REQUEST['message'] );
		$noconfirmation =  ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() );

		foreach ( $blogids as $blog ) {
			if ( ! current_user_can_for_blog( $blog, 'create_users') ) {
				$error = new WP_Error( __( 'Cheatin&#8217; uh?', 'bulk-user-management' ) );
				wp_die( $error->get_error_message() );
			}
		}

		// Invite users
		do_action('bulk_user_management_invite', $blogids, $emails, $users, $role, $message, $noconfirmation);
	}

	public function invite_users( $blogids, $emails, $usernames, $role, $message, $noconfirmation ) {
		$redirect = add_query_arg( 'page', $this->page_slug, $this->parent_page );

		// TODO: add javascript username suggestion and auto fill email
		$invites = array();
		foreach ( $emails as $key => $email ) {
			if ( $user = email_exists($email) ) {
				unset( $emails[ $key ], $usernames[ $key ] );
				$invites[] = $user;
			}
		}

		foreach ( $invites as $userid ) {
			foreach ( $blogids as $blogid ) {
				add_user_to_blog( $$blogid, $userid, $role );
			}
		}

		$errors = $this->create_users($blogids, $emails, $usernames, $role, $message, $noconfirmation);

		if ( isset( $errors ) ) {
			$_GET['update'] = 'add_user_errors';
			$_POST['errors'] = $errors;
			return;
		} else {
			if ( $noconfirmation || empty( $emails ) ) {
				$args = array( 'update' => 'addnoconfirmation' );
			} else {
				$args = array( 'update' => 'newuserconfimation' );
			}
		}

		$redirect = add_query_arg( $args, $redirect );
		wp_redirect( $redirect );
		exit();	
	}

	/**
	 * Create users, send notification emails, add entry to signups table
	 */
	public function create_users($blogids, $emails, $usernames, $role, $message, $noconfirmation) {
		global $wpdb;

		if ( $noconfirmation ) {
			add_filter( 'wpmu_signup_user_notification', '__return_false' ); // Disable confirmation email
		}

		foreach ( $emails as $key => $email ) {
			$username = $usernames[$key];

			// Adding a new user to this blog
			$user_details = wpmu_validate_user_signup( $username, $email );
			unset( $user_details[ 'errors' ]->errors[ 'user_email_used' ] );
			if ( is_wp_error( $user_details[ 'errors' ] ) && !empty( $user_details[ 'errors' ]->errors ) ) {
				$add_user_errors[$email] = $user_details[ 'errors' ];
			} else {
				$new_user_login = apply_filters('pre_user_login', sanitize_user(stripslashes($username), true));
				wpmu_signup_user( $new_user_login, $email, array( 'add_to_blogs' => $blogids, 'new_role' => $role, 'message' => $message ) );
				if ( $noconfirmation ) {
					$key = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $new_user_login, $email ) );
					wpmu_activate_signup( $key );
				}
			}
		}

		if ( isset( $add_user_errors ) ) {
			return $add_user_errors;
		}
			
	}

	/**
	 * Add user to the blogs that were listed in the invitation process
	 * after they activate their account
	 */
	public function add_to_blogs($userid, $password, $meta) {
		global $current_site;
		if ( !empty( $meta[ 'add_to_blogs' ] ) ) {
			$blogids = $meta[ 'add_to_blogs' ];
			$role = $meta[ 'new_role' ];

			remove_user_from_blog($userid, $current_site->blog_id); // remove user from main blog.

			foreach( $blogids as $blog_id )
				add_user_to_blog( $blog_id, $userid, $role );

			update_user_meta( $userid, 'primary_blog', $blogids[0] );
		}
	}

	/**
	 * Optionally add a custom message to the end of the invitation
	 */
	public function invite_message($message, $user, $email, $key, $meta) {
		$meta = unserialize($meta);
		if ( !empty( $meta[ 'message' ] ) ) {
			return $message . $meta[ 'message' ];
		}
		return $message;
	}

	/**
	 * Validate and sanitize data from the bulk edit form before
	 * actually assigning new roles to users
	 */
	public function handle_promote_users_form() {
		global $current_user, $wp_roles;
		$update = "promote";

		if ( !isset($_REQUEST['action']) || 'modify' != $_REQUEST['action'] ||
			!isset($_REQUEST['page']) || $this->page_slug != $_REQUEST['page'] )
			return;

		check_admin_referer( 'bulk-user-management-bulk-users', 'bulk-user-management-bulk-users' );
		$redirect = add_query_arg( 'page', $this->page_slug, $this->parent_page );

		$blogids = array_map('intval', $_REQUEST['blogs']);
		$userids = array_map('intval', $_REQUEST['users']);
		$role = sanitize_key($_REQUEST['new_role']);

		if ( ! current_user_can( 'promote_users' ) ) {
			$error = new WP_Error( 'no-promote-user-cap', __( 'You can&#8217;t edit that user.', 'bulk-user-management' ) );
			wp_die( $error->get_error_message() );
		}

		if ( empty($_REQUEST['users']) ) {
			wp_redirect($redirect);
			exit();
		}

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[$_REQUEST['new_role']] ) && 'none' != $_REQUEST['new_role'] ) {
			$error = new WP_Error( 'no-editable-role', __( 'You can&#8217;t give users that role.', 'bulk-user-management' ) );
			wp_die( $error->get_error_message() );
		}

		$errors = array();
		foreach ( $blogids as $blogid ) {
			if ( ! current_user_can_for_blog($blogid, 'promote_user') ) {
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

		wp_redirect( add_query_arg('update', $update, $redirect) );
		exit();
	}

	/**
	 * Add/remote/modify role of specified users on specified sites
	 */
	public function promote_users($blogids = array(), $userids = array(), $role) {
		foreach ( $userids as $id ) {
			foreach ( $blogids as $blogid ) {
				add_user_to_blog($blogid, $id, $role);					
			}
		}
	}

	/**
	 * Validate and sanitize a request to remove users, then
	 * call `remove_users()` to actually remove the users
	 */
	public function handle_remove_users_form() {
		$update = "remove";

		if ( !isset($_REQUEST['action']) || 'remove' != $_REQUEST['action'] ||
			!isset($_REQUEST['page']) || $this->page_slug != $_REQUEST['page'] )
			return;

		check_admin_referer( 'bulk-user-management-bulk-remove-users', 'bulk-user-management-bulk-remove-users' );
		$redirect = add_query_arg( 'page', $this->page_slug, $this->parent_page );

		$blogids = array_map('intval', $_REQUEST['blogs']);
		$userids = array_map('intval', $_REQUEST['users']);

		if ( ! current_user_can( 'remove_users' ) ) {
			$error = new WP_Error( 'no-promote-user-cap', __( 'You can&#8217;t edit that user.', 'bulk-user-management' ) );
			wp_die( $error->get_error_message() );
		}

		if ( empty($_REQUEST['users']) ) {
			wp_redirect($redirect);
			exit();
		}

		$this->remove_users($blogids, $userids);

		wp_redirect( add_query_arg('update', $update, $redirect) );
		exit();
	}

	/**
	 * Remove users in `$userids` from blogs in `$blogids` with `remove_user_from_blog()`
	 */
	public function remove_users($blogids = array(), $userids = array()) {
		foreach ( $userids as $userid ) {
			foreach ( $blogids as $blogid ) {
				remove_user_from_blog($userid, $blogid);
			}
		}
	}
}

$Bulk_User_Management = new Bulk_User_Management();