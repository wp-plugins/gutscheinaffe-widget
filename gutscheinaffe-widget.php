<?php
/*
Plugin Name: Gutscheinaffe Widget
Plugin URI: http://www.gutscheinaffe.de/
Description: Die Widgets zeigen aktuelle Gutscheine von gutscheinaffe.de an.
Version: 1.1.3
Author: Julian Exner
Author URI: http://www.gutscheinaffe.de/
*/

add_action( 'widgets_init', create_function( '', 'register_widget( "gutscheinaffe_widget" );' ) );

/**
 * Gutscheinaffe Widget
 */
class Gutscheinaffe_Widget extends WP_Widget {
	
	// .min for minified files
	private $filesuffix = '.min';
	// i18n textdomain
	private $textdomain = 'gutscheinaffe_widget';
	// errors
	private $error = array();
	
	// url for categories
	private $category_url	= 'http://www.gutscheinaffe.de/api/v1/?method=getCategories';
	private $shop_url		= 'http://www.gutscheinaffe.de/api/v1/?method=getShops';
	
	private function getCategories() {
		$categories = get_option( $this->id_base . '-allcategories', array() );
		if ( empty( $categories ) ) {
			$raw = wp_remote_retrieve_body( wp_remote_get( $this->category_url ) );
			$response = json_decode( $raw );

			if ( isset( $response->success ) && $response->success === true ) {
				$categories = array();
				foreach ( $response->result as $category ) {
					$categories[ $category->id ] = $category->title;
				}
				update_option( $this->id_base . '-allcategories', $categories );
			}
		}
		
		return $categories;
	}
	
	public function enqueue_scripts_and_styles() {
		$settings = $this->get_settings();
		
		foreach ( $settings as $number => $instance ) {
			
			if ( is_active_widget( false, $this->id_base . '-' . $number, $this->id_base, true ) ) {
				wp_enqueue_script( 'gutscheinaffe-widget', plugin_dir_url( __FILE__ ) . 'scripts/gutscheinaffe-widget' . $this->filesuffix . '.js', array( 'jquery' ) );
				
				if ( $instance[ 'customcss' ] == '0' ) {
					wp_enqueue_style(  'gutscheinaffe-widget', plugin_dir_url( __FILE__ ) . 'styles/gutscheinaffe-widget' . $this->filesuffix  . '.css' );
				} else {
					if ( !empty($instance[ 'cssurl' ]) ) {
						wp_enqueue_style( $instance[ 'cssurl' ], $instance[ 'cssurl' ] );
					}
				}
				
			}
		
		}
	}
	
	private $defaults = array(
		'title'			=> '',
		'ad'			=> 0,
		'default'		=> 'none',
		'shop'			=> '',
		'shopid'		=> '',
		'category'		=> '',
		'categories' 	=> '',
		'limit'			=> 10,
		'partnerid'		=> '',
		'channelid'		=> '',
		'customcss'		=> 0,
		'cssurl'		=> ''
	);
	
	/**
	 * construction and registration
	 */
	
	public function Gutscheinaffe_Widget() {
		$this->__construct();
	}
	
	public function __construct() {
		parent::__construct(
			'gutscheinaffe_widget',
			'Gutscheinaffe Widget',
			array(
				'description' => __( 'Das Gutscheinaffe Widget zeigt aktuelle Gutscheine von Gutscheineaffe.de an.', $this->textdomain )
			),
			array( 'width' => 350 )
		);
		
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts_and_styles' ) );
	}
	
	/**
	 * save form
	 */
	
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$new_instance = wp_parse_args( (array)$new_instance, $this->defaults );
		
		$instance[ 'title' ]	= $new_instance[ 'title' ];
		$instance[ 'ad' ]		= !empty( $new_instance[ 'ad' ] ) ? '1': '0';
		$instance[ 'default' ]	= $new_instance[ 'default' ];
		
		$instance[ 'shop' ]		= $new_instance[ 'shop' ];
		$raw = wp_remote_retrieve_body( wp_remote_get( $this->shop_url . '&limit=1&query=' . urlencode( $new_instance[ 'shop' ] ) . '&start=true' ) );
		$response = json_decode( $raw );
		if ( isset( $response->success ) && $response->success === true && count( $response->result ) > 0 ) {
			$shop = array_shift( $response->result );
			$instance[ 'shopid' ]	= $shop->id;
			$instance[ 'shop' ]		= $shop->name;
		} elseif ( $instance[ 'default' ] == 'shop' ) {
			$instance[ 'default' ]	= 'none';
			$instance[ 'shopid' ]	= 0;
			$instance[ 'shop' ]		= '';			
			$this->error[ $this->number ] = sprintf( __( 'Der Shop <em>%s</em> wurde nicht gefunden.', $this->textdomain ), $new_instance[ 'shop' ] );
		}
		
