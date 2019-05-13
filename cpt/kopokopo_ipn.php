<?php
/**
 * @package KopoKopo For WooCommerce
 * @subpackage Payments CPT
 * @author Mauko Maunde < hi@mauko.co.ke >
 * @since 0.19.04
 */

// Register Custom Post Type
add_action( 'init', 'kopokopo_custom_post_type', 0 );
function kopokopo_custom_post_type() {

	$labels = array(
		'name'                  => _x( 'KopoKopo Payments', 'Post Type General Name', 'kopokopo' ),
		'singular_name'         => _x( 'Payment', 'Post Type Singular Name', 'kopokopo' ),
		'menu_name'             => __( 'KopoKopo', 'kopokopo' ),
		'name_admin_bar'        => __( 'KopoKopo IPN', 'kopokopo' ),
		'archives'              => __( 'Item Archives', 'kopokopo' ),
		'attributes'            => __( 'Item Attributes', 'kopokopo' ),
		'parent_item_colon'     => __( 'Parent Item:', 'kopokopo' ),
		'all_items'             => __( 'Payments', 'kopokopo' ),
		'add_new_item'          => __( 'Add New Item', 'kopokopo' ),
		'add_new'               => __( 'Add New', 'kopokopo' ),
		'new_item'              => __( 'New Item', 'kopokopo' ),
		'edit_item'             => __( 'Edit Item', 'kopokopo' ),
		'update_item'           => __( 'Update Item', 'kopokopo' ),
		'view_item'             => __( 'View Item', 'kopokopo' ),
		'view_items'            => __( 'View Items', 'kopokopo' ),
		'search_items'          => __( 'Search Item', 'kopokopo' ),
		'not_found'             => __( 'Not found', 'kopokopo' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'kopokopo' ),
		'featured_image'        => __( 'Featured Image', 'kopokopo' ),
		'set_featured_image'    => __( 'Set featured image', 'kopokopo' ),
		'remove_featured_image' => __( 'Remove featured image', 'kopokopo' ),
		'use_featured_image'    => __( 'Use as featured image', 'kopokopo' ),
		'insert_into_item'      => __( 'Insert into item', 'kopokopo' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'kopokopo' ),
		'items_list'            => __( 'Items list', 'kopokopo' ),
		'items_list_navigation' => __( 'Items list navigation', 'kopokopo' ),
		'filter_items_list'     => __( 'Filter items list', 'kopokopo' ),
	);
	$args = array(
		'label'                 => __( 'Payment', 'kopokopo' ),
		'description'           => __( 'KopoKopo Payments IPN', 'kopokopo' ),
		'labels'                => $labels,
		'supports'              => array(),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 20,
		'menu_icon'             => 'dashicons-money',
		'show_in_admin_bar'     => false,
		'show_in_nav_menus'     => false,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'    	=> 'post',
		'capabilities'       	=> array( 'create_posts' => false, 'edit_posts' => true, 'delete_post' => true ),
		'map_meta_cap'       	=> true,
	);
	register_post_type( 'kopokopo_ipn', $args );

}

/**
 * A filter to add custom columns and remove built-in
 * columns from the edit.php screen.
 * 
 * @access public
 * @param Array $columns The existing columns
 * @return Array $filtered_columns The filtered columns
 */
add_filter( 'manage_kopokopo_ipn_posts_columns', 'filter_kopokopo_payments_table_columns' );
function filter_kopokopo_payments_table_columns( $columns )
{
	//$columns['service_name'] 		= "Service";
	//$columns['shortcode'] 		= "Shortcode";
	$columns['reference'] 		= "Reference";
	$columns['transaction'] 		= "Transaction ID";
	$columns['timestamp'] 		= "Date";
	$columns['transaction_type'] 		= "Type";
	$columns['amount'] 		= "Amount";
	$columns['customer'] 		= "Customer";
	$columns['phone'] 		= "Phone";
	$columns['account_number'] 		= "Account No";
	unset( $columns['title'] );
	unset( $columns['date'] );
	return $columns;
}

/**
 * Render custom column content within edit.php table on event post types.
 * 
 * @access public
 * @param String $column The name of the column being acted upon
 * @return void
 */
add_action( 'manage_posts_custom_column','kopokopo_payments_table_column_content', 10, 2 );
function kopokopo_payments_table_column_content( $column_id, $post_id )
{
	$order_id = get_post_meta( $post_id, '_order_id', true );
	switch ( $column_id ) {

		case 'service_name':
		echo ( $value = get_post_meta( $post_id, '_service_name', true ) ) ? $value : "N/A";
		break;

		case 'shortcode':
		echo ( $value = get_post_meta( $post_id, '_shortcode', true ) ) ? $value : "N/A";
		break;

		case 'reference':
		echo ( $value = get_post_meta( $post_id, '_reference', true ) ) ? $value : "N/A";
		break;
		
		case 'transaction':
		echo ( $value = get_post_meta( $post_id, '_transaction', true ) ) ? $value : "N/A";
		break;
		
		case 'timestamp':
		echo ( $value = date('M jS, Y \a\t H:i', strtotime(get_post_meta( $post_id, '_timestamp', true ) ))) ? $value : "N/A";
		break;
		
		case 'transaction_type':
		echo ( $value = get_post_meta( $post_id, '_transaction_type', true ) ) ? $value : "N/A";
		break;
		
		case 'amount':
		echo ( $value = get_post_meta( $post_id, '_amount', true ) ) ? $value : "N/A";
		break;
		
		case 'customer':
		echo ( $value = get_post_meta( $post_id, '_customer', true ) ) ? $value : "N/A";
		break;
		
		case 'phone':
		echo ( $value = get_post_meta( $post_id, '_phone', true ) ) ? $value : "N/A";
		break;
		
		case 'account_number':
		echo ( $value = get_post_meta( $post_id, '_account_number', true ) ) ? $value : "N/A";
		break;
	}
}

/**
 * Make custom columns sortable.
 * 
 * @access public
 * @param Array $columns The original columns
 * @return Array $columns The filtered columns
 */
add_filter( 'manage_edit-kopokopo_ipn_sortable_columns', 'kopokopo_payments_columns_sortable' );
function kopokopo_payments_columns_sortable( $columns ) 
{
	$columns['service_name'] 		= "Service";
	$columns['shortcode'] 		= "Shortcode";
	$columns['reference'] 		= "Reference";
	$columns['transaction'] 		= "Transaction ID";
	$columns['timestamp'] 		= "Date";
	$columns['transaction_type'] 		= "Type";
	$columns['amount'] 		= "Amount";
	$columns['customer'] 		= "Customer";
	$columns['phone'] 		= "Phone";
	$columns['account_number'] 		= "Account No";
	return $columns;
}


/**
 * Remove actions from columns.
 * 
 * @access public
 * @param Array $actions Actions to remove
 */
add_filter( 'post_row_actions', 'kopokopo_remove_row_actions', 10, 1 );
function kopokopo_remove_row_actions( $actions )
{
	if( get_post_type() === 'kopokopo_ipn' ){
		unset( $actions['edit'] );
		unset( $actions['view'] );
		unset( $actions['inline hide-if-no-js'] );
	}
	
	return $actions;
}