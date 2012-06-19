<?php /*

**************************************************************************

Plugin Name:  VIP Dashboard
Plugin URI:   
Description:  
Version:      1.0.0
Author:       Automattic
Author URI:   
License:      GPLv2 or later

Text Domain:  vip-dashboard
Domain Path:  /languages/

**************************************************************************/

include('includes/class-vip-user-table.php');

class VIP_Dashboard {

	public $settings               = array();
	public $default_settings       = array();

	private $version               = '1.0.0';

	private $option_name           = 'vip_dashboard';
	private $page_slug             = 'vip_dashboard_users';
	private $parent_page           = 'index.php';
	private $per_page              = 20;

	function __construct() {
		add_action( 'init',                                array( $this, 'init' ) );
		add_action( 'admin_init',                          array( $this, 'admin_init' ) );
    
		add_action( 'admin_menu',                          array( $this, 'register_menus' ) );
    
    add_action( 'vip_dashboard_users_invite',          array( $this, 'invite_users'), 5, 5 );
		add_action( 'wpmu_activate_user',                  array( $this, 'add_to_blogs' ), 5, 3 );
		add_action( 'wpmu_signup_user_notification_email', array( $this, 'invite_message' ), 5, 5 );

		//Handle GET and POST requests
		add_action( 'admin_init', array( $this, 'handle_promote_users_form' ) );
		add_action( 'admin_init', array( $this, 'handle_add_users_form' ) );

		add_filter('set-screen-option', array( $this, 'vip_dashboard_users_per_page_save' ), 10, 3);
	}

	public function init() {
		$this->default_settings = array(
			
		);
		$this->settings = wp_parse_args( (array) get_option( $this->option_name ), $this->default_settings );

		// Allow the parent page to be filtered
		$this->parent_page = apply_filters('vip_dashboard_users_parent_page', $this->parent_page);
	}

	public function admin_init() {
		wp_register_script( 'vip-dashboard-inline-edit', plugins_url('/js/vip-dashboard-inline-edit.js', __FILE__), array('jquery'), $this->version );
	}

	public function register_menus() {
		$hook = add_submenu_page( $this->parent_page, esc_html__( 'Users', 'vip-dashboard' ), esc_html__( 'Users', 'vip-dashboard' ), 'manage_options', $this->page_slug, array( $this, 'users_page' ) );
		add_action( "load-$hook", array( $this, 'vip_dashboard_users_per_page' ) );
	}

	/**
	 * Set up the screen option for the number of users per page
	 */
	public function vip_dashboard_users_per_page() {
		$option = 'per_page';

		$args = array(
			'label' => 'Users',
			'default' => $this->per_page,
			'option' => 'vip_dashboard_users_per_page'
		);

		add_screen_option( $option, $args );
	}

	/**
	 * Save the screen option for users per page
	 */
	public function vip_dashboard_users_per_page_save($status, $option, $value) {
		if ( 'vip_dashboard_users_per_page' == $option ) return $value;
	}