		$instance[ 'category' ]		= $new_instance[ 'category' ];
		$instance[ 'categories' ]	= $new_instance[ 'categories' ];
		$instance[ 'limit' ]		= $new_instance[ 'limit' ];
		$instance[ 'partnerid' ]	= $new_instance[ 'partnerid' ];
		$instance[ 'channelid' ]	= $new_instance[ 'channelid' ];
		$instance[ 'customcss' ]	= !empty( $new_instance[ 'customcss' ] ) ? '1' : '0';
		$instance[ 'cssurl' ]		= $new_instance[ 'cssurl' ];
		
		return $instance;
	}

	/**
	 * front end widget form
	 */
	
	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance[ 'title' ] );
		
		echo $before_widget;
		
		if ( $instance[ 'ad' ] == '1' )
			$title .= ( !empty( $title ) ? ' ' : '' ) . '<small>' . __( 'Anzeige', $this->textdomain ) . '</small>';
		
		if ( !empty( $title ) )
			echo $before_title . $title . $after_title;
		
		$allcategories	= $this->getCategories();
		$class_prefix	= esc_attr( $this->id_base );
		?>
		<div class="<?php echo $class_prefix ?>">
			<form class="<?php echo $class_prefix ?>-form" action="#">
				<input type="text" value="" class="<?php echo $class_prefix; ?>-query" value="" placeholder="<?php echo esc_attr( __( 'Suche nach Shop', $this->textdomain ) ); ?>" />				
				<?php foreach ( $instance as $field => $value ): ?>
					<input type="hidden" name="<?php echo $field; ?>" value="<?php echo esc_attr($value); ?>" class="<?php echo $class_prefix; ?>-config" />
				<?php endforeach; ?>
			</form>
			
			<ul class="<?php echo $class_prefix; ?>-autocomplete">
				<li></li>
			</ul>
			
			<select class="<?php echo $class_prefix; ?>-select">
				<option <?php echo ( $instance[ 'category' ] == 'none' ? 'selected="selected"' : ''); ?> value="none"><?php _e( 'Gutschein-Kategorie wählen', $this->textdomain ); ?></option>
				<option <?php echo ( $instance[ 'category' ] == 'getTop' ? 'selected="selected"' : '' ); ?> value="getTop"><?php _e( 'Top Gutscheine', $this->textdomain ); ?></option>
				<option <?php echo ( $instance[ 'category' ] == 'getNew' ? 'selected="selected"' : '' ); ?> value="getNew"><?php _e( 'Neueste Gutscheine', $this->textdomain); ?></option>
				<option <?php echo ( $instance[ 'category' ] == 'getExpiring' ? 'selected="selected"' : '' ); ?> value="getExpiring"><?php _e( 'Ablaufende Gutscheine', $this->textdomain ); ?></option>
				<?php foreach( $allcategories as $id => $name ): if ( in_array( $id, $instance[ 'categories' ] ) ): ?>
					<option <?php echo ( $instance[ 'category' ] == $id ? 'selected="selected"' : '' ); ?> value="<?php echo esc_attr( $id ); ?>"><?php echo $name; ?></option>
				<?php endif; endforeach; ?>
			</select>
			
			<div class="<?php echo $class_prefix; ?>-table-container">
				<table class="<?php echo $class_prefix; ?>-table">
					<tr><td></td></tr>
				</table>
			</div>
		</div>
		<?
		
		echo $after_widget;
	}
	
	/**
	 * admin form
	 */
	
	public function form( $instance ) {
		$instance = wp_parse_args( (array)$instance, $this->defaults );
		$ad		= $instance[ 'ad' ] ? 'checked="checked"' : '';
		$default= in_array( $instance[ 'default' ], array( 'none', 'shop', 'category' ) ) ? $instance[ 'default' ] : 'none';
		
		$allcategories = $this->getCategories();
		$categories = is_array( $instance[ 'categories' ] ) ? $instance[ 'categories' ] : array_keys( $allcategories );
		?>
		<?php if ( !empty( $this->error[ $this->number ] ) ): ?>
			<div class="error"><strong><?php echo $this->error[ $this->number ]; ?></strong></div>
		<?php endif; ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel', $this->textdomain ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance[ 'title' ] ); ?>" />
		
			<label for="<?php echo $this->get_field_id( 'ad' ); ?>">
				<input <?php echo $ad; ?> id="<?php echo $this->get_field_id( 'ad' ); ?>" name="<?php echo $this->get_field_name( 'ad' ); ?>" type="checkbox" />
				<?php _e( 'Als Anzeige markieren', $this->textdomain ); ?>
			</label> 
		</p>
		
		<fieldset>
			<legend><?php _e( 'Standard-Ansicht', $this->textdomain ); ?>:</legend>
			
			<p>
				<label for="<?php echo $this->get_field_id( 'none' ); ?>"><input type="radio" <?php echo ($default == 'none' ? 'checked="checked"' : ''); ?> name="<?php echo $this->get_field_name( 'default' ); ?>" value="none" id="<?php echo $this->get_field_id( 'none' ); ?>" /> <?php _e( 'Keine Gutscheine', $this->textdomain ); ?></label><br />
				<label for="<?php echo $this->get_field_id( 'shop' ); ?>">
					<input <?php echo ($default == 'shop' ? 'checked="checked"' : ''); ?> type="radio" name="<?php echo $this->get_field_name( 'default' ); ?>" value="shop" id="<?php echo $this->get_field_id( 'shop' ); ?>" /> <?php _e( 'Bestimmer Shop', $this->textdomain ); ?>:
				</label>
				<input class="widefat" type="text" name="<?php echo $this->get_field_name( 'shop' ); ?>" value="<?php echo esc_attr( $instance[ 'shop' ] ); ?>" />
				
				<label for="<?php echo $this->get_field_id( 'category' ); ?>">
					<input <?php echo ($default == 'category' ? 'checked="checked"' : ''); ?> type="radio" id="<?php echo $this->get_field_id( 'category' ); ?>" name="<?php echo $this->get_field_name( 'default' ); ?>" value="category" /> <?php _e( 'Kategorie', $this->textdomain ); ?>:
				</label>
				<select class="widefat" name="<?php echo $this->get_field_name( 'category' ); ?>">
					<option value="getTop" <?php echo ($instance[ 'category' ] == 'getTop' ? 'selected="selected"' : ''); ?>><?php _e( 'Top Gutscheine', $this->textdomain ); ?></option>
					<option value="getNew" <?php echo ($instance[ 'category' ] == 'getNew' ? 'selected="selected"' : ''); ?>><?php _e( 'Neue Gutscheine', $this->textdomain ); ?></option>
					<option value="getExpiring" <?php echo ($instance[ 'category' ] == 'getExpiring' ? 'selected="selected"' : ''); ?>><?php _e( 'Ablaufende Gutscheine', $this->textdomain ); ?></option>
					<?php foreach ( $allcategories as $id => $name ): ?>
						<option <?php echo ($instance[ 'category' ] == $id ? 'selected="selected"' : ''); ?> value="<?php echo esc_attr( $id ); ?>"><?php echo $name; ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</fieldset>
		
		<div class="categorydiv">
			<span><?php _e( 'Auswählbare Kategorien' ); ?>:</span>
			<div class="tabs-panel">
				<ul class="categorychecklist">
					<?php foreach( $allcategories as $id => $name ): $checked = in_array( $id, $categories ) ? 'checked="checked"' : ''; ?>
						<li><label class="selectit"><input <?php echo $checked; ?> type="checkbox" name="<?php echo $this->get_field_name( 'categories' ); ?>[]" value="<?php echo esc_attr( $id ); ?>"> <?php echo $name; ?></label></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<br />
		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Anzahl der Gutscheine', $this->textdomain ); ?>:</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>">
			<?php for ( $i = 1; $i <= 10; $i++ ): ?>
				<option <?php echo ( $instance[ 'limit' ] == $i ? 'selected="selected"' : ''); ?> value="<?php echo $i; ?>"><?php echo $i; ?></option>
			<?php endfor; ?>
			</select>
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'partnerid' ); ?>"><?php _e( 'Partner-Id', $this->textdomain ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'partnerid' ); ?>" name="<?php echo $this->get_field_name( 'partnerid' ); ?>" type="text" value="<?php echo esc_attr( $instance[ 'partnerid' ] ); ?>" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'channelid' ); ?>"><?php _e( 'Kanal-Id', $this->textdomain ); ?>:</label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'channelid' ); ?>" name="<?php echo $this->get_field_name( 'channelid' ); ?>" type="text" value="<?php echo esc_attr( $instance[ 'channelid' ] ); ?>" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'customcss' ); ?>">
				<input <?php echo ( $instance[ 'customcss' ] ? 'checked="checked"' : '' ); ?> id="<?php echo $this->get_field_id( 'customcss' ); ?>" name="<?php echo $this->get_field_name( 'customcss' ); ?>" type="checkbox" />
				<?php _e( 'Eigene CSS-Datei einbinden' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'cssurl' ); ?>" name="<?php echo $this->get_field_name( 'cssurl' ); ?>" type="text" value="<?php echo esc_attr( $instance[ 'cssurl' ] ); ?>" placeholder="<?php echo esc_attr( __( 'URL zum Stylesheet', $this->textdomain ) ); ?>" />
			<br />
			<small><a href="<?php echo esc_attr( plugin_dir_url(__FILE__) . 'styles/gutscheinaffe-widget.css' ); ?>">Beispiel-Stylesheet</a></small>
		</p>
		
		<?php
	}
	
}

?>