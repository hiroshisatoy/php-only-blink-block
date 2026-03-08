<?php
/**
 * Plugin Name: Blink Block
 * Description: CSS アニメーションで点滅を制御する、PHPのみで構成された点滅ブロックです。
 * Author:      Hiroshi Sato
 * Version:     1.0.1
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;
define( 'BLINK_BLOCK_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' )['Version'] );

function blink_block_register() {
	register_block_type(
		'original-plugin/blink',
		array(
			'title'       => 'Blink',
			'description' => 'Blinkタグを再現した点滅するブロックです。',
			'category'    => 'text',
			'icon'        => 'star-half',
			'attributes' => array(
				'text' => array(
					'type'    => 'string',
					'default' => 'このテキストが点滅します',
					'label'   => 'テキスト',
				),
				'speed' => array(
					'type'    => 'string',
					'enum'    => array( '遅い', '通常', '速い', 'とても速い' ),
					'default' => '通常',
					'label'   => '点滅速度',
				),
				'mode' => array(
					'type'    => 'string',
					'enum'    => array( '表示/非表示', '薄くする' ),
					'default' => '表示/非表示',
					'label'   => '点滅方式',
				),
			),
			'supports' => array(
				'autoRegister' => true,
				'align'        => array( 'wide', 'full' ),
				'color'        => array( 'text' => true, 'background' => true ),
				'spacing'    => array( 'padding' => true, 'margin' => true ),
				'typography' => array( 'fontSize' => true, 'lineHeight' => true, 'textAlign' => true ),
				'shadow' => true
			),
			'render_callback' => 'blink_block_render',
		)
	);
}
add_action( 'init', 'blink_block_register' );

function blink_block_render( array $attributes, string $content, WP_Block $block ) {
	$speed_map = array(
		'遅い'      => 2400,
		'通常'      => 1600,
		'速い'      => 1000,
		'とても速い' => 500,
	);

	$duration_ms = $speed_map[ $attributes['speed'] ] ?? 1600;
	$mode        = $attributes['mode'] ?? '表示/非表示';

	if ( $mode === '薄くする' ) {
		$mode_class = 'blink-block__text--opacity';
	} else {
		$mode_class = 'blink-block__text--visibility';
	}
	$style = sprintf( '--blink-duration: %dms', $duration_ms );

	$wrapper_attrs = get_block_wrapper_attributes(
		array( 'class' => 'blink-block' )
	);

	return sprintf(
		'<div %s><span class="blink-block__text %s" style="%s">%s</span></div>',
		$wrapper_attrs,
		esc_attr( $mode_class ),
		esc_attr( $style ),
		esc_html( $attributes['text'] )
	);
}

function blink_block_register_styles() {
	wp_enqueue_block_style(
		'original-plugin/blink',
		array(
			'handle' => 'blink-block-style',
			'src'    => plugin_dir_url( __FILE__ ) . 'style.css',
			'path'   => plugin_dir_path( __FILE__ ) . 'style.css',
			'ver'    => BLINK_BLOCK_VERSION,
		)
	);
}
add_action( 'init', 'blink_block_register_styles' );

function blink_block_editor_styles() {
	wp_enqueue_style(
		'blink-block-style',
		plugin_dir_url( __FILE__ ) . 'style.css',
		array(),
		BLINK_BLOCK_VERSION
	);
}
add_action( 'enqueue_block_editor_assets', 'blink_block_editor_styles' );