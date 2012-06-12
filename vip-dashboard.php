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

include('includes/VIP_User_Table.php');

class VIP_Dashboard {

	public $settings               = array();
	public $default_settings       = array();

	// Using "private" for read-only functionality. See __get().
	private $version               = '1.0.0';

	private $option_name           = 'vip_dashboard';
	private $dashboard_slug    		 = 'vip_dashboard';
	private $users_slug						 = 'vip_dashboard_users';

	function __construct() {
		add_action( 'init',           array( &$this, 'init' ) );
		add_action( 'admin_init', 		array( &$this, 'admin_init' ) );

		add_action( 'admin_menu',     array( &$this, 'register_menus' ) );
	}

	public function init() {
		$this->default_settings = array(
			
		);

		$this->settings = wp_parse_args( (array) get_option( $this->option_name ), $this->default_settings );
	}

	public function admin_init() {
		wp_register_script( 'vip-dashboard-inline-edit', plugins_url('/js/vip-dashboard-inline-edit.js', __FILE__), array('jquery') );

		if ( isset($_REQUEST['form']) && 'promote' == $_REQUEST['form'] ) {
			$this->promote_users();
		} elseif ( isset($_REQUEST['form']) && 'createuser' == $_REQUEST['form'] ) {
			$this->create_user();
		}
	}

	public function register_menus() {
		add_menu_page( esc_html__( 'VIP Dashboard', 'vip-dashboard' ), esc_html__( 'VIP Dashboard', 'vip-dashboard' ), 'administrator', $this->dashboard_slug, plugins_url('vip-dashboard/images/icon.png'), array( &$this, 'dashboard_page'), 3 );
		add_submenu_page( $this->dashboard_slug, esc_html__( 'Users', 'vip-dashboard' ), esc_html__( 'Users', 'vip-dashboard' ), 'manage_options', $this->users_slug, array( &$this, 'users_page' ) );
	}

	public function dashboard_page() {

	}

	public function users_page() {

		$vip_users_table = new VIP_User_Table();
		$vip_users_table->prepare_items();
		wp_enqueue_script('vip-dashboard-inline-edit');

		?>

		<div class=wrap>
			<?php screen_icon(); // TODO: icon ?>

			<h2><?php esc_html_e( 'Users', 'vip-dashboard' ); ?></h2>

			<div class='col-container'>

				<div id='col-right'>
					<div class='col-wrap'>
						<form action="" method="get">
						<?php $vip_users_table->search_box( __( 'Search Users', 'vip-dashboard' ), 'user' ); ?>
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

	public function add_users_form() { ?>

		<form>
			<?php wp_nonce_field( 'bulk-users' ) ?>
			<input type=hidden name=action value="adduser">

			<div class="form-field">
				<label for="emails">Emails</label>
				<textarea id="emails" name="emails"></textarea>
				<p>Invite up to 10 email addresses separated by commas.</p>
			</div>

			<div class="form-field">
				<label for="adduser-role"><?php _e('Role'); ?></label>
				<select name="new_role" id="new_role-role">
					<?php wp_dropdown_roles( get_option('default_role') ); ?>
				</select>
			</div>

			<div class="form-field">
				<label for="message">Message</label>
				<textarea id="message" name="message" rows=5 placeholder="Check out my blog!"></textarea>
				<p>(Optional) You can enter a custom message of up to 500 characters that will be included in the invitation to the user(s).</p>
			</div>
			
			<?php submit_button( __( 'Add User', 'vip-dashboard' ), 'primary', 'adduser', true ); ?>
		</form>

<?php
	}

	public function promote_users() {
		global $current_user, $wp_roles;

		check_admin_referer('bulk-users');
		$redirect = "admin.php?page=vip_dashboard_users";

		if ( ! current_user_can( 'promote_users' ) )
			wp_die( __( 'You can&#8217;t edit that user.', 'vip-dashboard' ) );

		if ( empty($_REQUEST['users']) || 'modify' != $_REQUEST['action'] ) {
			wp_redirect($redirect);
			exit();
		}

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[$_REQUEST['new_role']] ) && 'none' != $_REQUEST['new_role'] )
			wp_die(__( 'You can&#8217;t give users that role.', 'vip-dashboard' ));

		$userids = $_REQUEST['users'];
		$update = 'promote';
		foreach ( $userids as $id ) {
			$id = (int) $id;

			if ( ! current_user_can('promote_user', $id) )
				wp_die(__( 'You can&#8217;t edit that user.', 'vip-dashboard' ));
			// The new role of the current user must also have the promote_users cap or be a multisite super admin
			if ( $id == $current_user->ID && ! $wp_roles->role_objects[ $_REQUEST['new_role'] ]->has_cap('promote_users')
				&& ! ( is_multisite() && is_super_admin() ) ) {
					$update = 'err_admin_role';
					continue;
			}

			foreach ( $_REQUEST['blogs'] as $blogid ) {
				$role = $_REQUEST['new_role'];
				if ( $role == 'none' )
					remove_user_from_blog($id, $blogid);
				else
					add_user_to_blog($blogid, $id, $role);					
			}
		}

		wp_redirect(add_query_arg('update', $update, $redirect));
		exit();
	}
}

$VIP_Dashboard = new VIP_Dashboard();