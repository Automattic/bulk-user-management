<?php

if(!class_exists('WP_List_Table')){
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class VIP_User_Table extends WP_List_Table {

  function __construct(){
    global $status, $page;
            
    //Set parent defaults
    parent::__construct( array(
        'singular'  => 'vip_user',
        'plural'    => 'vip_users'
    ) );
  }

  function no_items() {
    _e( 'No matching users were found', 'vip-dashboard' );
  }

  function column_cb($item){
    return sprintf(
        '<input type="checkbox" name="%1$s[]" value="%2$s" />',
        /*$1%s*/ $this->_args['singular'],
        /*$2%s*/ $item->ID
    );
  }

  function column_username($item){
    $actions = array();
    $actions['edit'] = '<a href="#">Edit</a>';
    if ( get_current_user_id() !== $item->ID )
      $actions['delete'] = '<a href="#">Delete</a>';
 
    return sprintf( __('%1$s %2$s <span style="color:silver">(id:%3$s)</span>%4$s', 'vip-dashboard' ),
        /*$1%s*/ get_avatar($item->ID, 32),
        /*$2%s*/ $item->user_login,
        /*$3%s*/ $item->ID,
        /*$4%s*/ $this->row_actions($actions)
    );
  }

  function column_name($item){
    return $item->user_firstname . ' ' . $item->user_lastname;
  }

  function column_email($item){
    return sprintf( __('<a href="mailto:%1$s" title="E-mail %1$s">%1$s</a>', 'vip-dashboard' ), $item->user_email );
  }

  function column_sites($item){
    $blogs = get_blogs_of_user($item->ID);

    // TODO: replace with blog stickers API
    $crossreference = $this->blog_ids();

    $sites = '';
    foreach ( $blogs as $blog )
      if( in_array($blog->site_id, $crossreference) )
          $sites .= $blog->blogname . "<br>";
    return $sites;
  }

  function get_columns(){
    $columns = array(
        'cb'       => '<input type="checkbox" />',
        'username' => __( 'Username', 'vip-dashboard' ),
        'name'     => __( 'Name', 'vip-dashboard' ),
        'email'    => __( 'E-mail', 'vip-dashboard' ),
        'sites'    => __( 'Sites', 'vip-dashboard' ),
    );
    return $columns;
  }

  function get_sortable_columns() {
    $sortable_columns = array(
        'username' => array('username',false),
        'name'     => array('name',false),
        'email'    => array('email',false)
    );
    return $sortable_columns;
  }

  function get_bulk_actions() {
    $actions = array(
        'modify'    => __( 'Modify', 'vip-dashboard' ),
    );
    return $actions;
  }

  function process_bulk_action() {
    switch( $this->current_action() ) {
      case 'modify':
        wp_die( __("Modify Bulk Action"), 'vip-dashboard' );
        break;
    }
  }

  // TODO: replace with blog stickers API
  function blog_ids() {
    $user_id = get_current_user_id();
    $blogs = get_blogs_of_user($user_id, false);
    $blog_ids = array();
    foreach ( $blogs as $blog )
      $blog_ids[] = $blog->userblog_id;

    return $blog_ids;
  }

  function prepare_items() {
    global $wpdb, $usersearch;

    $usersearch = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';
    
    $per_page = $this->get_items_per_page( 'users_per_page' );

    $paged = $this->get_pagenum();

    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    $this->process_bulk_action();

    // TODO: replace with blog stickers API
    $blog_ids = $this->blog_ids();    

    $meta_query = array();
    foreach ( $blog_ids as $blog_id )
      $meta_query[]['meta_key'] = $wpdb->get_blog_prefix( $blog_id ). '_capabilities';

    $args = array(
      'blog_id'    => null,
      'meta_query' => $meta_query,
      'number'     => $per_page,
      'offset'     => $per_page * ($paged-1),
      'search'     => $usersearch,
      'fields'     => 'all_with_meta'
    );

    if ( '' !== $args['search'] )
      $args['search'] = '*' . $args['search'] . '*';

    if ( isset( $_REQUEST['orderby'] ) )
      $args['orderby'] = $_REQUEST['orderby'];

    if ( isset( $_REQUEST['order'] ) )
      $args['order'] = $_REQUEST['order'];

    $query = new WP_User_Query( $args );

    $this->items = $query->get_results();

    $this->set_pagination_args( array(
      "total_items" => $query->total_users,
      "per_page" => $per_page,
    ) );
  }

  function has_items() {
    return count($this->items) > 0;
  }

/**
   * Outputs the hidden row displayed when inline editing
   *
   * @since 3.1.0
   */
  function inline_edit() {
    global $mode;
    $screen = get_current_screen();
  ?>

  <form action="" method="post" name="addusers" id="addusers"><table style="display: none"><tbody id="inlineedit">
    <?php wp_nonce_field( 'bulk-users' ) ?>
    <input type=hidden name=form value="promote">

    <tr id="bulk-edit" class="inline-edit-row inline-edit-row-<?php echo "inline-edit-$screen->post_type bulk-edit-row bulk-edit-$screen->post_type" ?>" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <h4><?php _e( 'Bulk Edit', 'vip-dashboard' ) ?></h4>

      <div id="bulk-title-div">
        <div id="bulk-titles"></div>
      </div>
    </div></fieldset>

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <span class="title inline-edit-categories-label"><?php _e( 'Bulk Edit', 'vip-dashboard' ) ?></span>

      <ul class="cat-checklist category-checklist">
        <?php foreach ( $this->blog_ids() as $id ): //TODO: replace with blog stickers api ?>
          <?php $blog = get_blog_details($id); ?>
          <li><label class="selectit"><input id='blog-<?php echo $blog->blog_id; ?>' type=checkbox name=blogs[] value='<?php echo $blog->blog_id; ?>'> <?php echo $blog->blogname; ?></label></li>
        <?php endforeach; ?>
      </ul>
    </div></fieldset>

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <label class="inline-edit-user">
        <span class="title"><?php _e( 'Role', 'vip-dashboard' ); ?></span>
        <select name="new_role" id="new_role-role">
          <option value="none"> &mdash; None &mdash; </option>
          <?php wp_dropdown_roles( get_option('default_role') ); ?>
        </select>
      </label>
    </div></fieldset>

    <p class="submit inline-edit-save">
      <a accesskey="c" href="#inline-edit" title="<?php esc_attr_e( 'Cancel' ); ?>" class="button-secondary cancel alignleft"><?php _e( 'Cancel', 'vip-dashboard' ); ?></a>
      <?php submit_button( __( 'Update', 'vip-dashboard' ), 'button-primary alignright', 'bulk_edit', false, array( 'accesskey' => 's' ) ); ?>
      <input type="hidden" name="post_view" value="<?php echo esc_attr( $m ); ?>" />
      <input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>" />
      <span class="error" style="display:none"></span>
      <br class="clear" />
    </p>

    </td></tr>

    </tbody></table></form>
<?php
  }
}