	/**
	 * Generate the users page
	 */
	public function users_page() {

		$vip_users_table = new VIP_User_Table();
		$vip_users_table->prepare_items();
		wp_enqueue_script('vip-dashboard-inline-edit');

		if ( isset( $_GET['update'] ) ) {
			$messages = array();
			switch ( $_GET['update'] ) {
				case "newuserconfimation":
					$messages[] = __( 'Invitation email sent to new users. A confirmation link must be clicked before their account is created.', 'vip-dashboard' );
					break;
				case "addnoconfirmation":
					$messages[] = __( 'Users have been added to your site.', 'vip-dashboard' );
					break;
				case "addexisting":
					$messages[] = __( 'That user is already a member of this site.', 'vip-dashboard' );
					break;
				case "does_not_exist":
					$messages[] = __( 'Please enter a valid email address.', 'vip-dashboard' );
					break;
				case 'promote':
					$messages[] = __( 'User roles were modified', 'vip-dashboard' );
					break;
				case 'err_admin_role':
					$messages[] = __( 'The new role of the current user must still be able to promote users.', 'vip-dashboard' );
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
			}
		}

		?>

		<div class=wrap>
			<?php screen_icon('users'); ?>

			<h2><?php esc_html_e( 'Users', 'vip-dashboard' ); ?></h2>

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
							<input type=hidden name=page value="vip_dashboard_users">
							<?php $vip_users_table->search_box( __( 'Search Users', 'vip-dashboard' ), 'user' ); ?>
						</form>
						<form action="" method="post">
						<?php $vip_users_table->display(); ?>
						<?php
							if ( $vip_users_table->has_items() )
								$vip_users_table->inline_edit();
						?>
						</form>
					</div>
				</div>

				<div id='col-left'>
					<div class='form-wrap'>
						<h3>Add New User</h3>
						<?php $this->add_users_form(); ?>
					</div>
				</div>

			</div>
		</div>

<?php
	}

	/**
	 * Generate the add users form
	 */
	public function add_users_form() { ?>

		<form action="" method="post">
			<?php wp_nonce_field( 'vip-dashboard-add-users', 'vip-dashboard-add-users' ) ?>
			<input type=hidden name=action value="adduser">

			<div class="form-field">
				<label for="emails"><?php _e( 'Emails', 'vip-dashboard' ); ?></label>
				<textarea id="emails" name="emails"><?php
						if ( isset( $_POST['emails']) ) {
							echo esc_textarea( $_POST['emails'] );
						}
					?></textarea>
				<p>Invite up to 10 email addresses separated by commas.</p>
			</div>

			<div class="form-field">
				<label for="adduser-role"><?php _e( 'Role', 'vip-dashboard' ); ?></label>
				<select name="new_role" id="new_role-role">
					<?php
						$role = isset( $_POST['new_role'] ) ? esc_attr( $_POST['new_role'] ) : get_option('default_role');
						wp_dropdown_roles( $role );
					?>
				</select>
			</div>

			<div class="form-field">
				<?php _e( 'Sites', 'vip-dashboard' ); ?>
				<fieldset>
				<?php
					$vip_users_table = new VIP_User_Table();
					$blogs = $vip_users_table->blog_ids();

					foreach ( $blogs as $id ) {
						$blog = get_blog_details($id);
						$checked = isset( $_POST['blogs'] ) && in_array( $id, $_POST['blogs'] ) ? 'checked' : '';
						printf("<label class='selectit'><input style='width:auto' type=checkbox name=blogs[] value='%d'%s> %s</label>", intval($blog->blog_id), $checked, esc_attr($blog->blogname) );
					}
				?>
				</fieldset>
			</div>

			<div class="form-field">
				<label for="message"><?php _e( 'Message', 'vip-dashboard' ); ?></label>
				<textarea id="message" name="message" rows=5 placeholder="Check out my blog!"><?php
						if ( isset( $_POST['message']) ) {
							echo esc_textarea( $_POST['message'] );
						}
					?></textarea>
				<p>(Optional) You can enter a custom message of up to 500 characters that will be included in the invitation to the user(s).</p>
			</div>

			<?php if ( is_super_admin() ): ?>
			<div class="form-field">
				<label><input style="width:auto" type=checkbox name="noconfirmation"<?php if ( isset( $_POST['noconfirmation'] ) ) echo "checked";?>> <?php _e( 'Skip Confirmation Email', 'vip-dashboard' ); ?></label>
			</div>
			<?php endif; ?>
			
			<?php submit_button( __( 'Add Users', 'vip-dashboard' ), 'primary', 'adduser', true ); ?>
		</form>

<?php
	}

