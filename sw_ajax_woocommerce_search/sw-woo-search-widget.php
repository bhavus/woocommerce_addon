<?php
/**
 * Plugin Name: SW Ajax WooCommerce Search
 * Plugin URI: http://smartaddons.com
 * Description: This plugins allows users to search products in your website with the results in real time.
 * Version: 1.1.17
 * Author: smartaddons.com
 * Author URI: http://smartaddons.com
 * WC tested up to: 5.2.0
 *
 * This Widget help you to show listing search by ajax.
 */

if ( ! defined( 'WCSTHEME' ) ) {
	define( 'WCSTHEME', plugin_dir_path( __FILE__ ) );
}

/*
** Register Activation
*/
function sw_ajax_search_construct(){
	global $woocommerce;

	if ( ! isset( $woocommerce ) || ! function_exists( 'WC' ) ) {
		add_action( 'admin_notices', 'sw_ajax_search_admin_notice' );
		return;
	}
	
	add_action( 'widgets_init', 'sw_ajax_woocommerce_search', 10 );
	
	/* Load text domain */
	load_plugin_textdomain( 'sw_ajax_woocommerce_search', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'sw_ajax_search_construct', 20 );

/*
** Check if WooCommerce not active
*/
function sw_ajax_search_admin_notice(){
	?>
<div class="error">
    <p><?php esc_html_e( 'SW Ajax WooCommerce Search is enabled but not effective. It requires WooCommerce in order to work.', 'sw_ajax_woocommerce_search' ); ?>
    </p>
</div>
<?php
}

/**
 * Register our widget.
 * 'sw_ajax_woocommerce_search' is the widget class used below.
 */
function sw_ajax_woocommerce_search() {
	register_widget( 'sw_woo_search_widget' );
}

/**
 * SW Woocommerce Search Widget class.
 * This class handles everything that needs to be handled with the widget:
 * the settings, display, and update.  Nice!
 */
class sw_woo_search_widget extends WP_Widget {
	
	private $snumber = 1;
	
	function __construct(){
		/* Widget settings. */
		$widget_ops = array( 'classname' => 'sw_ajax_woocommerce_search', 'description' => __( 'This plugins allows users to search products in your website with the results in real time', 'sw_ajax_woocommerce_search' ) );

		/* Widget control settings. */
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'sw_ajax_woocommerce_search' );

		/* Create the widget. */
		parent::__construct( 'sw_ajax_woocommerce_search', __('SW Ajax WooCommerce Search', 'sw_ajax_woocommerce_search'), $widget_ops, $control_ops );
		
		add_action('wp_enqueue_scripts', array( $this, 'sw_search_script' ) );
		add_action( 'pre_get_posts', array( $this, 'advanced_search_query') );
		
		
		/* Ajax Call*/
	
		add_action( 'wp_ajax_sw_search_products_callback', array( $this, 'sw_search_products_callback' ) );
		add_action( 'wp_ajax_nopriv_sw_search_products_callback', array( $this, 'sw_search_products_callback' ) );
		
		/* Create Shortcode */
		add_shortcode( 'search_products', array( $this, 'WS_Search_Shortcode' ) );
		if ( class_exists('Vc_Manager') ) {
			add_action( 'vc_before_init', array( $this, 'WS_Search_integrateWithVC' ), 20 );
		}	
		
		add_filter( 'template_include', array( $this, 'sw_search_template_load' ) );
	}

	/*
	** Generate ID
	*/
	public function generateID() {
		return 'sw_woo_search_' . (int) $this->snumber++;
	}
	
	function advanced_search_query( $query )
	{
		if( $query->is_search() ) {
			// category terms search.
			if( isset($_GET['search_posttype'] ) ) {
					$query->set( 'post_type', 'product' );
				if ( ( isset( $_GET['category'] ) && !empty( $_GET['category'] ) ) && $_GET['category'] != '' ) {
					$query->set( 'tax_query', array(
						array(
							'taxonomy' => 'product_cat',
							'field' => 'slug',
							'terms' => array( $_GET['category'] ) )
						)
					);
				}
			}
			return $query;
		}
	}

	
	function sw_search_template_load( $template ){ 
		if( is_search() ){
				if( isset( $_GET['search_posttype'] ) && !locate_template( 'templates/search-product.php' ) ) {
				$template = WCSTHEME . 'themes/search-product.php';
			}
		}
		return $template;
	}
	
	/**
	* Add Vc Params
	**/
		
	function WS_Search_integrateWithVC(){
		$categories =	get_terms( 'product_cat', array( 'hide_empty' => false,'parent' => 0 ));
		if( count( $categories ) == 0 ){
			return ;
		}
		$term = array( __( 'All Categories', 'sw_ajax_woocommerce_search' ) => '' );
		foreach( $categories as $cat ){
			$term[$cat->name] = $cat -> term_id;
		}
		vc_map( array(
		  "name" => __( "SW Ajax WooCommerce Search", 'sw_ajax_woocommerce_search' ),
		  "base" => "search_products",
		  "icon" => "icon-wpb-ytc",
		  "class" => "",
		  "category" => __( "SW Shortcodes", 'sw_ajax_woocommerce_search'),
		  "params" => array(
			 array(
				"type" => "textfield",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Title", 'sw_ajax_woocommerce_search' ),
				"param_name" => "title",
				"value" => "",
				"description" => __( "Title", 'sw_ajax_woocommerce_search' )
			 ),
			array(
				"type" => "dropdown",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Show Title", 'sw_ajax_woocommerce_search' ),
				"param_name" => "show_title",
				"value" => array('Yes' => 1, 'No' => 0),
				"description" => __( "Show Title", 'sw_ajax_woocommerce_search' )
			 ),		
			array(
				"type" => "dropdown",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Search Type", 'sw_ajax_woocommerce_search' ),
				"param_name" => "search_type",
				"value" => array( 
					__( 'Search By Keyword', 'sw_ajax_woocommerce_search' ) => 0,
					__( 'Search By Keyword and SKU', 'sw_ajax_woocommerce_search' ) => 1
				),
				"description" => __( "Select search type config for ajax search.", 'sw_ajax_woocommerce_search' )
			 ),				 
			 array(
				"type" => "textfield",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Image Width(px)", 'sw_ajax_woocommerce_search' ),
				"param_name" => "width_image",
				"value" => '50',
				"description" => __( "Image Width", 'sw_ajax_woocommerce_search' )
			 ),	
			 array(
				"type" => "textfield",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Image Height(px)", 'sw_ajax_woocommerce_search' ),
				"param_name" => "height_image",
				"value" => '50',
				"description" => __( "Image Height", 'sw_ajax_woocommerce_search' )
			 ),		
			 array(
				"type" => "textfield",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Number Of Items In List", 'sw_ajax_woocommerce_search' ),
				"param_name" => "limit",
				"value" => 5,
				"description" => __( "Number Of Items In List", 'sw_ajax_woocommerce_search' )
			 ),	
			 array(
				"type" => "textfield",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Number Of Character For Starting Search", 'sw_ajax_woocommerce_search' ),
				"param_name" => "character",
				"value" => 3,
				"description" => __( "Number Of Character For Starting Search", 'sw_ajax_woocommerce_search' )
			 ),				 
			array(
				"type" => "dropdown",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Show Category List", 'sw_ajax_woocommerce_search' ),
				"param_name" => "show_category",
				"value" => array('Yes' => 1, 'No' => 0),
				"description" => __( "Show Category List", 'sw_ajax_woocommerce_search' )
			 ),	
			array(
				"type" => "dropdown",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Show Product Image", 'sw_ajax_woocommerce_search' ),
				"param_name" => "show_image",
				"value" => array('Yes' => 1, 'No' => 0),
				"description" => __( "Show Product Image", 'sw_ajax_woocommerce_search' )
			 ),	
			array(
				"type" => "dropdown",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Show Price Of Product", 'sw_ajax_woocommerce_search' ),
				"param_name" => "show_price",
				"value" => array('Yes' => 1, 'No' => 0),
				"description" => __( "Show Price Of Product", 'sw_ajax_woocommerce_search' )
			 ),				 
			array(
				"type" => "dropdown",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Layout", 'sw_ajax_woocommerce_search' ),
				"param_name" => "widget_template",
				"value" => array('Default' => 'default'),
				"description" => __( "Select Layout", 'sw_ajax_woocommerce_search' )
			),
			 array(
				"type" => "textfield",
				"holder" => "div",
				"class" => "",
				"heading" => __( "Extra Class", 'sw_ajax_woocommerce_search' ),
				"param_name" => "extra_class",
				"value" => '',
				"description" => __( "If you wish to style particular content element differently, then use this field to add a class name and then refer to it in your css file.", 'sw_ajax_woocommerce_search' )
			 )			
		)
	  ));
	}
	/**
		** Add Shortcode
	**/
		
	function WS_Search_Shortcode( $atts, $content = null){
			extract( shortcode_atts(
				array(
				'title' 			  => '',
				'show_title'	  => 1,
				'extra_class'	  => '',
				'width_image'	  => '50',
				'height_image'  => '50',
				'limit'				  => 5,
				'character'		  => 3,
				'show_category'	=> 1,
				'show_image'		=> 1,
				'show_price'		=> 1,	
				'search_type'		=> 0,	
				'widget_template'	=> 'default'	
				), $atts )
			);	
		ob_start(); 	
		include( plugin_dir_path( dirname(__FILE__) ).'/sw_ajax_woocommerce_search/themes/default.php' );
		$content = ob_get_clean();
		return $content;
	}	
	
	function sw_search_products_callback(){
		$dir =	plugin_dir_path( dirname(__FILE__) ).'sw_ajax_woocommerce_search/themes/default_ajax.php';
		include $dir;
	}	
	
	function sw_search_script(){
		wp_register_style( 'sw_woocommerce_search_style', plugins_url( 'css/sw_woocommerce_search_products.css', __FILE__) );
		if (!wp_style_is('sw_woocommerce_search_style')) {
			// wp_enqueue_style('sw_woocommerce_search_style');
		}		
		wp_register_script( 'sw_woocommerce_search_products', plugins_url('js/sw_woocommerce_search_products.min.js', __FILE__), array(), null, true );
		wp_localize_script( 'sw_woocommerce_search_products', 'sw_livesearch', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'sku' => esc_html__( 'Sku', 'sw_ajax_woocommerce_search' ) ) );
		wp_enqueue_script('sw_woocommerce_search_products');
	}
	
	public function ya_trim_words( $text, $num_words = 30, $more = null ) {
		$text = strip_shortcodes( $text);
		$text = apply_filters('the_content', $text);
		$text = str_replace(']]>', ']]&gt;', $text);
		return wp_trim_words($text, $num_words, $more);
	}
	/**
	 * Display the widget on the screen.
	 */
	public function widget( $args, $instance ) {
		extract($args);
		echo $before_widget;
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		
		if ( ! empty( $title ) &&  $instance['show_title'] ){
			echo $args['before_title'] . $title . $args['after_title'];
		}
		
		if ( !array_key_exists('widget_template', $instance) ){
			$instance['widget_template'] = 'default';
		}
		extract( $instance );

		if ( $tpl = $this->getTemplatePath( $instance['widget_template'] ) ){ 
			$link_img = plugins_url('images/', __FILE__);
			$widget_id = $args['widget_id'];		
			include $tpl;
		}
				
		/* After widget (defined by themes). */
		echo $after_widget;
	}    	

	protected function getTemplatePath($tpl='default', $type=''){
		$file = '/'.$tpl.$type.'.php';
		$dir =realpath(dirname(__FILE__)).'/themes';
		
		if ( file_exists( $dir.$file ) ){
			return $dir.$file;
		}
		
		return $tpl=='default' ? false : $this->getTemplatePath('default', $type);
	}
	
	/**
	 * Update the widget settings.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		// strip tag on text field
		$instance['title'] 				= strip_tags( $new_instance['title'] );	
		$instance['show_title'] 		= $new_instance['show_title'];
		$instance['extra_class'] 		= strip_tags( $new_instance['extra_class'] );	
		$instance['width_image'] 		= strip_tags( $new_instance['width_image'] );	
		$instance['height_image'] 	 	= strip_tags( $new_instance['height_image'] );			
		$instance['limit'] 				= $new_instance['limit'];
		$instance['character'] 			= $new_instance['character'];
		$instance['show_category'] 	 	= $new_instance['show_category'];
		$instance['show_image'] 		= $new_instance['show_image'];
		$instance['show_price'] 		= $new_instance['show_price'];
		$instance['search_type'] 		= $new_instance['search_type'];
		$instance['widget_template'] = strip_tags( $new_instance['widget_template'] );		
        if ( array_key_exists('style', $new_instance) ){
			$instance['style'] = strip_tags( $new_instance['style'] );
		}
		return $instance;
	}	

	/**
	 * Displays the widget settings controls on the widget panel.
	 * Make use of the get_field_id() and get_field_name() function
	 * when creating your form elements. This handles the confusing stuff.
	 */
	public function form( $instance ) {
		/* Set up some default widget settings. */
		$defaults = array();
		$instance 				= wp_parse_args( (array) $instance, $defaults ); 
		$title 						= isset( $instance['title'] )    			? 	strip_tags( $instance['title'] ) : '';	
		$style 						= isset( $instance['style'] )    			? 	strip_tags( $instance['style'] ) : '';
		$show_title 			= isset( $instance['show_title'] )    	? 	$instance['show_title'] : 1 ;	
		$extra_class 			= isset( $instance['extra_class'] )     ? 	strip_tags( $instance['extra_class'] ) : '';
		$width_image 			= isset( $instance['width_image'] )     ? 	strip_tags( $instance['width_image'] ) : '50';
		$height_image 		= isset( $instance['height_image'] )    ? 	strip_tags( $instance['height_image'] ) : '50';	
		$limit 						= isset( $instance['limit'] )    				? 	$instance['limit'] : 5 ;
		$character 				= isset( $instance['character'] )    		? 	$instance['character'] : 3 ;
		$show_category 		= isset( $instance['show_category'] )   ? 	$instance['show_category'] : 1 ;
		$show_image 			= isset( $instance['show_image'] )    	? 	$instance['show_image'] : 1 ;		
		$show_price 			= isset( $instance['show_price'] )    	? 	$instance['show_price'] : 1 ;
		$search_type 			= isset( $instance['search_type'] )    	? 	$instance['search_type'] : 0;		
		$widget_template  = isset( $instance['widget_template'] ) ? 	strip_tags( $instance['widget_template'] ) : 'default';   
		?>
</p>
<div style="background: Blue; color: white; font-weight: bold; text-align:center; padding: 3px">
    <?php _e('Data Config', 'sw_ajax_woocommerce_search')?> </div>
</p>

<p>
    <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'sw_ajax_woocommerce_search')?></label>
    <br />
    <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
        name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('show_title'); ?>"><?php _e("Show Title", 'sw_ajax_woocommerce_search')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('show_title'); ?>"
        name="<?php echo $this->get_field_name('show_title'); ?>">
        <option value="1" <?php if ($show_title==1){?> selected="selected" <?php } ?>>
            <?php _e('Yes', 'sw_ajax_woocommerce_search')?>
        </option>
        <option value="0" <?php if ($show_title==0){?> selected="selected" <?php } ?>>
            <?php _e('No', 'sw_ajax_woocommerce_search')?>
        </option>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('search_type'); ?>"><?php _e("Search Type", 'sw_ajax_woocommerce_search')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('search_type'); ?>"
        name="<?php echo $this->get_field_name('search_type'); ?>">
        <option value="0" <?php if ($search_type==0){?> selected="selected" <?php } ?>>
            <?php _e('Search By Keyword', 'sw_ajax_woocommerce_search')?>
        </option>
        <option value="1" <?php if ($search_type==1){?> selected="selected" <?php } ?>>
            <?php _e('Search By Keyword and SKU', 'sw_ajax_woocommerce_search')?>
        </option>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('width_image'); ?>"><?php _e('Image Width(px)', 'sw_ajax_woocommerce_search')?></label>
    <br />
    <input class="widefat" id="<?php echo $this->get_field_id('width_image'); ?>"
        name="<?php echo $this->get_field_name('width_image'); ?>" type="text"
        value="<?php echo esc_attr($width_image); ?>" />
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('height_image'); ?>"><?php _e('Image Height(px)', 'sw_ajax_woocommerce_search')?></label>
    <br />
    <input class="widefat" id="<?php echo $this->get_field_id('height_image'); ?>"
        name="<?php echo $this->get_field_name('height_image'); ?>" type="text"
        value="<?php echo esc_attr($height_image); ?>" />
