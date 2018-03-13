<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Super_Duper' ) ) {


	/**
	 * A Class to be able to create a Widget, Shortcode or Block to be able to output content for WordPress.
	 *
	 * Should not be called direct but extended instead.
	 *
	 * Class WP_Super_Duper
	 * @ver 0.0.1
	 */
	class WP_Super_Duper extends WP_Widget {

		public $block_code;
		public $options;
		public $base_id;
		public $arguments = array();
		private $class_name;

		/**
		 * Take the array options and use them to build.
		 */
		public function __construct( $options ) {

			//print_r($options);exit;

			$this->base_id = $options['base_id'];
			// lets filter the options before we do anything
			$options       = apply_filters( "wp_super_duper_options_{$this->base_id}", $options );
			$options = $this->add_name_from_key($options);
			$this->options = $options;

			$this->base_id   = $options['base_id'];
			$this->arguments = isset($options['arguments']) ? $options['arguments'] : '';


			// init parent
			parent::__construct( $options['base_id'], $options['name'], $options['widget_ops'] );


			if ( isset( $options['class_name'] ) ) {
				// register widget
				$this->class_name = $options['class_name'];
				$this->register_widget();

				// register shortcode
				$this->register_shortcode();

				// register block
				//$this->register_block();
				add_action( 'admin_enqueue_scripts', array( $this, 'register_block' ) );
			}

		}


		/**
		 * Set the name from the argument key.
		 *
		 * @param $options
		 *
		 * @return mixed
		 */
		private function add_name_from_key($options){
			if(!empty($options['arguments'])){
				foreach($options['arguments'] as $key => $val){
					$options['arguments'][$key]['name'] = $key;
				}
			}
			return $options;
		}

		/**
		 * Register the parent widget class
		 */
		public function register_widget() {
//		add_action( 'widgets_init', function () {
//			register_widget( $this->class_name );
//		} );
		}

		/**
		 * Register the parent shortcode
		 */
		public function register_shortcode() {
			add_shortcode( $this->base_id, array( $this, 'shortcode_output' ) );
			add_action( 'wp_ajax_super_duper_output_shortcode', array( __CLASS__, 'render_shortcode' ) );
		}

		/**
		 * Render the shortcode via ajax so we can return it to Gutenberg.
		 */
		public static function render_shortcode() {

			check_ajax_referer( 'super_duper_output_shortcode', '_ajax_nonce', true );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die();
			}

			// we might need the $post value here so lets set it.
			if ( isset( $_POST['post_id'] ) && $_POST['post_id'] ) {
				$post_obj = get_post( absint( $_POST['post_id'] ) );
				if ( ! empty( $post_obj ) && empty( $post ) ) {
					global $post;
					$post = $post_obj;
				}
			}

			if ( isset( $_POST['shortcode'] ) && $_POST['shortcode'] ) {
				$shortcode_name   = sanitize_title_with_dashes( $_POST['shortcode'] );
				$attributes_array = isset( $_POST['attributes'] ) && $_POST['attributes'] ? $_POST['attributes'] : array();
				$attributes       = '';
				if ( ! empty( $attributes_array ) ) {
					foreach ( $attributes_array as $key => $value ) {
						$attributes .= " " . sanitize_title_with_dashes( $key ) . "='" . wp_slash( $value ) . "' ";
					}
				}

				$shortcode = "[" . $shortcode_name . " " . $attributes . "]";

				echo do_shortcode( $shortcode );

			}
			wp_die();
		}

		/**
		 * Output the shortcode.
		 *
		 * @param array $args
		 * @param string $content
		 */
		public function shortcode_output( $args = array(), $content = '' ) {
			$args = self::argument_values( $args );

			// add extra argument so we know its a output to gutenberg
			//$args
			$args = $this->string_to_bool($args);

			return $this->output( $args, array(), $content );
		}


		/**
		 * Sometimes booleans values can be turned to strings, so we fix that.
		 *
		 * @param $options
		 *
		 * @return mixed
		 */
		public function string_to_bool($options){
			// convert bool strings to booleans
			foreach($options as $key => $val){
				if($val=='false'){ $options[$key] = false;}
				elseif($val=='true'){ $options[$key] = true;}
			}

			return $options;
		}

		/**
		 * Get the argument values that are also filterable.
		 *
		 * @param $instance
		 *
		 * @return array
		 */
		public function argument_values( $instance ) {
			$argument_values = array();

			if ( ! empty( $this->arguments ) ) {
				foreach ( $this->arguments as $key => $args ) {
					// set the input name from the key
					$args['name'] = $key;
					//
					$argument_values[ $key ] = isset( $instance[ $key ] ) ? $instance[ $key ] : '';
					if ( $argument_values[ $key ] == '' && isset( $args['default'] ) ) {
						$argument_values[ $key ] = $args['default'];
					}
				}
			}

			return $argument_values;
		}

		/**
		 * This is the main output class for all 3 items, widget, shortcode and block, it is extended in the calling class.
		 *
		 * @param array $args
		 * @param array $widget_args
		 * @param string $content
		 */
		public function output( $args = array(), $widget_args = array(), $content = '' ) {

		}

		/**
		 * Add the dyanmic block code inline when the wp-block in enqueued.
		 */
		public function register_block() {
			wp_add_inline_script( 'wp-blocks', $this->block() );
		}


		/**
		 * Check if we need to show advanced options.
		 *
		 * @return bool
		 */
		public function block_show_advanced(){
			//$this->arguments
			$show = false;
			$arguments = $this->arguments;
			if(!empty($arguments)){
				foreach($arguments as $argument){
					if(isset($argument['advanced']) && $argument['advanced']){
						$show = true;
					}
				}
			}

			return $show;
		}


		/**
		 * Output the JS for building the dynamic Guntenberg block.
		 *
		 * @return mixed
		 */
		public function block() {
			ob_start();
			?>
			<script>
				/**
				 * BLOCK: Basic
				 *
				 * Registering a basic block with Gutenberg.
				 * Simple block, renders and saves the same content without any interactivity.
				 *
				 * Styles:
				 *        editor.css — Editor styles for the block.
				 *        style.css  — Editor & Front end styles for the block.
				 */
				(function () {
					var __ = wp.i18n.__; // The __() for internationalization.
					var el = wp.element.createElement; // The wp.element.createElement() function to create elements.
					var editable = wp.blocks.Editable;
					var blocks = wp.blocks;
					var registerBlockType = wp.blocks.registerBlockType; // The registerBlockType() to register blocks.
					var is_fetching = false;
					var prev_attributes = [];

					/**
					 * Register Basic Block.
					 *
					 * Registers a new block provided a unique name and an object defining its
					 * behavior. Once registered, the block is made available as an option to any
					 * editor interface where blocks are implemented.
					 *
					 * @param  {string}   name     Block name.
					 * @param  {Object}   settings Block settings.
					 * @return {?WPBlock}          The block, if it has been successfully
					 *                             registered; otherwise `undefined`.
					 */
					registerBlockType('<?php echo str_replace( "_", "-", sanitize_title_with_dashes( $this->options['textdomain'] ) . '/' . sanitize_title_with_dashes( $this->options['class_name'] ) );  ?>', { // Block name. Block names must be string that contains a namespace prefix. Example: my-plugin/my-custom-block.
						title: '<?php echo $this->options['name'];?>', // Block title.
						description: '<?php echo esc_attr( $this->options['widget_ops']['description'] )?>', // Block title.
						icon: '<?php echo isset( $this->options['block-icon'] ) ? esc_attr( $this->options['block-icon'] ) : 'shield-alt';?>', // Block icon from Dashicons → https://developer.wordpress.org/resource/dashicons/.
						category: '<?php echo isset( $this->options['block-category'] ) ? esc_attr( $this->options['block-category'] ) : 'common';?>', // Block category — Group blocks together based on common traits E.g. common, formatting, layout widgets, embed.
						<?php if ( isset( $this->options['block-keywords'] ) ) {
						echo "keywords : " . $this->options['block-keywords'] . ",";
					}?>

						<?php

						$show_advanced = $this->block_show_advanced();

						$show_alignment = false;

						if ( ! empty( $this->arguments ) ) {
							echo "attributes : {";

							if ( $show_advanced ) {
								echo "show_advanced: {";
								echo "	type: 'boolean',";
								echo "  default: false,";
								echo "},";
							}

							foreach ( $this->arguments as $key => $args ) {

								// set if we should show alignment
								if ( $key == 'alignment' ) {
									$show_alignment = true;
								}

								$extra = '';

								if ( $args['type'] == 'checkbox' ) {
									$type    = 'boolean';
									$default = isset( $args['default'] ) && "'" . $args['default'] . "'" ? 'true' : 'false';
								} elseif ( $args['type'] == 'number' ) {
									$type    = 'number';
									$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
								}elseif ( $args['type'] == 'select' && !empty($args['multiple'])) {
									$type    = 'array';
									$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
								}
								elseif ( $args['type'] == 'multiselect') {
									$type    = 'array';
									$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
								} else {
									$type    = 'string';
									$default = isset( $args['default'] ) ? "'" . $args['default'] . "'" : "''";
								}
								echo $key . " : {";
								echo "type : '$type',";
								echo "default : $default,";
								echo "},";
							}

							echo "content : {type : 'string',default: 'Please select the attributes in the block settings'},";

							echo "},";

						}

						?>

						// The "edit" property must be a valid function.
						edit: function (props) {

							var content = props.attributes.content;

							function onChangeContent() {

								if (!is_fetching && prev_attributes[props.id] != props.attributes) {

									//console.log(props);

									is_fetching = true;
									var data = {
										'action': 'super_duper_output_shortcode',
										'shortcode': '<?php echo $this->options['base_id'];?>',
										'attributes': props.attributes,
										'post_id': <?php global $post; if ( isset( $post->ID ) ) {
										echo $post->ID;
									}?>,
										'_ajax_nonce': '<?php echo wp_create_nonce( 'super_duper_output_shortcode' );?>'
									};

									jQuery.post(ajaxurl, data, function (response) {
										return response;
									}).then(function (env) {
										props.setAttributes({content: env});
										is_fetching = false;
										prev_attributes[props.id] = props.attributes;
									});


								}

								return props.attributes.content;

							}


							return [

								!!props.focus && el(blocks.BlockControls, {key: 'controls'},

									<?php if($show_alignment){?>
									el(
										blocks.AlignmentToolbar,
										{
											value: props.attributes.alignment,
											onChange: function (alignment) {
												props.setAttributes({alignment: alignment})
											}
										}
									)
									<?php }?>

								),

								!!props.focus && el(blocks.InspectorControls, {key: 'inspector'},

									<?php

									if(! empty( $this->arguments )){

									if ( $show_advanced ) {
									?>
									el(
										wp.components.ToggleControl,
										{
											label : 'Show Advanced Settings?',
											checked : props.attributes.show_advanced,
											onChange: function ( show_advanced ) {
												props.setAttributes({ show_advanced: ! props.attributes.show_advanced } )
											}
										}
									),
									<?php

									}

									foreach($this->arguments as $key => $args){
									$options = '';
									$extra = '';
									$require = '';
									$onchange = "props.setAttributes({ $key: $key } )";
									$value = "props.attributes.$key";
									$text_type = array( 'text', 'password', 'number', 'email', 'tel', 'url', 'color' );
									if ( in_array( $args['type'], $text_type ) ) {
										$type = 'TextControl';
									} elseif ( $args['type'] == 'checkbox' ) {
										$type = 'CheckboxControl';
										$extra .= "checked: props.attributes.$key,";
										$onchange = "props.setAttributes({ $key: ! props.attributes.$key } )";
									} elseif ( $args['type'] == 'select' || $args['type'] == 'multiselect' ) {
										$type = 'SelectControl';
										if ( ! empty( $args['options'] ) ) {
											$options .= "options  : [";
											foreach ( $args['options'] as $option_val => $option_label ) {
												$options .= "{ value : '" . esc_attr( $option_val ) . "',     label : '" . esc_attr( $option_label ) . "'     },";
											}
											$options .= "],";
										}
										if(isset($args['multiple']) && $args['multiple']){ //@todo multiselect does not work at the moment: https://github.com/WordPress/gutenberg/issues/5550
											$extra .= ' multiple: true, ';
											//$onchange = "props.setAttributes({ $key: ['edit'] } )";
											//$value = "['edit', 'delete']";
										}
									}
									elseif ( $args['type'] == 'alignment' ) {
										$type = 'AlignmentToolbar'; // @todo this does not seem to work but cant find a example
									} else {
										continue;// if we have not implemented the control then don't break the JS.
									}

									// add show only if advanced
									if(!empty($args['advanced'])){
										echo "props.attributes.show_advanced && ";
									}
									// add setting require if defined
									if(!empty($args['element_require'])){
										echo $this->block_props_replace( $args['element_require'], true )." && ";
									}
									?>
									el(
										wp.components.<?php echo esc_attr( $type );?>,
										{
											label: '<?php echo esc_attr( $args['title'] );?>',
											help: '<?php if(isset($args['desc'] )) echo esc_attr( $args['desc'] );?>',
											value: <?php echo $value ;?>,
											<?php if ( $type == 'TextControl' && $args['type'] != 'text' ) {
											echo "type: '" . esc_attr( $args['type'] ) . "',";
										}?>
											<?php if ( ! empty( $args['placeholder'] ) ) {
											echo "placeholder: '" . esc_attr( $args['placeholder'] ) . "',";
										}?>
											<?php echo $options;?>
											<?php echo $extra;?>
											onChange: function ( <?php echo $key;?> ) {
												<?php echo $onchange;?>
											}
										}
									),
									<?php
									}
									}
									?>

								),

								<?php
								// If the user sets block-output array then build it
								if ( ! empty( $this->options['block-output'] ) ) {
								$this->block_element( $this->options['block-output'] );
							}else{
								// if no block-output is set then we try and get the shortcode html output via ajax.
								?>
								el('div', {
									dangerouslySetInnerHTML: {__html: onChangeContent()},
									className: props.className,
									style: {'min-height': '30px'}
								})
								<?php
								}
								?>
							]; // end return
						},

						// The "save" property must be specified and must be a valid function.
						save: function (props) {

							console.log(props);


							var attr = props.attributes;
							var align = '';

							// build the shortcode.
							var content = "[<?php echo $this->options['base_id'];?>";
							<?php

							if(! empty( $this->arguments )){
							foreach($this->arguments as $key => $args){
							?>
							if (attr.hasOwnProperty("<?php echo esc_attr( $key );?>")) {
								content += " <?php echo esc_attr( $key );?>='" + attr.<?php echo esc_attr( $key );?>+ "' ";
							}
							<?php
							}
							}

							?>
							content += "]";


							// @todo should we add inline style here or just css classes?
							if (attr.alignment) {
								if (attr.alignment == 'left') {
									align = 'alignleft';
								}
								if (attr.alignment == 'center') {
									align = 'aligncenter';
								}
								if (attr.alignment == 'right') {
									align = 'alignright';
								}
							}

							console.log(content);
							return el('div', {dangerouslySetInnerHTML: {__html: content}, className: align});

						}
					});
				})();
			</script>
			<?php
			$output = ob_get_clean();

			/*
			 * We only add the <script> tags for code highlighting, so we strip them from the output.
			 */

			return str_replace( array(
				'<script>',
				'</script>'
			), '', $output );
		}

		/**
		 * A self looping function to create the output for JS block elements.
		 *
		 * This is what is output in the WP Editor visual view.
		 *
		 * @param $args
		 */
		public function block_element( $args ) {


			if ( ! empty( $args ) ) {
				foreach ( $args as $element => $new_args ) {

					if(is_array($new_args)){ // its an element



						if(isset($new_args['element'])){

							//print_r($new_args);

							if(isset($new_args['element_require'])){
								echo str_replace(array("'+","+'"),'',$this->block_props_replace( $new_args['element_require'] ))." &&  ";
								unset($new_args['element_require']);
							}

							echo "\n el( '" . $new_args['element'] . "', {";

							// get the attributes
							foreach($new_args as $new_key => $new_value){


								if($new_key=='element' || $new_key=='content' || $new_key=='element_require' || $new_key=='element_repeat' || is_array($new_value)){
									// do nothing
								}else{
									echo $this->block_element( array($new_key => $new_value) );
								}
							}

							echo "},";// end attributes

							// get the content
							$first_item = 0;
							foreach($new_args as $new_key => $new_value){
								if($new_key === 'content' || is_array($new_value)){
									//echo ",".$first_item;// separate the children


									if($first_item > 0){
										//echo ",";// separate the children
									}else{
										//echo '####'.$first_item;
									}

									if($new_key === 'content'){
										//print_r($new_args);
										echo  "'" . $this->block_props_replace( $new_value ) . "'";
									}

									if(is_array($new_value)){

										if(isset($new_value['element_require'])){
											echo str_replace(array("'+","+'"),'',$this->block_props_replace( $new_value['element_require'] ))." &&  ";
											unset($new_value['element_require']);
										}

										if(isset($new_value['element_repeat'])){
											$x = 1;
											while($x <= absint($new_value['element_repeat'])) {
												$this->block_element(array(''=>$new_value));
												$x++;
											}
										}else{
											$this->block_element(array(''=>$new_value));
										}
										//print_r($new_value);
									}
									$first_item ++;
								}
							}

							echo ")";// end content

							//if($first_item>0){
							echo ", \n";
							//}


						}
						//$this->block_element($new_args);
					}else{

						if(substr( $element, 0, 3 ) === "if_"){
							echo str_replace("if_","",$element). ": " . $this->block_props_replace( $new_args, true) . ",";
						}
						elseif($element=='style'){
							echo $element . ": " . $this->block_props_replace( $new_args ) . ",";
						}else{
							echo $element . ": '" . $this->block_props_replace( $new_args ) . "',";
						}

					}


				}
			}
		}

		/**
		 * Replace block attributes placeholders with the proper naming.
		 *
		 * @param $string
		 *
		 * @return mixed
		 */
		public function block_props_replace( $string, $no_wrap = false ) {

			if($no_wrap){
				$string = str_replace( array( "[%", "%]" ), array( "props.attributes.", "" ), $string );
			}else{
				$string = str_replace( array( "[%", "%]" ), array( "'+props.attributes.", "+'" ), $string );
			}

			return $string;
		}

		/**
		 * Outputs the content of the widget
		 *
		 * @param array $args
		 * @param array $instance
		 */
		public function widget( $args, $instance ) {
			// outputs the content of the widget

			// get the filtered values
			$argument_values = $this->argument_values( $instance );

			$output = $this->output( $argument_values, $args );

			if($output){
				echo $args['before_widget'];
				if ( ! empty( $instance['title'] ) ) {
					echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
				}
				$argument_values = $this->string_to_bool($argument_values);
				echo $output;
				echo $args['after_widget'];
			}
		}

		/**
		 * Outputs the options form inputs for the widget.
		 *
		 * @param array $instance The widget options.
		 */
		public function form( $instance ) {
			if ( is_array( $this->arguments ) ) {
				foreach ( $this->arguments as $key => $args ) {
					$this->widget_inputs( $args, $instance );
				}
			}else{
				echo "<p>".esc_attr($this->options['widget_ops']['description'])."</p>";
			}
		}

		/**
		 * Builds the inputs for the widget options.
		 *
		 * @param $args
		 * @param $instance
		 */
		public function widget_inputs( $args, $instance ) {

//print_r($instance );
			if ( isset( $instance[ $args['name'] ] ) ) {
				$value = $instance[ $args['name'] ];
			} elseif ( !isset( $instance[ $args['name'] ] ) && ! empty( $args['default'] ) ) {
				$value = esc_html( $args['default'] );
			} else {
				$value = '';
			}

			if ( ! empty( $args['placeholder'] ) ) {
				$placeholder = "placeholder='" . esc_html( $args['placeholder'] ) . "'";
			} else {
				$placeholder = '';
			}

			switch ( $args['type'] ) {
				//array('text','password','number','email','tel','url','color')
				case "text":
				case "password":
				case "number":
				case "email":
				case "tel":
				case "url":
				case "color":
					?>
					<p>
						<label
							for="<?php echo esc_attr( $this->get_field_id( $args['name'] ) ); ?>"><?php echo esc_attr( $args['title'] ); ?><?php echo $this->widget_field_desc( $args ); ?></label>
						<input <?php echo $placeholder; ?> class="widefat"
						                                   id="<?php echo esc_attr( $this->get_field_id( $args['name'] ) ); ?>"
						                                   name="<?php echo esc_attr( $this->get_field_name( $args['name'] ) ); ?>"
						                                   type="<?php esc_attr( $args['type'] ); ?>"
						                                   value="<?php echo esc_attr( $value ); ?>">
					</p>
					<?php

					break;
				case "select":
					?>
					<p>
						<label
							for="<?php echo esc_attr( $this->get_field_id( $args['name'] ) ); ?>"><?php echo esc_attr( $args['title'] ); ?><?php echo $this->widget_field_desc( $args ); ?></label>
						<select <?php echo $placeholder; ?> class="widefat"
						                                    id="<?php echo esc_attr( $this->get_field_id( $args['name'] ) ); ?>"
						                                    name="<?php echo esc_attr( $this->get_field_name( $args['name'] ) ); ?>"
							<?php if(isset($args['multiple']) && $args['multiple']){echo "multiple";} //@todo not implemented yet due to gutenberg not supporting it?>
						>
							<?php

							if ( ! empty( $args['options'] ) ) {
								foreach ( $args['options'] as $val => $label ) {
									echo "<option value='$val' " . selected( $value, $val ) . ">$label</option>";
								}
							}
							?>
						</select>
					</p>
					<?php
					break;
				case "checkbox":
					?>
					<p>
						<input <?php echo $placeholder; ?>
							<?php checked( 1, $value, true ) ?>
							class="widefat" id="<?php echo esc_attr( $this->get_field_id( $args['name'] ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_name( $args['name'] ) ); ?>" type="checkbox"
							value="1">
						<label
							for="<?php echo esc_attr( $this->get_field_id( $args['name'] ) ); ?>"><?php echo esc_attr( $args['title'] ); ?><?php echo $this->widget_field_desc( $args ); ?></label>
					</p>
					<?php
					break;
				default:
					echo "No input type found!"; // @todo we need to add more input types.
			}

		}


		/**
		 * Get the widget input description html.
		 *
		 * @param $args
		 *
		 * @return string
		 * @todo, need to make its own tooltip script
		 */
		public function widget_field_desc( $args ) {

			$description = '';
			if ( isset( $args['desc'] ) && $args['desc'] ) {
				if ( isset( $args['desc_tip'] ) && $args['desc_tip'] ) {
					$description = $this->desc_tip( $args['desc'] );
				} else {
					$description = '<span class="description">' . wp_kses_post( $args['desc'] ) . '</span>';
				}
			}

			return $description;
		}


		/**
		 * Get the tool tip html.
		 *
		 * @param $tip
		 * @param bool $allow_html
		 *
		 * @return string
		 */
		function desc_tip( $tip, $allow_html = false ) {
			if ( $allow_html ) {
				$tip = $this->sanitize_tooltip( $tip );
			} else {
				$tip = esc_attr( $tip );
			}

			return '<span class="gd-help-tip dashicons dashicons-editor-help" title="' . $tip . '"></span>';
		}

		/**
		 * Sanitize a string destined to be a tooltip.
		 *
		 * @param string $var
		 *
		 * @return string
		 */
		public function sanitize_tooltip( $var ) {
			return htmlspecialchars( wp_kses( html_entity_decode( $var ), array(
				'br'     => array(),
				'em'     => array(),
				'strong' => array(),
				'small'  => array(),
				'span'   => array(),
				'ul'     => array(),
				'li'     => array(),
				'ol'     => array(),
				'p'      => array(),
			) ) );
		}

		/**
		 * Processing widget options on save
		 *
		 * @param array $new_instance The new options
		 * @param array $old_instance The previous options
		 *
		 * @return array
		 * @todo we should add some sanitation here.
		 */
		public function update( $new_instance, $old_instance ) {
			//save the widget
			$instance = array_merge( (array) $old_instance, (array) $new_instance );

//			print_r($new_instance);
//			print_r($old_instance);
//			print_r($instance);
//			print_r($this->arguments);
//			exit;

			// check for checkboxes
			if(!empty($this->arguments)){
				foreach($this->arguments as $argument){
					if(isset($argument['type']) && $argument['type']=='checkbox' && !isset($new_instance[$argument['name']])){
						$instance[$argument['name']] = '0';
					}
				}
			}

			return $instance;
		}

	}

}
