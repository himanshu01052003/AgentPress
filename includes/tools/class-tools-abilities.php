<?php
/**
 * Bridge to the WordPress Abilities API (WordPress 6.9+).
 *
 * Abilities whose registration sets `meta.mcp.public = true` become MCP tools named
 * "ability__<namespace>__<name>". Use the `mcp100p_expose_ability` filter to expose others.
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Abilities {

	public static function available() {
		return function_exists( 'wp_get_abilities' );
	}

	public static function register() {
		foreach ( (array) wp_get_abilities() as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) || ! method_exists( $ability, 'execute' ) ) {
				continue;
			}

			$meta   = method_exists( $ability, 'get_meta' ) ? (array) $ability->get_meta() : array();
			$public = ! empty( $meta['mcp']['public'] );
			if ( ! apply_filters( 'mcp100p_expose_ability', $public, $ability ) ) {
				continue;
			}

			$schema  = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array();
			$wrapped = ! empty( $schema ) && ( ! isset( $schema['type'] ) || 'object' !== $schema['type'] );
			if ( empty( $schema ) ) {
				$schema = MCP100P_Util::schema();
			} elseif ( $wrapped ) {
				// MCP tool inputs must be objects; wrap scalar/array inputs in an "input" property.
				$schema = MCP100P_Util::schema( array( 'input' => $schema ), array( 'input' ) );
			} elseif ( empty( $schema['properties'] ) ) {
				$schema['properties'] = new stdClass();
			}

			$annotations = isset( $meta['annotations'] ) ? (array) $meta['annotations'] : array();

			MCP100P_Tools::add(
				array(
					'name'        => 'ability__' . str_replace( '/', '__', $ability->get_name() ),
					'title'       => method_exists( $ability, 'get_label' ) ? $ability->get_label() : $ability->get_name(),
					'group'       => 'abilities',
					'description' => method_exists( $ability, 'get_description' ) ? $ability->get_description() : '',
					'schema'      => $schema,
					'read_only'   => ! empty( $annotations['readonly'] ),
					'destructive' => ! empty( $annotations['destructive'] ),
					'idempotent'  => ! empty( $annotations['idempotent'] ),
					'callback'    => function ( $args ) use ( $ability, $wrapped ) {
						$input = $wrapped ? ( isset( $args['input'] ) ? $args['input'] : null ) : ( $args ? $args : null );
						return $ability->execute( $input );
					},
				)
			);
		}
	}
}
