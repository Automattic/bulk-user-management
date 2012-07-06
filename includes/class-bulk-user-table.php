<?php

if(!class_exists('WP_List_Table')){
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Bulk_User_Table extends WP_List_Table {

  function __construct(){
    global $status, $page;
            
    //Set parent defaults
    parent::__construct( array(
      'singular'  => 'bulk_user',
      'plural'    => 'bulk_users',
      'ajax'      => true
    ) );
  }

  function no_items() {
    _e( 'No matching users were found', 'bulk-user-management' );
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
    if ( get_current_user_id() == $item->ID ) {
      $actions['edit'] = '<a href="' . admin_url('profile.php') . '">Edit</a>';
    } elseif ( current_user_can('edit_users') ) {
      $actions['edit'] = '<a href="' . add_query_arg( 'user_id', $item->ID, admin_url('user-edit.php') ) . '">Edit</a>';
    }

    $login = is_super_admin( $item->ID ) ? $item->user_login . " - <strong>Super Admin</strong>" : $item->user_login;
    return sprintf( __('%1$s %2$s %3$s', 'bulk-user-management' ),
      /*$1%s*/ get_avatar($item->ID, 32),
      /*$2%s*/ $login,
      /*$3%s*/ $this->row_actions($actions)
    );
  }

  function column_name($item){
    return $item->display_name;
  }

  function column_email($item){
    return sprintf( __( '<a href="mailto:%1$s" title="E-mail %1$s">%1$s</a>', 'bulk-user-management' ), $item->user_email );
  }

  function column_sites($item){
    $blogs = get_blogs_of_user($item->ID);
    $crossreference = $this->get_blog_ids( 'list_users' );
    $sites = '';
    foreach ( $blogs as $blog )
      if( in_array($blog->userblog_id, $crossreference) ) {
        $user = new WP_User( $item->ID, null, $blog->userblog_id );
        $domain = ( '/' == $blog->path ) ? $blog->domain : $blog->domain . $blog->path;
        $sites .= sprintf( '<a href="%s">%s</a> - %s<br>', esc_attr( $blog->siteurl ), $domain, implode( ', ', $user->roles ) );
      } 
    return $sites;
  }

  function get_columns(){
    $columns = array(
      'cb'       => '<input type="checkbox" />',
      'username' => __( 'Username', 'bulk-user-management' ),
      'name'     => __( 'Name', 'bulk-user-management' ),
      'email'    => __( 'E-mail', 'bulk-user-management' ),
      'sites'    => __( 'Sites', 'bulk-user-management' ),
    );
    return $columns;
  }

  function get_sortable_columns() {
    $sortable_columns = array(
      'username' => array( 'user_login', true ),
      'name' => array( 'display_name', false ),
      'email' => array( 'user_email', false )
    );
    return $sortable_columns;
  }

  function get_bulk_actions() {
    $actions = array(
      'modify'    => __( 'Modify', 'bulk-user-management' ),
      'remove'    => __( 'Remove', 'bulk-user-management' )
    );
    return $actions;
  }

  function get_blog_ids( $cap ) {
    $user_id = get_current_user_id();
    $blogs = get_blogs_of_user( $user_id );
    
    $limit = array_map( 'intval', apply_filters( 'bulk_user_management_limit_blogs', array() ) );

    $blog_ids = array();
    foreach ( $blogs as $blog ) {
      $user = new WP_User( $user_id, null, $blog->userblog_id );
      if ( user_can( $user, $cap ) && ( count( $limit ) == 0 || in_array( $blog->userblog_id, $limit ) ) )
        $blog_ids[] = $blog->userblog_id;
    }

    return $blog_ids;
  }

  function prepare_items( $queryitems = true ) {
    global $wpdb;

    $per_page = 20;

    $paged = $this->get_pagenum();

    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    if ( $queryitems ) {

      $blog_ids = $this->get_blog_ids( 'list_users' );

      $query = array();
      foreach ( $blog_ids as $blogid ) {

        $args = array(
          'blog_id' => $blogid
        );

        $users = wp_cache_get( $blogid, 'bum_blog_users' );
        if ( false === $users ) {
          $users = get_users( $args );
          wp_cache_set( $blogid, $users, 'bum_blog_users', 60 * 60 * 24 );
        }

        foreach ( $users as $user ) {
          if ( !in_array( $user, $query ) )
            $query[] = $user;
        }
      }

      // orderby and order
      usort( $query, function( $a, $b ){
        $orderby = isset( $_REQUEST['orderby'] ) ? esc_attr( $_REQUEST['orderby'] ) : 'user_login';
        $order = isset( $_REQUEST['order'] ) && 'desc' == $_REQUEST['order'] ? -1 : 1;

        switch ( $orderby ) {
          case 'display_name':
            $cmp = strnatcmp( strtolower( $a->display_name ), strtolower( $b->display_name ) );
            break;
          case 'user_email':
            $cmp = strnatcmp( strtolower( $a->user_email ), strtolower( $b->user_email ) );
            break;
          case 'user_login':
          default:
            $cmp = strnatcmp( strtolower( $a->user_login ), strtolower( $b->user_login ) );
            break;
        }

        return $cmp * $order;
      });

      // search
      $users = array();
      if ( isset( $_REQUEST['s'] ) && '' != $_REQUEST['s'] ) {
        foreach ( $query as $user ) {
          if ( stristr( $user->user_login, $_REQUEST['s'] ) )
            $users[] = $user;
        }
      } else {
        $users = $query;
      }

      $this->items = array_slice( $users, $per_page * ($paged-1), $per_page);

      $this->set_pagination_args( array(
        "total_items" => count( $query ),
        "per_page" => $per_page,
      ) );

    }

  }

  function has_items() {
    return count($this->items) > 0;
  }

/**
   * Outputs the hidden row displayed when inline editing
   */
  function inline_edit() {
    global $mode;
    $screen = get_current_screen();
  ?>

  <table style="display: none"><tbody id="inlineedit">
    <?php wp_nonce_field( 'bulk-user-management-bulk-users', 'bulk-user-management-bulk-users' ) ?>

    <tr id="bulk-edit" class="inline-edit-row <?php echo "bulk-edit-row" ?>" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <h4><?php _e( 'Bulk Edit', 'bulk-user-management' ) ?></h4>

      <div id="bulk-title-div">
        <div id="bulk-titles"></div>
      </div>
    </div></fieldset>

    <fieldset class="inline-edit-col-middle"><div class="inline-edit-col">
      <span class="title inline-edit-categories-label"><?php _e( 'Sites', 'bulk-user-management' ) ?></span>

      <ul class="cat-checklist site-checklist">
        <?php foreach ( $this->get_blog_ids( 'promote_users' ) as $id ): ?>
          <?php $blog = get_blog_details($id); ?>
          <li><label class="selectit"><input id='blog-<?php echo esc_attr($blog->blog_id); ?>' type=checkbox name=blogs[] value='<?php echo esc_attr($blog->blog_id); ?>'> <?php echo esc_html($blog->blogname); ?></label></li>
        <?php endforeach; ?>
      </ul>
    </div></fieldset>

    <fieldset class="inline-edit-col-right"><div class="inline-edit-col">
      <label class="inline-edit-user">
        <span class="title"><?php _e( 'Role', 'bulk-user-management' ); ?></span>
        <select name="new_role" id="new_role-role">
          <?php wp_dropdown_roles( get_option('default_role') ); ?>
        </select>
      </label>
    </div></fieldset>

    <p class="submit inline-edit-save">
      <a accesskey="c" href="#inline-edit" title="<?php esc_attr_e( 'Cancel' ); ?>" class="button-secondary cancel alignleft"><?php _e( 'Cancel', 'bulk-user-management' ); ?></a>
      <?php submit_button( __( 'Update', 'bulk-user-management' ), 'button-primary alignright', 'bulk_edit', false, array( 'accesskey' => 's' ) ); ?>
      <input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>">
      <span class="error" style="display:none"></span>
      <br class="clear">
    </p>

    </td></tr>

    </tbody></table>
<?php
  }

  /**
   * Outputs hidden row for bulk removing users
   */
  function bulk_remove() {
    global $mode;
    $screen = get_current_screen();
  ?>

  <table style="display: none"><tbody id="inlineedit">
    <?php wp_nonce_field( 'bulk-user-management-bulk-remove-users', 'bulk-user-management-bulk-remove-users' ) ?>

    <tr id="bulk-remove" class="inline-edit-row <?php echo "bulk-edit-row" ?>" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">

    <fieldset class="inline-edit-col-left"><div class="inline-edit-col">
      <h4><?php _e( 'Bulk Edit', 'bulk-user-management' ) ?></h4>

      <div id="bulk-title-div">
        <div id="bulk-titles"></div>
      </div>
    </div></fieldset>

    <fieldset class="inline-edit-col-right-wide"><div class="inline-edit-col">
      <span class="title inline-edit-categories-label"><?php _e( 'Sites', 'bulk-user-management' ) ?></span>

      <ul class="cat-checklist site-checklist">
        <?php foreach ( $this->get_blog_ids( 'remove_users' ) as $id ): ?>
          <?php $blog = get_blog_details($id); ?>
          <li><label class="selectit"><input id='blog-<?php echo esc_attr($blog->blog_id); ?>' type=checkbox name=blogs[] value='<?php echo esc_attr($blog->blog_id); ?>'> <?php echo esc_html($blog->blogname); ?></label></li>
        <?php endforeach; ?>
      </ul>
    </div></fieldset>

    <p class="submit inline-edit-save">
      <a accesskey="c" href="#inline-edit" title="<?php esc_attr_e( 'Cancel' ); ?>" class="button-secondary cancel alignleft"><?php _e( 'Cancel', 'bulk-user-management' ); ?></a>
      <?php submit_button( __( 'Update', 'bulk-user-management' ), 'button-primary alignright', 'bulk_edit', false, array( 'accesskey' => 's' ) ); ?>
      <input type="hidden" name="screen" value="<?php echo esc_attr( $screen->id ); ?>">
      <span class="error" style="display:none"></span>
      <br class="clear">
    </p>

    </td></tr>

    </tbody></table>
<?php
  }
}