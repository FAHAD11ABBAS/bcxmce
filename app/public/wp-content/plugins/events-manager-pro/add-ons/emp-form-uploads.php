<?php
namespace EM\Forms;
use EM\Utils;
use EM_Booking, EM_Ticket_Booking, EM\Uploads\Uploader, EM_Form;

/**
 * Handles uploads for bookings, including an endpoint url for retrieving booking uploads, and serving uploaded booking files by verifying user has permission to view the file.
 */
class Uploads {
	
	public static $data = array();
	public static $endpoint = 'em-form-uploads';
	/**
	 * @var bool    Folder path to upload files to
	 */
	public static $uploads_folder = 'events-manager/form-uploads';
	
	public static function init() {
		// rule flushes are handled by EM itself, we can just add our endpoints
		add_action( 'init', array( static::class, 'add_endpoint' ) );
		add_action( 'template_include', array( static::class, 'template_include' ) );
		add_action( 'em_uploads_api_upload_validate_options_form-uploads', [static::class, 'api_validate_options'], 10, 2 );
	}
	
	public static function add_endpoint(){
		add_rewrite_endpoint(static::$endpoint, EP_ROOT);
	}
	
	public static function get_endpoint_url( $path = null ){
		$url = trailingslashit(get_home_url( null, static::$endpoint ));
		if( !empty($path) ){
			$path = preg_replace('/^\//', '', $path);
			$url .= $path;
		}
		return trailingslashit($url); // endpoint will redirect without a slash
	}

	/**
	 * Quickly builds a URL from the $field_value of an upload field, also accounts for fact that subfolder wasn't added if uploaded via Pro 3.5
	 * @param $uuid
	 * @param $field_value
	 *
	 * @return string   Returns a hash if not found.
	 */
	public static function get_file_url( $uuid, $field, $field_value ) {
		$url = '#';
		if ( !empty($field_value['files'][$uuid]) ) {
			$endpoint_id = !empty($field_value['endpoint']) ? trailingslashit($field_value[ 'endpoint']) : '';
			$file = $field_value['files'][$uuid];
			if ( empty($field_value['subfolder']) ){
				$uploads_dir = Uploader::upload_dir( false, static::$uploads_folder );
				$subfolders = explode('/', str_replace($uploads_dir['url'], '', $file['url'])); // get the endpoint path
				array_pop($subfolders);
				$field_value['subfolder'] = trailingslashit( ltrim( implode('/', $subfolders), '/') );
			}
			$url = static::get_endpoint_url( $field_value['subfolder'] . $endpoint_id . $field['fieldid'] . '/' . $uuid );
		}
		return $url;
	}
	
