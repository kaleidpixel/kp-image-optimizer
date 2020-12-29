<?php
/**
 * PHP 5.6 or later
 *
 * @package    KALEIDPIXEL
 * @author     KUCKLU <kuck1u@users.noreply.github.com>
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
		if ( is_admin() ) {
			return;
		}

		if ( apply_filters( 'KP_IMAGE_OPTIMIZER_LAZYLOAD_SWITCHER', true ) === true ) {
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
			if ( isset( $attr['src'] ) ) {
				$attr['data-src'] = $attr['src'];
                $attr['src']      = 'data:image/gif;base64,R0lGODlhAQABAGAAACH5BAEKAP8ALAAAAAABAAEAAAgEAP8FBAA7';
			}

			if ( isset( $attr['srcset'] ) ) {
				$attr['data-srcset'] = $attr['srcset'];

				unset( $attr['srcset'] );
			}

			if ( isset( $attr['class'] ) ) {
				$attr['class'] = "{$attr['class']} lazyload";
			}
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
		wp_enqueue_script( 'kpio-lazyload', KP_IMAGE_OPTIMIZER_URL . '/assets/js/lazyload.min.js', array(), '8.17.0', true );
		wp_enqueue_script( 'kpio-lazyload-config', KP_IMAGE_OPTIMIZER_URL . '/assets/js/lazyload.config.min.js', array(), '0.1.0', true );
	}

	/**
	 * Echo style.
	 */
	public function wp_head() {
        echo '<style>.lazyload:not(.loaded){visibility: hidden;}</style>';
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
				if ( ! preg_match( '/(<img[^>]*)\s+class=["|\']([\w\-\s]*)["|\']/', $v ) ) {
					$temp    = preg_replace( '/(<img[^>]*)\s+src=["|\']([^"|\']*)["|\']/', '$1 src="$2" class="" ', $v );
					$content = str_replace( $v, $temp, $content );
					$v       = $temp;

					unset( $temp );
				}

				if ( preg_match( '/<img[^>]*\s+src=["|\']([^"|\']*)["|\']/', $v, $src ) ) {
					$src    = wp_parse_url( $src[1] );
					$wp_url = wp_parse_url( home_url() );

					if ( isset( $src['host'] ) && $src['host'] === $wp_url['host'] ) {
						$temp    = preg_replace( '/(<img[^>]*)\s+class=["|\']([\w\-\s]*)["|\']/', '$1 class="$2 lazyload"', $v );
						$temp    = preg_replace( '/(<img[^>]*)\s+src=/', '$1 src="data:image/gif;base64,R0lGODlhAQABAGAAACH5BAEKAP8ALAAAAAABAAEAAAgEAP8FBAA7" data-src=', $temp );
						$temp    = preg_replace( '/(<img[^>]*)\s+srcset=/', '$1 data-srcset=', $temp );
						$content = str_replace( $v, $temp, $content );
					}
				}

				unset( $matches[1][ $k ] );
			}
		}

		return $content;
	}
}
