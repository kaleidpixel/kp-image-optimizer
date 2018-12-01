<?php
/**
 * Plugin Name: KP Image Optimizer
 * Plugin URI: https://github.com/kaleidpixel/wp-image-optimizer
 * Description: This plugin might have the power to shift the world beyond Divergence 1%.
 * Author: Kaleid Pixel (KUCKLU)
 * Version: 0.0.1
 * Author URI: https://www.kaleidpixel.jp
 * Domain Path: /languages
 * Text Domain: kp-image-optimizer
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package    WordPress
 * @subpackage KP Image Optimizer
 * @since      0.0.1
 * @author     KUCKLU <hello@kuck1u.me>
 *             Copyright (C) 2018 Kaleid Pixel
 *
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License as published by
 *             the Free Software Foundation, either version 3 of the License, or
 *             (at your option) any later version.
 *
 *             This program is distributed in the hope that it will be useful,
 *             but WITHOUT ANY WARRANTY; without even the implied warranty of
 *             MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *             GNU General Public License for more details.
 *
 *             You should have received a copy of the GNU General Public License
 *             along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KP_IMAGE_OPTIMIZER_DIR', dirname( __FILE__ ) );
define( 'KP_IMAGE_OPTIMIZER_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'KP_IMAGE_OPTIMIZER_BIN', KP_IMAGE_OPTIMIZER_DIR . '/vendor/kaleidpixel/image-optimizer/bin' );

require_once KP_IMAGE_OPTIMIZER_DIR . '/vendor/autoload.php';
require_once KP_IMAGE_OPTIMIZER_DIR . '/includes/class-kp-image-optimizer.php';

$_KP_IMAGE_OPTIMIZER_INSTANCE = apply_filters( 'KP_IMAGE_OPTIMIZER_INSTANCE', array( 'KALEIDPIXEL\WP\KP_ImageOptimizer', 'get_instance' ) );

add_action( 'plugins_loaded', $_KP_IMAGE_OPTIMIZER_INSTANCE );
