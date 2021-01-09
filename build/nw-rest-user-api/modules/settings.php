<?php 

if( !class_exists('nwSettingsClassV2') ){
class nwSettingsClassV2{
	
	var $setttings_parameters;
	var $settings_prefix;
	var $message;
	
	function __construct( $prefix ){
		$this->setttings_prefix = $prefix;	
		
		if( isset($_POST[$this->setttings_prefix.'save_settings_field']) ){
			if(  wp_verify_nonce($_POST[$this->setttings_prefix.'save_settings_field'], $this->setttings_prefix.'save_settings_action') ){
				$options = array();
				foreach( $_POST as $key=>$value ){
					$options[$key] = $value ;
				}
				update_option( $this->setttings_prefix.'_options', $options );
				
				$this->message = '<div class="alert alert-success">'.__('Settings saved', $this->setttings_prefix ).'</div>';
				
			}
		}

	}
	
	function get_setting( $setting_name ){
		$inner_option = get_option( $this->setttings_prefix.'_options');
		return $inner_option[$setting_name];
	}
	
	function create_menu( $parameters ){
		$this->setttings_parameters = $parameters;		
			
		add_action('admin_menu', array( $this, 'add_menu_item') );
		
	}
	

	function add_menu_item(){
		
		foreach( $this->setttings_parameters as $single_option ){
			if( $single_option['type'] == 'menu' ){
				add_menu_page(  			 
				$single_option['page_title'], 
				$single_option['menu_title'], 
				$single_option['capability'], 
				$this->setttings_prefix.$single_option['menu_slug'], 
				array( $this, 'show_settings' ),
				$single_option['icon']
				);
			}
			if( $single_option['type'] == 'submenu' ){
				add_submenu_page(  
				$single_option['parent_slug'],  
				$single_option['page_title'], 
				$single_option['menu_title'], 
				$single_option['capability'], 
				$this->setttings_prefix.$single_option['menu_slug'], 
				array( $this, 'show_settings' ) 
				);
			}
			if( $single_option['type'] == 'option' ){
				add_options_page(  				  
				$single_option['page_title'], 
				$single_option['menu_title'], 
				$single_option['capability'], 
				$this->setttings_prefix.$single_option['menu_slug'], 
				array( $this, 'show_settings' ) 
				);
			}
		}
		 
	}
	
	function show_settings(){
		// hide output if its parent menu
		if( count( $this->setttings_parameters[0]['parameters'] ) == 0 ){ return false; }
		
		?>
		<div class="wrap tw-bs4">
		
		<h2><?php echo $this->setttings_parameters[0]['form_title']; ?></h2>
		<hr/>
		<?php 
			echo $this->message;
		?>
		
		<?php if( $this->setttings_parameters[0]['is_form'] ): ?>
			<form class="form-horizontal" method="post" action="">
		<?php endif; ?>

		<?php 
		wp_nonce_field( $this->setttings_prefix.'save_settings_action', $this->setttings_prefix.'save_settings_field'  );  
		$config = get_option( $this->setttings_prefix.'_options'); 
		 
		?>  
		<fieldset>

			<?php 
			foreach( $this->setttings_parameters as $single_page ){	
				foreach( $single_page['parameters'] as $key=>$value ){	
					$interface_element = new formElementsClass( $value['type'], $value, $config[$value['name']] );
					echo $interface_element->get_code();	 
				}
			}
			?>
		</fieldset>  
		
		<?php if( $this->setttings_parameters[0]['is_form'] ): ?>
		</form>
		<?php endif; ?>

		</div>
		<?php
	}
}	
}
	
add_Action('init',  function (){

	$locale = 'nrua';
	$config_big = 
	array(
		array(
			'type' => 'option',
			//'parent_slug' => 'edit.php?post_type=post',
			'form_title' => __('Settings', $locale),
			'is_form' => true,
			'page_title' => __('User API Settings', $locale),
			'menu_title' => __('User API Settings', $locale),
			'capability' => 'edit_published_posts',
			'menu_slug' => 'user_api_settings',
			'parameters' => array(
				array(
					'type' => 'select',
					'title' => __('Disable new registrations', $locale),
					'name' => 'disable_registrations',
					'value' => array( 'no' => 'No', 'yes' => 'Yes' ),
					'id' => '',
					'style' => '',
					'class' => ''
				),
				array(
					'type' => 'save',
					'title' => __('Save', $locale),
				),
				
				 
			)
		)
	); 
	global $settings;

	$settings = new nwSettingsClassV2( $locale ); 
	$settings->create_menu(  $config_big   );
	
} );
	
 

?>