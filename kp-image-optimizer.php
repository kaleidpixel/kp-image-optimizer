<?php
/**
 * Plugin Name: KP Image Optimizer
 * Plugin URI: https://github.com/kaleidpixel/wp-image-optimizer
 * Description: This plugin might have the power to shift the world beyond Divergence 1%.
 * Author: Kaleid Pixel (KUCKLU)
 * Version: 0.1.6
 * Author URI: https://www.kaleidpixel.jp
 * Domain Path: /languages
 * Text Domain: kp-image-optimizer
 * License: GNU General Public License v2.0 or later version
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package    WordPress
 * @subpackage KP Image Optimizer
 * @since      0.0.1
 * @author     KUCKLU <oss@kaleidpixel.jp>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KP_IMAGE_OPTIMIZER_DIR', dirname( __FILE__ ) );
define( 'KP_IMAGE_OPTIMIZER_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'KP_IMAGE_OPTIMIZER_BIN', KP_IMAGE_OPTIMIZER_DIR . '/vendor/kaleidpixel/image-optimizer/bin' );

require_once KP_IMAGE_OPTIMIZER_DIR . '/vendor/autoload.php';
require_once KP_IMAGE_OPTIMIZER_DIR . '/includes/class-optimizer.php';
require_once KP_IMAGE_OPTIMIZER_DIR . '/includes/class-lazyload.php';

add_action( 'plugins_loaded', apply_filters( 'KP_IMAGE_OPTIMIZER_INSTANCE', array( 'KALEIDPIXEL\WP\WP_ImageOptimizer', 'get_instance' ) ) );
add_action( 'plugins_loaded', apply_filters( 'KP_IMAGE_OPTIMIZER_LAZYLOAD_INSTANCE', array( 'KALEIDPIXEL\WP\WP_LazyloadImage', 'get_instance' ) ) );