	/**
	 * Validate and sanitize data from the add users form before creating
	 * them and adding them to the correct blogs
	 */
	public function handle_add_users_form() {
		global $wpdb;

		if ( !isset($_REQUEST['action']) || 'adduser' != $_REQUEST['action'] ||
			!isset($_REQUEST['page']) || $this->page_slug != $_REQUEST['page'] )
			return;

		check_admin_referer( 'vip-dashboard-add-users', 'vip-dashboard-add-users' );

		$blogids = array_map('intval', $_REQUEST['blogs']);
		$emails = array_map( 'sanitize_email', explode(',', $_REQUEST['emails']) );
		$role = sanitize_key($_REQUEST['new_role']);
		$message = sanitize_text_field($_REQUEST['message']);
		$noconfirmation =  ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() );

		if ( ! current_user_can('create_users') )
			wp_die(__('Cheatin&#8217; uh?'));

		// Invite users
		do_action('vip_dashboard_users_invite', $blogids, $emails, $role, $message, $noconfirmation);
	}

	public function invite_users( $blogids, $emails, $role, $message, $noconfirmation ) {
		$redirect = add_query_arg( 'page', $this->page_slug, $this->parent_page );
		$errors = $this->create_users($blogids, $emails, $role, $message, $noconfirmation);

			if ( isset( $errors ) ) {
				$_GET['update'] = 'add_user_errors';
				$_POST['errors'] = $errors;
			} else {
				if ( $noconfirmation ) {
					$args = array( 'update' => 'addnoconfirmation' );
				} else {
					$args = array( 'update' => 'newuserconfimation' );
				}
				$redirect = add_query_arg( $args, $redirect );
				wp_redirect( $redirect );
				exit();
			}		
	}

	/**
	 * Create users, send notification emails, add entry to signups table
	 */
	public function create_users($blogids, $emails, $role, $message, $noconfirmation) {
		if ( $noconfirmation ) {
			add_filter( 'wpmu_signup_user_notification', '__return_false' ); // Disable confirmation email
		}

		foreach ( $emails as $email ) {
			$username = explode('@', $email);
			$username = sanitize_user($username[0], true);

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

		check_admin_referer( 'vip-dashboard-bulk-users', 'vip-dashboard-bulk-users' );
		$redirect = add_query_arg( 'page', $this->page_slug, $this->parent_page );

		$blogids = array_map('intval', $_REQUEST['blogs']);
		$userids = array_map('intval', $_REQUEST['users']);
		$role = sanitize_key($_REQUEST['new_role']);

		if ( ! current_user_can( 'promote_users' ) ) {
			$error = new WP_Error( 'no-promote-user-cap', __( 'You can&#8217;t edit that user.', 'vip-dashboard' ) );
			wp_die( $error->get_error_message() );
		}

		if ( empty($_REQUEST['users']) ) {
			wp_redirect($redirect);
			exit();
		}

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[$_REQUEST['new_role']] ) && 'none' != $_REQUEST['new_role'] ) {
			$error = new WP_Error( 'no-editable-role', __( 'You can&#8217;t give users that role.', 'vip-dashboard' ) );
			wp_die( $error->get_error_message() );
		}

		foreach ( $userids as $id ) {
			if ( ! current_user_can('promote_user', $id) ) {
				$error = new WP_Error( 'no-promote-user-cap', __( 'You can&#8217;t edit that user.', 'vip-dashboard' ) );
				wp_die( $error->get_error_message() );
			}
			// The new role of the current user must also have the promote_users cap or be a multisite super admin
			if ( $id == $current_user->ID && ! $wp_roles->role_objects[ $role ]->has_cap('promote_users')
				&& ! ( is_multisite() && is_super_admin() ) ) {
					//TODO: this isn't actually doing anything, the way it is currently written
					// maybe just pull those id's out of the array?
					$update = 'err_admin_role';
					continue;
			}
		}

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
				if ( $role == 'none' )
					remove_user_from_blog($id, $blogid);
				else
					add_user_to_blog($blogid, $id, $role);					
			}
		}
	}
}

$VIP_Dashboard = new VIP_Dashboard();