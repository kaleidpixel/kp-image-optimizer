<?php
/**
 * PHP 5.6 or later
 *
 * @package    KALEIDPIXEL
 * @author     KUCKLU <hello@kuck1u.me>
 * @copyright  2018 Kaleid Pixel
 * @license    MIT License http://www.opensource.org/licenses/mit-license.html
 * @version    1.0.0
 **/

namespace KALEIDPIXEL\Module;

/**
 * Class ImageOptimizer
 *
 * @package KALEIDPIXEL
 */
class ImageOptimizer {
	/**
	 * @var array Holds the instance of this class
	 */
	private static $instance = array();

	/**
	 * @var string Path of the directory where image files are stored.
	 */
	public $image_dir = './images';

	/**
	 * @var string Path of the directory where command binaries are saved.
	 */
	public $command_dir = '/usr/local/bin';

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
	}

	/**
	 * Get the mime type of the file from the file path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function get_mime_type( $path ) {
		return mime_content_type( $path );
	}

	/**
	 * Create list of all image files in a specific directory.
	 *
	 * @return array|bool
	 */
	public function get_file_list() {
		$result = false;

		if ( file_exists( $this->image_dir ) ) {
			$list     = array();
			$iterator = new \RecursiveDirectoryIterator( $this->image_dir );
			$iterator = new \RecursiveIteratorIterator( $iterator );

			foreach ( $iterator as $info ) {
				if ( $info->isFile() && preg_match( '#.*?(jpe?g|png)#i', $info->getPathname() ) ) {
					$path_name = $info->getPathname();
					$type      = self::get_mime_type( $path_name );

					if ( $type === 'image/jpeg' || $type === 'image/png' ) {
						$list[] = $path_name;
					}

					unset( $path_name, $type );
				}
			}

			$result = $list;
		}

		return $result;
	}

	/**
	 * Image Optimize.
	 *
	 * @param string $file
	 */
	public function optimize( $file ) {
		$type = $this->get_mime_type( $file );

		switch ( $type ) {
			case 'image/jpeg':
				$command = self::get_binary_path( 'jpegtran' );

				exec( "{$command} -progressive -copy none -optimize -outfile '{$file}' '{$file}' 2>&1", $result );
				break;
			case 'image/png':
				$command = self::get_binary_path( 'pngquant' );

				exec( "{$command} --force --output '{$file}' '{$file}' 2>&1", $result );
				break;
			case 'image/gif':
				$command = self::get_binary_path( 'gifsicle' );

				exec( "{$command} -O2 '{$file}' > '{$file}' 2>&1", $result );
				break;
		}
	}

	/**
	 * Generate image in webp format.
	 *
	 * @param string $file
	 */
	public function convert_to_webp( $file ) {
		$type = $this->get_mime_type( $file );

		switch ( $type ) {
			case 'image/jpeg':
			case 'image/png':
				$out     = self::get_filename_of_webp( $file );
				$command = self::get_binary_path( 'cwebp' );

				exec( "{$command} '{$file}' -o '{$out}' 2>&1", $result );
				break;
		}
	}

	/**
	 * Get the file name of webp.
	 *
	 * @param string $file
	 *
	 * @return mixed
	 */
	public function get_filename_of_webp( $file ) {
		$ext  = pathinfo( $file, PATHINFO_EXTENSION );

		return str_replace( ".{$ext}", '.webp', $file );
	}

	/**
	 * Generate path of binary file.
	 *
	 * @param string $bin
	 *
	 * @return string
	 */
	public function get_binary_path( $bin ) {
		$os_dir      = '';
		$ext         = '';
		$command_dir = str_replace( ' ', '\ ', $this->command_dir );
		$command_dir = rtrim( $command_dir, '/' );

		switch ( PHP_OS ) {
			case 'WINNT':
				$os_dir = 'win';
				$ext    = '.exe';
				break;
			case 'Darwin':
				$os_dir = 'mac';
				break;
			case 'SunOS':
				$os_dir = 'sol';
				break;
			case 'FreeBSD':
				$os_dir = 'fbsd';
				break;
			case 'Linux':
				$os_dir = 'linux';
				break;
		}

		return "{$command_dir}/{$os_dir}/{$bin}{$ext}";
	}
}
