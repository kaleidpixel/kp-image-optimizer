<?php
/**
 * PHP 5.6 or later
 *
 * @package    KALEIDPIXEL
 * @author     KUCKLU <oss@kaleidpixel.jp>
 * @copyright  2018 Kaleid Pixel
 * @license    GNU General Public License v2.0 or later version
 * @version    0.0.1
 **/

namespace KALEIDPIXEL\WP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KALEIDPIXEL\Module\ImageOptimizer;

/**
 * Class WP_ImageOptimizer
 *
 * @package KALEIDPIXEL
 */
class WP_ImageOptimizer {
	/**
	 * @var array Holds the instance of this class
	 */
	private static $instance = array();

	/**
	 * @var null
	 */
	public $upload_dir = null;

	/**
	 * @var null
	 */
	public $command_dir = null;

	/**
	 * @var null Image optimizer instance.
	 */
	public $optimizer = null;

	/**
	 * @var null Image optimizer instance.
	 */
	public $option = null;

	/**
	 * @var null Image optimizer instance.
	 */
	public $option_name = null;

	/**
	 * @var null Image optimizer instance.
	 */
	public $option_group = null;

	/**
	 * @var null Image optimizer instance.
	 */
	public $admin_menu_section = null;

	/**
	 * Instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = self::get_called_class();

		if ( ! isset( self::$instance[ $class ] ) ) {
			self::$instance[ $class ] = new $class();
		}

		return self::$instance[ $class ];
	}

	/**
	 * Get my class.
	 *
	 * @return string
	 */
	public static function get_called_class() {
		return get_called_class();
	}

