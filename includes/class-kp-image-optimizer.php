<?php
/**
 * PHP 5.6 or later
 *
 * @package    KALEIDPIXEL
 * @author     KUCKLU <hello@kuck1u.me>
 * @copyright  2018 Kaleid Pixel
 * @license    GNU General Public License v3.0
 * @version    1.0.0
 **/

namespace KALEIDPIXEL\WP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once KP_IMAGE_OPTIMIZER_DIR . '/includes/class-image-optimizer.php';

use KALEIDPIXEL\Module\ImageOptimizer;

/**
 * Class KP_ImageOptimizer
 *
 * @package KALEIDPIXEL
 */
class KP_ImageOptimizer {
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
		$class = get_called_class();

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
		$this->command_dir            = apply_filters( 'KP_IMAGE_OPTIMIZER_DIR_BIN', KP_IMAGE_OPTIMIZER_DIR . '/bin' );
		$this->option_name            = 'kp_image_optimize';
		$this->option_group           = 'kp_image_optimize';
		$this->admin_menu_section     = 'kp_image_optimize_section';
		$this->optimizer              = ImageOptimizer::get_instance();
		$this->optimizer->image_dir   = $this->upload_dir['basedir'];
		$this->optimizer->command_dir = $this->command_dir;

		add_filter( 'wp_handle_upload', array( &$this, 'wp_handle_upload' ) );
		add_filter( 'image_make_intermediate_size', array( &$this, 'image_make_intermediate_size' ) );
		add_action( 'delete_attachment', array( &$this, 'delete_attachment' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_init', array( &$this, 'admin_menu_page_fields' ) );
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
	 * Image Optimize.
	 *
	 * @param string $filename
	 *
	 * @return mixed
	 */
	public function image_optimize( $filename ) {
		$type        = $this->optimizer->get_mime_type( $filename );
		$image_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
		);

		if ( in_array( $type, $image_types, true ) ) {
			$this->chmod_command_files();
			$this->optimizer->optimize( $filename );
			$this->optimizer->convert_to_webp( $filename );
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
		?>
		<div class="wrap">
			<h2><?php echo apply_filters( 'the_title', __( 'Image Optimize', 'kp-image-optimizer' ) ); ?></h2>

			<form method="post" action="options.php" novalidate="novalidate">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( $this->option_group );
				submit_button( esc_attr__( 'Start optimizing', 'kp-image-optimizer' ), 'primary', 'submit', true, array( 'disabled' => 'disabled' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add admin menu page fields.
	 */
	public function admin_menu_page_fields() {
		register_setting( $this->option_group, $this->option_name, array( &$this, 'sanitize_option' ) );
		add_settings_section( $this->admin_menu_section, null, null, $this->option_group );
		add_settings_field( 'test', '<label for="test">Test</label>', array(
				&$this,
				'admin_menu_render_input_test',
			), $this->option_group, $this->admin_menu_section );
	}

	public function admin_menu_render_input_test() {
		echo 'test';
	}

	/**
	 * Sanitize option.
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	public function sanitize_option( $options ) {
		return $options;
	}

	/**
	 * Grant execute permission to the command.
	 */
	public function chmod_command_files() {
		$files = apply_filters( 'KP_IMAGE_OPTIMIZER_COMMANDS', array(
			'cwebp',
			'gifsicle',
			'jpegtran',
			'pngquant',
		) );
		$index = ( is_array( $files ) ) ? count( $files ) : 0;

		for ( $i = 0; $i < $index; $i ++ ) {
			$command = $this->optimizer->get_binary_path( $files[ $i ] );

			chmod( $command, 0755 );

			unset( $files[ $i ] );
		}
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
