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
	private $users_slug            = 'vip_dashboard_users';
	private $parent_page           = 'index.php';
	private $per_page              = 20;

	function __construct() {
		add_action( 'init',                                array( &$this, 'init' ) );
		add_action( 'admin_init',                          array( &$this, 'admin_init' ) );
    
		add_action( 'admin_menu',                          array( &$this, 'register_menus' ) );
    
		add_action( 'wpmu_activate_user',                  array( &$this, 'add_to_blogs' ), 5, 3 );
		add_action( 'wpmu_signup_user_notification_email', array( &$this, 'invite_message' ), 5, 5 );

		//Handle GET and POST requests
		add_action( 'admin_init', array( &$this, 'handle_promote_users_form' ) );
		add_action( 'admin_init', array( &$this, 'handle_add_users_form' ) );

		add_filter('set-screen-option', array( &$this, 'vip_dashboard_users_per_page_save' ), 10, 3);
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
		$hook = add_submenu_page( $this->parent_page, esc_html__( 'Users', 'vip-dashboard' ), esc_html__( 'Users', 'vip-dashboard' ), 'manage_options', $this->users_slug, array( &$this, 'users_page' ) );
		add_action( "load-$hook", array( &$this, 'vip_dashboard_users_per_page' ) );
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

		?>

		<div class=wrap>
			<?php screen_icon(); ?>

			<h2><?php esc_html_e( 'Users', 'vip-dashboard' ); ?></h2>

			<div class='col-container'>

				<div id='col-right'>
					<div class='col-wrap'>
						<form>
							<input type=hidden name=page value="vip_dashboard_users">
							<?php $vip_users_table->search_box( __( 'Search Users', 'vip-dashboard' ), 'user' ); ?>
						</form>
						<form action="" method="get">
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

		<form>
			<?php wp_nonce_field( 'vip-dashboard-add-users', 'vip-dashboard-add-users' ) ?>
			<input type=hidden name=action value="adduser">

			<div class="form-field">
				<label for="emails"><?php _e( 'Emails', 'vip-dashboard' ); ?></label>
				<textarea id="emails" name="emails"></textarea>
				<p>Invite up to 10 email addresses separated by commas.</p>
			</div>

			<div class="form-field">
				<label for="adduser-role"><?php _e( 'Role', 'vip-dashboard' ); ?></label>
				<select name="new_role" id="new_role-role">
					<?php wp_dropdown_roles( get_option('default_role') ); ?>
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
						echo "<label class='selectit'><input style='width:auto' type=checkbox name=blogs[] value='{$blog->blog_id}'> {$blog->blogname}</label>";
					}
				?>
				</fieldset>
			</div>

			<div class="form-field">
				<label for="message"><?php _e( 'Message', 'vip-dashboard' ); ?></label>
				<textarea id="message" name="message" rows=5 placeholder="Check out my blog!"></textarea>
				<p>(Optional) You can enter a custom message of up to 500 characters that will be included in the invitation to the user(s).</p>
			</div>
			
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

		if ( !isset($_REQUEST['action']) || 'adduser' != $_REQUEST['action'] )
			return;

		check_admin_referer( 'vip-dashboard-add-users', 'vip-dashboard-add-users' );

		$blogids = array_map('intval', $_REQUEST['blogs']);
		$emails = array_map( 'sanitize_email', explode(',', $_REQUEST['emails']) );
		$role = sanitize_key($_REQUEST['new_role']);
		$message = sanitize_text_field($_REQUEST['message']);

		if ( ! current_user_can('create_users') )
			wp_die(__('Cheatin&#8217; uh?'));

		if ( has_action('vip_dashboard_users_invite') )
			do_action('vip_dashboard_users_invite', $blogids, $emails, $role, $message);
		else {
			$redirect = $this->create_users($blogids, $emails, $role, $message);
		}

		wp_redirect( $redirect );
		exit();
	}

	/**
	 * Create users, send notification emails, add entry to signups table
	 */
	public function create_users($blogids, $emails, $role, $message) {
		$redirect = "admin.php?page=vip_dashboard_users";

		foreach ( $emails as $email ) {
			$username = explode('@', $email);
			$username = sanitize_user($username[0], true);

			// Adding a new user to this blog
			$user_details = wpmu_validate_user_signup( $username, $email );
			unset( $user_details[ 'errors' ]->errors[ 'user_email_used' ] );
			if ( is_wp_error( $user_details[ 'errors' ] ) && !empty( $user_details[ 'errors' ]->errors ) ) {
				$add_user_errors = $user_details[ 'errors' ];
			} else {
				$new_user_login = apply_filters('pre_user_login', sanitize_user(stripslashes($username), true));
				if ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() ) {
					add_filter( 'wpmu_signup_user_notification', '__return_false' ); // Disable confirmation email
				}
				wpmu_signup_user( $new_user_login, $email, array( 'add_to_blogs' => $blogids, 'new_role' => $role, 'message' => $message ) );
				if ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() ) {
					$key = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $new_user_login, $email ) );
					wpmu_activate_signup( $key );
					$redirect = add_query_arg( array('update' => 'addnoconfirmation'), 'user-new.php' );
				} else {
					$redirect = add_query_arg( array('update' => 'newuserconfimation'), 'user-new.php' );
				}
			}
		}

		return $redirect;
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

		if ( !isset($_REQUEST['action']) || 'modify' != $_REQUEST['action'] )
			return;

		check_admin_referer( 'vip-dashboard-bulk-users', 'vip-dashboard-bulk-users' );
		$redirect = "admin.php?page=vip_dashboard_users";

		$blogids = array_map('intval', $_REQUEST['blogs']);
		$userids = array_map('intval', $_REQUEST['users']);
		$role = sanitize_key($_REQUEST['new_role']);

		if ( ! current_user_can( 'promote_users' ) )
			wp_die( __( 'You can&#8217;t edit that user.', 'vip-dashboard' ) );

		if ( empty($_REQUEST['users']) || 'modify' != $_REQUEST['action'] ) {
			wp_redirect($redirect);
			exit();
		}

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[$_REQUEST['new_role']] ) && 'none' != $_REQUEST['new_role'] )
			wp_die(__( 'You can&#8217;t give users that role.', 'vip-dashboard' ));

		foreach ( $userids as $id ) {
			if ( ! current_user_can('promote_user', $id) )
				wp_die(__( 'You can&#8217;t edit that user.', 'vip-dashboard' ));
			// The new role of the current user must also have the promote_users cap or be a multisite super admin
			if ( $id == $current_user->ID && ! $wp_roles->role_objects[ $role ]->has_cap('promote_users')
				&& ! ( is_multisite() && is_super_admin() ) ) {
					$update = 'err_admin_role';
					continue;
			}
		}

		$update = $this->promote_users($blogids, $userids, $role);

		wp_redirect(add_query_arg('update', $update, $redirect));
		exit();
	}

	/**
	 * Add/remote/modify role of specified users on specified sites
	 */
	public function promote_users($blogids = array(), $userids = array(), $role) {
		$update = 'promote';

		foreach ( $userids as $id ) {

			foreach ( $blogids as $blogid ) {
				if ( $role == 'none' )
					remove_user_from_blog($id, $blogid);
				else
					add_user_to_blog($blogid, $id, $role);					
			}
		}

		return $update;		
	}
}

$VIP_Dashboard = new VIP_Dashboard();