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
		//TODO: register js and css

		if ( isset($_REQUEST['action']) && 'promote' == $_REQUEST['action'] ) {
			$this->promote_users();
		} elseif ( isset($_REQUEST['action']) && 'createuser' == $_REQUEST['action'] ) {
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

		?>

		<div class=wrap>
			<?php screen_icon(); // TODO: icon ?>

			<h2><?php esc_html_e( 'Users', 'vip-dashboard' ); ?></h2>

			<div class='col-container'>

				<div id='col-right'>
					<div class='col-wrap'><?php $vip_users_table->display(); ?></div>
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

	public function users_for_blogs($ids = array()) {
		$users = array();
		
		foreach ( $ids as $id ) {
			foreach ( get_users( array( 'blog_id' => $id ) ) as $user ) {
				if ( !in_array( $user, $users ) ) {
					$users[] = $user;
				}
			}
		}

		return $users;
	}

	//TODO: replace with a "real" admin table
	public function temp_table($users, $crossreference = array()) {
		echo '<form action="" method="post" name="addusers" id="addusers">';

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<select name="remove">';
		echo '<option value="-1" selected="selected">Bulk Actions</option>';
		echo '<option value="remove">Remove</option>';
		echo '</select>';
		submit_button( __( 'Apply '), 'secondary', 'docation', false );
		echo '</div>';
		echo '</div>';

		echo "<table class='wp-list-table widefat fixed'>";

		echo "<thead>";
		echo "<tr>";
		echo "<th><input type=checkbox></th>";
		echo "<th>Username</th>";
		echo "<th>Name</th>";
		echo "<th>E-mail</th>";
		echo "<th>Sites</th>";
		echo "</tr>";
		echo "<thead>";

		echo "<tfoot>";
		echo "<tr>";
		echo "<th><input type=checkbox></th>";
		echo "<th>Username</th>";
		echo "<th>Name</th>";
		echo "<th>E-mail</th>";
		echo "<th>Sites</th>";
		echo "</tr>";
		echo "<tfoot>";

		echo "<tbody>";

		echo "<tr id=bulk-edit class='inline-edit-row inline-edit-row-post inline-edit-post bulk-edit-row bulk-edit-row-post bulk-edit-post inline-editor'>";

		echo "<td colspan=5>";
		$this->promote_users_form($crossreference);
		echo "</td>";

		echo "</tr>";

		foreach ( $users as $user ) {
			$blogs = get_blogs_of_user($user->ID);
			echo "<tr>";

			echo "<td><input type=checkbox name=users[] value='" . $user->ID . "'></td>";
			echo "<td>" . $user->user_login . "</td>";
			echo "<td>" . $user->user_nicename . "</td>";
			echo "<td>" . $user->user_email . "</td>";
			echo "<td>";

			foreach ( $blogs as $blog )
				if( in_array($blog->site_id, $crossreference) )
					echo $blog->blogname . "<br>";

			echo "</td>";

			echo "</tr>";
		}

		echo "</tbody>";

		echo "</table>";
		echo "</form>";
	}

	public function promote_users_form($blogs = array()) { ?>
			<?php wp_nonce_field( 'bulk-users' ) ?>
			<input type=hidden name=action value="promote">

			<?php
				foreach( $blogs as $id ) {
					$blog = get_blog_details($id);
					echo "<input id='blog-{$blog->blog_id}' type=checkbox name=blogs[] value='{$blog->blog_id}'> <label for='blog-{$blog->blog_id}'>{$blog->blogname}</label><br>";
				}
			?>

			<label for="adduser-role"><?php _e('Role'); ?></label>
			<select name="new_role" id="new_role-role">
				<?php wp_dropdown_roles( get_option('default_role') ); ?>
			</select>
			
			<?php submit_button( __( 'Update '), 'primary', 'changeit', false ); ?>
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
			
			<?php submit_button( __( 'Add User '), 'primary', 'adduser', true ); ?>
		</form>

<?php
	}

	public function promote_users() {
		check_admin_referer('bulk-users');
		$redirect = "admin.php?page=vip_dashboard_users";

		if ( ! current_user_can( 'promote_users' ) )
			wp_die( __( 'You can&#8217;t edit that user.' ) );

		if ( empty($_REQUEST['users']) ) {
			wp_redirect($redirect);
			exit();
		}

		$editable_roles = get_editable_roles();
		if ( empty( $editable_roles[$_REQUEST['new_role']] ) )
			wp_die(__('You can&#8217;t give users that role.'));

		$userids = $_REQUEST['users'];
		$update = 'promote';
		foreach ( $userids as $id ) {
			$id = (int) $id;

			if ( ! current_user_can('promote_user', $id) )
				wp_die(__('You can&#8217;t edit that user.'));
			// The new role of the current user must also have the promote_users cap or be a multisite super admin
			if ( $id == $current_user->ID && ! $wp_roles->role_objects[ $_REQUEST['new_role'] ]->has_cap('promote_users')
				&& ! ( is_multisite() && is_super_admin() ) ) {
					$update = 'err_admin_role';
					continue;
			}

			foreach ( $_POST['blogs'] as $blogid ) {
				$user = new WP_User($id, null, $blogid);
				if ( 'remove' == $_POST['remove'] )
					$user->remove_all_caps();
				elseif( isset($_POST['changeit']) )
					$user->set_role($_REQUEST['new_role']);
			}
		}

		wp_redirect(add_query_arg('update', $update, $redirect));
		exit();
	}
}

$VIP_Dashboard = new VIP_Dashboard();