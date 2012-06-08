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

  function column_username($item){
  	  $actions = array(
	      'edit'      => '<a href="#">Edit</a>',
	      'delete'    => '<a href="#">Delete</a>',
	  );
 
	  return sprintf('%1$s <span style="color:silver">(id:%2$s)</span>%3$s',
	      /*$1%s*/ $item->user_login,
	      /*$2%s*/ $item->ID,
	      /*$3%s*/ $this->row_actions($actions)
	  );
	}

	function column_cb($item){
      return sprintf(
          '<input type="checkbox" name="%1$s[]" value="%2$s" />',
          /*$1%s*/ $this->_args['singular'],
          /*$2%s*/ $item->ID
      );
  }

  function column_name($item){
      return $item->user_nicename;
  }

  function column_email($item){
      return $item->user_email;
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
          'username' => __('Username'),
          'name'     => __('Name'),
          'email'  	 => __('E-mail'),
          'sites' 	 => __('Sites'),
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
          'modify'    => 'Modify'
      );
      return $actions;
  }

  function process_bulk_action() {
		switch( $this->current_action() ) {
			case 'modify':
				wp_die("Modify Bulk Action");
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
			'blog_id' 	 => null,
			'meta_query' => $meta_query,
			'number' 		 => $per_page,
			'offset'		 => $per_page * ($paged-1),
			'search' 		 => $usersearch,
			'fields'		 => 'all_with_meta'
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
 
}