	/**
	 * ImageOptimizer constructor.
	 */
	protected function __construct() {
		$this->upload_dir             = wp_upload_dir();
		$this->command_dir            = apply_filters( 'KP_IMAGE_OPTIMIZER_DIR_BIN', KP_IMAGE_OPTIMIZER_BIN );
		$this->option_name            = 'kp_image_optimize';
		$this->option_group           = 'kp_image_optimize';
		$this->option                 = get_option( $this->option_name );
		$this->admin_menu_section     = 'kp_image_optimize_section';
		$this->optimizer              = ImageOptimizer::get_instance();
		$this->optimizer->image_dir   = trailingslashit( $this->upload_dir['basedir'] );
		$this->optimizer->command_dir = $this->command_dir;

		add_filter( 'upload_mimes', array( &$this, 'allow_svg' ) );
		add_filter( 'wp_check_filetype_and_ext', array( &$this, 'fix_mime_type_svg' ), 75, 4 );
		add_filter( 'wp_handle_upload', array( &$this, 'wp_handle_upload' ) );
		add_filter( 'image_make_intermediate_size', array( &$this, 'image_make_intermediate_size' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( &$this, 'wp_prepare_attachment_for_js' ), 10, 2 );
		add_action( 'delete_attachment', array( &$this, 'delete_attachment' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_init', array( &$this, 'admin_menu_page_fields' ) );
		add_action( 'init', array( &$this, 'init' ) );
		add_action( $this->option_name, array( &$this, 'cron_all_file_optimize' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
	}

	public function wp_handle_upload( $file ) {
		$this->image_optimize( $file['file'] );

		return $file;
	}

	public function image_make_intermediate_size( $filename ) {
		$this->image_optimize( $filename );

		return $filename;
	}

	/**
	 * Setup.
	 */
	public function init() {
		if ( is_admin() ) {
			load_plugin_textdomain( 'kp-image-optimizer', false, plugin_basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );
		}
	}

	/**
	 * Allow SVG Uploads
	 *
	 * @param $mimes
	 *
	 * @return mixed
	 */
	public function allow_svg( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * Fixes the issue in WordPress 4.7.1 being unable to correctly identify SVGs
	 *
	 * @param null $data
	 * @param null $file
	 * @param null $filename
	 * @param null $mimes
	 *
	 * @return null
	 */
	public function fix_mime_type_svg( $data = null, $file = null, $filename = null, $mimes = null ) {
		$ext = isset( $data['ext'] ) ? $data['ext'] : '';

		if ( strlen( $ext ) < 1 ) {
			$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		}

		if ( $ext === 'svg' ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svg';
		} elseif ( $ext === 'svgz' ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svgz';
		}

		return $data;
	}

	/**
     * Browsers may or may not show SVG files properly without a height/width.
     *
     * @see https://ja.wordpress.org/plugins/scalable-vector-graphics-svg/
     *
	 * @param $response
	 * @param $attachment
	 *
	 * @return mixed
	 */
	public function wp_prepare_attachment_for_js( $response, $attachment ) {
		if ( $response['mime'] == 'image/svg+xml' && empty( $response['sizes'] ) ) {
			$svg_file_path = get_attached_file( $attachment->ID );
			$dimensions    = (object) array( 'width' => 0, 'height' => 0 );

			if ( function_exists( 'simplexml_load_file' ) ) {
				$svg        = simplexml_load_file( $svg_file_path );
				$attributes = $svg ? $svg->attributes() : false;

				if ( isset( $attributes->width, $attributes->height ) ) {
					$width      = (string) $attributes->width;
					$height     = (string) $attributes->height;
					$dimensions = (object) array( 'width' => $width, 'height' => $height );
				}
			}

			$response['sizes'] = array(
				'full' => array(
					'url'         => $response['url'],
					'width'       => $dimensions->width,
					'height'      => $dimensions->height,
					'orientation' => $dimensions->width > $dimensions->height ? 'landscape' : 'portrait'
				)
			);
		}

		return $response;
	}

	/**
	 * Browsers may or may not show SVG files properly without a height/width.
	 *
	 * @see https://ja.wordpress.org/plugins/scalable-vector-graphics-svg/
	 */
	public function admin_enqueue_scripts() {
		wp_add_inline_style( 'wp-admin', ".media .media-icon img[src$='.svg'] { width: auto; height: auto; }" );
		wp_add_inline_style( 'wp-admin', "#postimagediv .inside img[src$='.svg'] { width: 100%; height: auto; }" );
	}

	/**
	 * Image Optimize.
	 *
	 * @param string $filename
	 *
	 * @return mixed
	 */
	public function image_optimize( $filename ) {
		switch ( $this->optimizer->get_mime_type( $filename ) ) {
			case 'image/jpeg':
			case 'image/png':
			case 'image/gif':
			case 'image/svg+xml':
				$this->optimizer->optimize( $filename );
				$this->optimizer->convert_to_webp( $filename );
				break;
		}

		return $filename;
	}

	/**
	 * Delete webp files.
	 *
	 * @param int $post_id
	 */
	public function delete_attachment( $post_id ) {
		$meta = get_post_meta( $post_id, '_wp_attachment_metadata', true );

		if ( isset( $meta['file'] ) && ! empty( $meta['file'] ) ) {
			$basename = basename( $meta['file'] );

			$this->delete_webp_file( $meta['file'], $basename );

			unset( $path, $file );

			if ( isset( $meta['sizes'] ) && ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $k => $v ) {
					$this->delete_webp_file( $meta['file'], $v['file'] );
				}
			}
		}

		unset( $meta );
	}

	/**
	 * Delete webp file.
	 *
	 * @param string $original
	 * @param string $webp
	 *
	 * @return bool
	 */
	public function delete_webp_file( $original, $webp ) {
		$webp = $this->optimizer->get_filename_of_webp( $webp );
		$path = str_replace( basename( $original ), $webp, $original );
		$file = path_join( $this->upload_dir['basedir'], $path );

		return wp_delete_file_from_directory( $file, $this->upload_dir['basedir'] );
	}

	/**
	 * Load language file.
	 */
	public function load_plugin_textdomain() {
	}

	/**
	 * Add admin menu.
	 */
	public function admin_menu() {
		add_media_page( __( 'Image Optimize', 'kp-image-optimizer' ), __( 'Image Optimize', 'kp-image-optimizer' ), 'manage_options', 'kp_image_optimize', array(
			&$this,
			'admin_menu_page',
		) );
	}

	/**
	 * Add admin menu page.
	 */
	public function admin_menu_page() {
		if ( isset( $this->option['process'] ) && $this->option['process'] === 'true' ) {
			$submit_attr = array( 'disabled' => 'disabled' );
		} else {
			$submit_attr = array();
		}
		?>
        <div class="wrap">
            <h2><?php echo apply_filters( 'the_title', __( 'Image Optimize', 'kp-image-optimizer' ) ); ?></h2>

            <form method="post" action="options.php" novalidate="novalidate">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( $this->option_group );
				submit_button( esc_attr__( 'Start optimizing', 'kp-image-optimizer' ), 'primary', 'submit', true, $submit_attr );
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Add admin menu page fields.
	 */
	public function admin_menu_page_fields() {
		register_setting( $this->option_group, $this->option_name, array( &$this, 'cron_event_register' ) );
		add_settings_section( $this->admin_menu_section, null, null, $this->option_group );
		add_settings_field( 'progress', __( 'Progress', 'kp-image-optimizer' ), array(
			&$this,
			'admin_menu_render_input_progress',
		), $this->option_group, $this->admin_menu_section );
	}

	/**
	 * Render progress.
	 */
	public function admin_menu_render_input_progress() {
		$current = ( isset( $this->option['current'] ) ) ? $this->option['current'] : 0;
		$total   = ( isset( $this->option['total'] ) ) ? $this->option['total'] : 0;

		if ( isset( $this->option['process'] ) && $this->option['process'] === 'true' ) {
			if ( ! isset( $this->option['total'] ) || ( isset( $this->option['total'] ) && $this->option['total'] === 0 ) ) {
				esc_html_e( 'Searching for files.', 'kp-image-optimizer' );
			} else {
				echo "{$current} / {$total}";
			}
		} else {
			echo '<input type="hidden" name="' . $this->option_name . '[process]" value="true">';
			esc_html_e( 'Under suspension.', 'kp-image-optimizer' );
		}
	}

	/**
	 * Cron event register.
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	public function cron_event_register( $options ) {
		if ( isset( $options['process'] ) ) {
			$now                = time();
			$run                = false;
			$options['process'] = 'true';

			if ( ! isset( $this->option['process'] ) || ( isset( $this->option['process'] ) && $this->option['process'] === 'false' ) ) {
				$run = true;
			}

			if ( $run && ! wp_next_scheduled( $this->option_name ) ) {
				wp_schedule_single_event( $now, $this->option_name );
				spawn_cron( $now );
			}
		}

		return $options;
	}

	/**
	 * Optimize all images.
	 */
	public function cron_all_file_optimize() {
		$ttl  = ini_get( 'max_execution_time' );
		$mode = apply_filters( 'kp_image_optimizer_directory_scan_mode', '' );

		set_time_limit( 0 );

		$this->option['process'] = 'true';
		update_option( $this->option_name, $this->option );

		switch ( $mode ) {
			case 'glob':
				$images = $this->optimizer->get_file_list_in_glob();
				break;
			default:
				$images = $this->optimizer->get_file_list();
				break;
		}

		if ( is_array( $images ) && ! empty( $images ) ) {
			$total                 = count( $images );
			$this->option          = get_option( $this->option_name );
			$this->option['total'] = $total;

			update_option( $this->option_name, $this->option, false );

			foreach ( $images as $k => $v ) {
				$this->image_optimize( $v );
				unset( $images[ $k ] );

				$this->option['current'] = -- $total;

				update_option( $this->option_name, $this->option, false );
			}
		}

		sleep( 1 );

		$this->option['process'] = 'false';
		update_option( $this->option_name, $this->option, false );

		set_time_limit( $ttl );
	}

	/**
	 * Output debug log.
	 *
	 * @param mixed $var
	 */
	public function debug_log( $var ) {
		error_log( print_r( $var, true ) );
	}
}
