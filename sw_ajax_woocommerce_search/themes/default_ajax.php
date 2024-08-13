<?php
/**
 * Layout Default ajax
 * @version     1.0.0
 **/
	$id_category = ( isset($_GET['product_cat']) && $_GET['product_cat'] ) ? $_GET['product_cat'] : '';
	$filter_name = ( isset($_GET['query']) && $_GET['query'] ) ? $_GET['query'] : '';
	$limit 			 = ( isset($_GET['limit']) && $_GET['limit'] ) ? $_GET['limit'] : 5;
	$search_type = ( isset($_GET['search_type']) && $_GET['search_type'] ) ? $_GET['search_type'] : 0;
	
	$args  = array();
	$check = false;
	if( $search_type ){
		global $wpdb;
		$filter_name = esc_html( str_replace( "%20"," ",$filter_name ) );
		$post_ids = $wpdb->get_col( $wpdb->prepare( 
		"SELECT SQL_CALC_FOUND_ROWS {$wpdb->posts}.ID FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id ) 
		WHERE ((({$wpdb->posts}.post_title LIKE %s) OR ({$wpdb->posts}.post_excerpt LIKE %s) OR ({$wpdb->posts}.post_content LIKE %s)) OR ( ( {$wpdb->postmeta}.meta_key = '_sku' AND {$wpdb->postmeta}.meta_value LIKE %s ) ) ) 
		AND ({$wpdb->posts}.post_password = '') AND {$wpdb->posts}.post_type = 'product' AND ({$wpdb->posts}.post_status = 'publish') 
		GROUP BY {$wpdb->posts}.ID 
		ORDER BY {$wpdb->posts}.post_title LIKE %s DESC, {$wpdb->posts}.post_date DESC LIMIT 0, %d", '%' .$filter_name . '%', '%' .$filter_name . '%', '%' .$filter_name . '%', '%' .$filter_name . '%', '%' .$filter_name . '%', $limit ) );
		if( sizeof( $post_ids ) > 0 ){
			$check = true;
			$args = array(
				'post_type' => 'product',
				'post__in'  => $post_ids,
			);
		}
	}else{
		$check = true;
		$args  = array(
			'post_type' => 'product',
			's'					=> $filter_name
		);
	}
	
	if( $id_category !== '' ) :
		
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'product_cat',
				'field' 	 => 'slug', //This is optional, as it defaults to 'term_id'
				'terms'    => $id_category,
			)
		);
	endif;		
	$args['tax_query']['relation'] = 'AND';
	$product_visibility_terms  = wc_get_product_visibility_term_ids();
	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
		$product_visibility_not_in[] = $product_visibility_terms['outofstock'];
	}
	if ( ! empty( $product_visibility_not_in ) ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'term_taxonomy_id',
			'terms'    => $product_visibility_not_in,
			'operator' => 'NOT IN',
		);
	}
	
	$list = new WP_Query( $args );

	$suggestions = array();
	if ( $list->have_posts() && $check ) {
		while( $list->have_posts() ): $list->the_post();
		global $product, $post;
		$product_id = ( version_compare( WC()->version, '3.0', '>=' ) ) ? $product->get_id() : $product->id;
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'shop_thumbnail' );
		$price = '<p class="price">' . $product->get_price_html() . '</p>';
		$sku   = ( wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) ? $product->get_sku() : '';
		$suggestions[] = array(
			'id' 				 => $product_id,
			'value'      => $product->get_title(),		
			'img'		 	 	 => $image[0],
			'link'		   => get_permalink( $product_id ),
			'price'      => $price,
			'sku'				 => $sku
		);			
		endwhile;
	}else{
		 $no_results =  __( 'No products found.', 'sw_ajax_woocommerce_search' );

    $suggestions[] = array(
      'id' 		=> - 1,
      'value' => $no_results,
      'link' 	=> '',
    );
	}

	echo json_encode( array('suggestions' => $suggestions ) );
	exit();
