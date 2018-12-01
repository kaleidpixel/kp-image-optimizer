<?php
/**
 * PHP 5.6 or later
 *
 * @package    KALEIDPIXEL
 * @author     KUCKLU <hello@kuck1u.me>
 * @copyright  2018 Kaleid Pixel
 * @license    GNU General Public License v2.0 or later version
 * @version    0.0.1
 **/

namespace KALEIDPIXEL\WP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_ImageOptimizer
 *
 * @package KALEIDPIXEL
 */
class WP_LazyloadImage {
	/**
	 * @var array Holds the instance of this class
	 */
	private static $instance = array();

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
	 * LazyloadImage constructor.
	 */
	protected function __construct() {
		if ( apply_filters( 'KP_IMAGE_OPTIMIZER_LAZYLOAD_SWITCHER', true ) === true && ! is_admin() ) {
			add_filter( 'wp_get_attachment_image_attributes', array( &$this, 'wp_get_attachment_image_attributes' ) );
			add_filter( 'the_content', array( &$this, 'create_attr' ) );
			add_filter( 'get_header_image_tag', array( &$this, 'create_attr' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_head', array( &$this, 'wp_head' ) );
		}
	}

	/**
	 * Filters the list of attachment image attributes.
	 *
	 * @param array $attr Attributes for the image markup.
	 *
	 * @return mixed
	 */
	public function wp_get_attachment_image_attributes( $attr ) {
		if ( ! is_admin() ) {
			$attr['data-src']    = $attr['src'];
			$attr['data-srcset'] = $attr['srcset'];
			$attr['class']       = "{$attr['class']} lazyload";

			unset( $attr['src'], $attr['srcset'] );
		}

		return $attr;
	}

	/**
	 * Rewrite attributes of img element for Lazyload.
	 *
	 * @param string $content
	 *
	 * @return string|null
	 */
	public function create_attr( $content = '' ) {
		return self::__create_attr( $content );
	}

	/**
	 * Load scripts.
	 */
	public function wp_enqueue_scripts() {
		$threshold = (int) apply_filters( 'KP_IMAGE_OPTIMIZER_LAZYLOAD_OPTIONS_THRESHOLD', 70 );
		$to_webp   = (int) apply_filters( 'KP_IMAGE_OPTIMIZER_LAZYLOAD_OPTIONS_2WEBP', true );

		switch( $to_webp ) {
			case true:
			default:
				$to_webp = '!0';
				break;
			case false:
				$to_webp = '!1';
				break;
		}

		wp_enqueue_script( 'kpio-lazyload', KP_IMAGE_OPTIMIZER_URL . '/assets/js/lazyload.min.js', array(), '8.17.0', true );
		wp_add_inline_script( 'kpio-lazyload', "var kpio_ll = new LazyLoad({elements_selector:'.lazyload',threshold:{$threshold},to_webp:{$to_webp}});" );
	}

	/**
	 * Echo style.
	 */
	public function wp_head() {
		echo '<style>img:not([src]):not([srcset]){visibility: hidden;}</style>';
	}

	/**
	 * Rewrite attributes of img element for Lazyload.
	 *
	 * @param string $content
	 *
	 * @return string|null
	 */
	protected function __create_attr( $content = '' ) {
		if ( ! empty( preg_match_all( '/(<img[^>]*)/', $content, $matches ) ) ) {
			foreach ( $matches[1] as $k => $v ) {
				if ( ! preg_match( '/(<img[^>]*)\s+class="([^"]*)"/', $v ) ) {
					$v_temp  = preg_replace('/(<img[^>]*)\s+src="([^"]*)"/', '$1 src="$2" class="" ', $v );
					$content = str_replace( $v, $v_temp, $content );

					unset( $v_temp );
				}

				unset( $matches[1][ $k ] );
			}
		}

		$content = preg_replace('/(<img[^>]*)\s+class="([^"]*)"/', '$1 class="$2 lazyload"', $content);
		$content = preg_replace('/(<img[^>]*)\s+src=/', '$1 data-src=', $content);
		$content = preg_replace('/(<img[^>]*)\s+srcset=/', '$1 data-srcset=', $content);

		return $content;
	}
}