</p>
<?php $styles_args = array('' => 'Select', 'style1' => 'style1', 'style2' => 'style2'); ?>
<p>
    <label for="<?php echo $this->get_field_id('style'); ?>"><?php _e('Style', 'sw_woocommerce')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('style'); ?>"
        name="<?php echo $this->get_field_name('style'); ?>">
        <?php
					$option ='';
					foreach ($styles_args as $value => $key) :
						$option .= '<option value="' . $value . '" ';
						if ($value == $style){
							$option .= 'selected="selected"';
						}
						$option .=  '>'.$key.'</option>';
					endforeach;
					echo $option;
					?>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number Of Items In List', 'sw_ajax_woocommerce_search')?></label>
    <br />
    <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>"
        name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo esc_attr($limit); ?>" />
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('character'); ?>"><?php _e('Number Of Character For Starting Search', 'sw_ajax_woocommerce_search')?></label>
    <br />
    <input class="widefat" id="<?php echo $this->get_field_id('character'); ?>"
        name="<?php echo $this->get_field_name('character'); ?>" type="text"
        value="<?php echo esc_attr($character); ?>" />
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('show_category'); ?>"><?php _e("Show Category List", 'sw_ajax_woocommerce_search')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('show_category'); ?>"
        name="<?php echo $this->get_field_name('show_category'); ?>">
        <option value="1" <?php if ($show_category==1){?> selected="selected" <?php } ?>>
            <?php _e('Yes', 'sw_ajax_woocommerce_search')?>
        </option>
        <option value="0" <?php if ($show_category==0){?> selected="selected" <?php } ?>>
            <?php _e('No', 'sw_ajax_woocommerce_search')?>
        </option>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('show_image'); ?>"><?php _e("Show Product Image", 'sw_ajax_woocommerce_search')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('show_image'); ?>"
        name="<?php echo $this->get_field_name('show_image'); ?>">
        <option value="1" <?php if ($show_image==1){?> selected="selected" <?php } ?>>
            <?php _e('Yes', 'sw_ajax_woocommerce_search')?>
        </option>
        <option value="0" <?php if ($show_image==0){?> selected="selected" <?php } ?>>
            <?php _e('No', 'sw_ajax_woocommerce_search')?>
        </option>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('show_price'); ?>"><?php _e("Show Price Of Product", 'sw_ajax_woocommerce_search')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('show_price'); ?>"
        name="<?php echo $this->get_field_name('show_price'); ?>">
        <option value="1" <?php if ($show_price==1){?> selected="selected" <?php } ?>>
            <?php _e('Yes', 'sw_ajax_woocommerce_search')?>
        </option>
        <option value="0" <?php if ($show_price==0){?> selected="selected" <?php } ?>>
            <?php _e('No', 'sw_ajax_woocommerce_search')?>
        </option>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('widget_template'); ?>"><?php _e("Layout", 'sw_ajax_woocommerce_search')?></label>
    <br />
    <select class="widefat" id="<?php echo $this->get_field_id('widget_template'); ?>"
        name="<?php echo $this->get_field_name('widget_template'); ?>">
        <option value="default" <?php if ($widget_template=='default'){?> selected="selected" <?php } ?>>
            <?php _e('Default', 'sw_ajax_woocommerce_search')?>
        </option>
        <option value="theme1" <?php if ($widget_template=='theme1'){?> selected="selected" <?php } ?>>
            <?php _e('Style 1', 'sw_ajax_woocommerce_search')?>
        </option>
    </select>
</p>
<p>
    <label
        for="<?php echo $this->get_field_id('extra_class'); ?>"><?php _e('Extra Class', 'sw_ajax_woocommerce_search')?></label>
    <br />
    <input class="widefat" id="<?php echo $this->get_field_id('extra_class'); ?>"
        name="<?php echo $this->get_field_name('extra_class'); ?>" type="text"
        value="<?php echo esc_attr($extra_class); ?>" />
</p>
<script>
jQuery(document).trigger('sw_colorpicker');
</script>
<?php
	}			
}
?>