	public static function get_endpoint_dir( $path = null ) {
		$uploads_dir = Uploader::upload_dir( false, static::$uploads_folder );
		// if uploads were created, make sure the base folder has a .htaccess with deny all to prevent unauthorized acccess
		$htaccess = $uploads_dir['path'] . '/.htaccess';
		if ( !file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "deny from all\n" );
		}
		return $uploads_dir['path'] . '/' . untrailingslashit( ltrim( $path, '/\\' ) );
	}
	
	public static function template_include( $template ) {
		global $wp_query;
		// if this is not a request for json or a singular object then bail
		if ( ( !is_home() && !is_front_page() ) ) {
			return $template;
		}
		// check if endpoint is set
		if ( !isset( $wp_query->query_vars[ static::$endpoint ] ) ) {
			return $template;
		}
		// OK, so we're here, let's find the subfolder we're dealing with
		$full_path = $wp_query->query_vars[ static::$endpoint ];
		// first, determine if we're in subfolders so we can call by object
		$path_parts = explode( '/', $full_path );
		$uploads_dir = Uploader::upload_dir( false, static::$uploads_folder );
		$uploads_path = $uploads_dir['path'];
		$object_path_array = [];
		foreach( $path_parts as $k => $path_part ){
			if ( !is_dir( $uploads_path . '/' . $path_part) ) {
				break;
			}
			// add to uploads dir path and build path array
			$uploads_path .= '/' . $path_part;
			$object_path_array[] = $path_part;
			unset( $path_parts[$k] );
		}
		// determine object path for filters
		$object_name = implode( '/', $object_path_array );
		$object_path = implode( '/', $path_parts );
		do_action('em_forms_uploads_file_' . $object_name, $object_path ); // here we can serve our content
		
		header( "HTTP/1.1 404 Not Found" );
		exit;
	}
	
	/**
	 * Transitionary function until we make forms more OO, moved out of EM_Forms for easier management
	 * @depreacted
	 *
	 * @param $field_options
	 *
	 * @return array
	 */
	public static function get_field_options( $field ) {
		$mapping = array(
			'options_upload_allow_multiple'  => 'allow_multiple',
			'options_upload_max_files'       => 'max_files',
			'options_upload_max_file_size'   => 'max_file_size',
			'options_upload_type'            => 'type',
			'options_upload_extensions'      => 'extensions',
			'options_upload_image_min_width' => 'image_min_width',
			'options_upload_image_max_width' => 'image_max_width',
			'options_upload_image_min_height'=> 'image_min_height',
			'options_upload_image_max_height'=> 'image_max_height',
			'options_upload_disabled'        => 'disabled',
			'required'                       => 'required'
		);
		$options = array();
		foreach ( $mapping as $old_key => $new_key ) {
			if ( isset( $field[$old_key] ) ) {
				$options[$new_key] = $field[$old_key];
			}
		}
		// convert extensions and type into an array
		$options['type'] = !empty($options['type']) ? [ $options['type'] ] : [];
		$options['extensions'] = !empty($options['extensions']) ? explode(',', str_replace(' ', '', $options['extensions'])) : [];
		return $options;
	}
	
	public static function api_validate_options( $options, $file_key ) {
		global $wpdb;
		if ( !empty($_REQUEST['path_id']) ) {
			// load form from path id
			if ( is_numeric($_REQUEST['path_id']) ) {
				$form_id = $_REQUEST['path_id'];
				$sql = $wpdb->prepare("SELECT meta_id, meta_value FROM ".EM_META_TABLE." WHERE meta_id=%d", $form_id);
				$form_data_row = $wpdb->get_row($sql, ARRAY_A);
				if( !empty($form_data_row) ) {
					$form_data = unserialize( $form_data_row['meta_value'] );
					$EM_Form = new EM_Form( $form_data['form'], 'em_bookings_form' );
				}
			} else {
				$EM_Form = new EM_Form( $_REQUEST['path_id'] );
				if ( empty($EM_Form->form_id) ) {
					return $options;
				}
			}
			if ( !empty($EM_Form) ) {
				$field_key = !empty($_REQUEST['field_id']) ? $_REQUEST['field_id'] : $file_key;
				if ( !empty($EM_Form->form_fields[$field_key]) ) {
					$field = $EM_Form->form_fields[$field_key];
					return static::get_field_options( $field );
				}
			}
		}
		return $options;
	}

	
	/**
	 * Transitionary function until we make forms more OO, moved out of EM_Forms for easier management
	 * @deprecated
	 *
	 * @param \EM_Form $EM_Form
	 * @param $field
	 * @param $field_value
	 * @param array $uploader_options Any extra options that'll override field-defined options, edge use case only, such as outputting a disabled upload form for display purposes.
	 *
	 * @return void
	 */
	public static function output_field_input( $EM_Form, $field, $field_value, $uploader_options = [], $data = [] ) {
		$field_name = !empty($field['name']) ? $field['name']:$field['fieldid'];
		if ( empty($field_value) ) {
			$field_value = [];
		}
		if ( empty( $field_value['subfolder'] ) ) {
			$field_value['subfolder'] = $EM_Form->uploads_subfolder ? trailingslashit( $EM_Form->uploads_subfolder ) : '';
		}
		$field_key = $field['fieldid'];
		$field_options = static::get_field_options( $field );
		$default_options = Uploader::get_options( $field_options );
		$options = array_merge( $default_options, $uploader_options );
		$disabled = !empty( $options['disabled'] );
		$props = [];
		if ( $disabled ) $props[] = 'disabled';
		if ( $options['allow_multiple'] ) $props[] = 'multiple';
		$props[] = 'accept="'. implode(', ', Uploader::get_accepted_mime_types($options) )  . '"';
		if ( $options['max_files'] ) {
			$props[] = 'data-max-files = ' .esc_attr( $options['max_files'] );
		}
		// get the data from $_REQUEST or use $data
		if ( $data ) {
			$upload_data = $data;
		} else {
			// grab the $_REQUEST info, just what we need
			$REQUEST = Utils::_request( $EM_Form->_request_path );
			$upload_data = array_intersect_key( $REQUEST, array_flip([$field_key, $field_key . '--names', $field_key . '--deleted']) );
		}
		$upload_data = array_merge( [$field_key => [], $field_key . '--names' => [], $field_key . '--deleted' => [] ], $upload_data );
		?>
		<div class="em-input-upload input <?php if ( $disabled ) echo 'disabled'; ?>">
			<input type="file" <?php if( !$disabled ) echo 'name="'.$field_name.'[]"' ?> class="<?php echo $field['fieldid'] ?> em-uploader" data-field-id="<?php echo esc_attr($field['fieldid']); ?>" data-api-path="form-uploads" data-api-path-id="<?php echo esc_attr( $EM_Form->form_id ); ?>" data-api-nonce="<?php echo wp_create_nonce('em_uploads_api/form-uploads'); ?>" <?php echo implode( ' ', $props); ?>>
			<?php
				if ( !empty($field_value['files']) || !empty($upload_data[$field_key])  ) {
					$endpoint_id = $field_value['endpoint'] ?? '';
					?>
					<ul class="em-input-upload-files em-input-upload-fallback">
						<?php
							if ( !empty($field_value['files']) ) {
								foreach ( $field_value['files'] as $uuid => $file ) {
									?>
									<li data-file_id="<?php echo esc_attr($uuid); ?>">
										<button type="button" class="em-icon em-icon-undo em-tooltip" aria-label="<?php esc_html_e('Undo','em-pro'); ?>"></button>
										<a href="<?php echo esc_url( static::get_file_url( $uuid, $field, $field_value ) ); ?>" target="_blank"><?php echo esc_html( $file['name'] ); ?></a>
										<button type="button" class="em-icon em-icon-trash em-tooltip" aria-label="<?php esc_html_e('Delete','events-manager'); ?>"></button>
									</li>
									<?php
								}
							}
						?>
					</ul>
					<ul class="em-input-upload-files-tbd <?php if ( empty($upload_data[$field_key . '--deleted']) ) echo 'hidden'; ?>">
						<li><?php esc_html_e('Files will be deleted when saving your changes.', 'em-pro'); ?></li>
						<?php
							foreach( $upload_data[$field_key . '--deleted'] as $uuid => $deleted ) {
								?>
								<li data-file_id="<?php echo esc_attr($uuid); ?>">
									<button type="button" class="em-icon em-icon-undo em-tooltip" aria-label="<?php esc_html_e('Undo','em-pro'); ?>"></button>
									<a href="<?php echo esc_url( static::get_file_url( $uuid, $field, $field_value ) ); ?>" target="_blank"><?php echo esc_html( $file['name'] ); ?></a>
									<button type="button" class="em-icon em-icon-trash em-tooltip" aria-label="<?php esc_html_e('Delete','events-manager'); ?>"></button>
								</li>
								<?php
							}
						?>
					</ul>
					<script type="application/json" class="em-uploader-files">
							<?php
							// you can also do this for em-uploader-options and supply overriding options for filepond
							// prepare data for uploaded event image(s)
							$files_data = [];
							if ( !empty($upload_data[$field_key]) ) {
								if ( !is_array($upload_data[$field_key]) ) $upload_data[$field_key] = [$upload_data[$field_key]];
								foreach( $upload_data[$field_key] as $file_id ) {
									if ( !preg_match('/^https?:\/\//', $file_id ) ) {
										$files_data[] = [
											'id' => $file_id,
											'name' => !empty($upload_data[$field_key . '--names'][$file_id]) ? $upload_data[$field_key . '--names'][$file_id] : false,
											'nonce' => wp_create_nonce( 'em_uploads_api_file/em-form/' . $endpoint_id), // nonce for deleting the image
										];
									}
								}
							}
							if ( !empty( $field_value['files'] ) ) {
								foreach ( $field_value['files'] as $uuid => $file ) {
									$endpoint_url = static::get_file_url( $uuid, $field, $field_value );
									$files_data[] = [
										'id' => $uuid, // add a unique ID so we can identify the file should we need to delete it, in this case one image per event so event_id is enough
										'url' => $endpoint_url, // url to get image for preview
										'name' => $file['name'], // file name for display purposes
										'nonce' => wp_create_nonce( 'em_uploads_api_file/em-form/' . $endpoint_id), // nonce for deleting the image
										'deleted' => !empty($upload_data[$field_key.'--deleted'][$uuid]),
									];
								}
							}
							echo json_encode( $files_data );
						?>
					</script>
					<?php
				}
			?>
			<?php
				if ( !$disabled ) {
					if ( !empty($upload_data[$field_key.'--deleted']) ){
						foreach( $upload_data[$field_key.'--deleted'] as $file_id  => $value ) {
							$the_input_name = !empty($EM_Form->_request_path) ? str_replace("[$field_key]", "[$field_key--deleted]", $field_name ) : $field_name . '--deleted';
							?>
							<input type="hidden" name="<?php echo $the_input_name . '['. esc_attr($file_id) .']'; ?>" value="<?php echo esc_attr($value); ?>">
							<?php
						}
					}
					if ( !empty($upload_data[$field_key.'--names']) ){
						foreach( $upload_data[$field_key.'--names'] as $file_id => $file_name ) {
							$the_input_name = !empty($EM_Form->_request_path) ? str_replace("[$field_key]", "[$field_key--names]", $field_name ) : $field_name . '--names';
							?>
							<input type="hidden" name="<?php echo $the_input_name . '['. esc_attr($file_id) .']'; ?>" value="<?php echo esc_attr($file_name); ?>">
							<?php
						}
					}
				}
			?>
			<script type="application/json" class="em-uploader-options">
				<?php
					$json_options = array(
						'imageValidateSizeMinWidth'  => $options['image_min_width'] ?: null,
						'imageValidateSizeMaxWidth'  => $options['image_max_width'] ?: null,
						'imageValidateSizeMinHeight' => $options['image_min_height'] ?: null,
						'imageValidateSizeMaxHeight' => $options['image_max_height'] ?: null,
						'maxFileSize'                => $options['max_file_size'] ?: null,
						'acceptedFileTypes'          => Uploader::get_accepted_mime_types($options),
						'maxFiles'                   => (int) $options['max_files'] ?: null,
					);
					echo json_encode($json_options);
				?>
			</script>
		</div>
		<?php
	}
	
}
Uploads::init();