<?php
/**
 * Shared helpers for tool implementations.
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Util {

	/**
	 * Build a JSON Schema object for a tool's input.
	 */
	public static function schema( array $props = array(), array $required = array() ) {
		$schema = array(
			'type'       => 'object',
			'properties' => $props ? $props : new stdClass(),
		);
		if ( $required ) {
			$schema['required'] = array_values( $required );
		}
		return $schema;
	}

	public static function error( $message, $code = 'mcp100p_error' ) {
		return new WP_Error( $code, $message );
	}

	public static function has( $args, $key ) {
		return is_array( $args ) && array_key_exists( $key, $args ) && null !== $args[ $key ];
	}

	public static function str( $args, $key, $fallback = '' ) {
		if ( ! self::has( $args, $key ) || ! is_scalar( $args[ $key ] ) ) {
			return $fallback;
		}
		return (string) $args[ $key ];
	}

	public static function int( $args, $key, $fallback = 0, $min = null, $max = null ) {
		$value = self::has( $args, $key ) && is_numeric( $args[ $key ] ) ? (int) $args[ $key ] : $fallback;
		if ( null !== $min ) {
			$value = max( $min, $value );
		}
		if ( null !== $max ) {
			$value = min( $max, $value );
		}
		return $value;
	}

	public static function bool( $args, $key, $fallback = false ) {
		if ( ! self::has( $args, $key ) ) {
			return $fallback;
		}
		$value = filter_var( $args[ $key ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		return null === $value ? $fallback : $value;
	}

	/**
	 * Accept an array or a comma-separated string and return a list of values.
	 */
	public static function list_arg( $args, $key ) {
		if ( ! self::has( $args, $key ) ) {
			return array();
		}
		$value = $args[ $key ];
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		$value = is_array( $value ) ? $value : array( $value );
		$value = array_map(
			function ( $v ) {
				return is_string( $v ) ? trim( $v ) : $v;
			},
			$value
		);
		return array_values(
			array_filter(
				$value,
				function ( $v ) {
					return is_scalar( $v ) && '' !== $v;
				}
			)
		);
	}

	/**
	 * Turn a list of term IDs and/or names into term IDs, creating missing
	 * terms when the current user is allowed to.
	 *
	 * @return int[]|WP_Error
	 */
	public static function resolve_terms( array $values, $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax ) {
			return self::error( "Unknown taxonomy '$taxonomy'." );
		}

		$ids = array();
		foreach ( $values as $value ) {
			if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
				$term = get_term( (int) $value, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					return self::error( "Term ID $value does not exist in taxonomy '$taxonomy'." );
				}
				$ids[] = (int) $term->term_id;
				continue;
			}

			$name = trim( (string) $value );
			if ( '' === $name ) {
				continue;
			}
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
			}
			if ( $term ) {
				$ids[] = (int) $term->term_id;
				continue;
			}

			if ( ! current_user_can( $tax->cap->edit_terms ) ) {
				return self::error( "Term '$name' does not exist in '$taxonomy' and you are not allowed to create terms." );
			}
			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$ids[] = (int) $created['term_id'];
		}

		return array_values( array_unique( $ids ) );
	}

	public static function term_item( $term ) {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'taxonomy'    => $term->taxonomy,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	public static function post_summary( WP_Post $post ) {
		$excerpt = $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

		return array(
			'id'       => (int) $post->ID,
			'type'     => $post->post_type,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'slug'     => $post->post_name,
			'date'     => $post->post_date,
			'modified' => $post->post_modified,
			'author'   => (int) $post->post_author,
			'parent'   => (int) $post->post_parent,
			'link'     => get_permalink( $post ),
			'excerpt'  => wp_trim_words( $excerpt, 30 ),
		);
	}

	public static function post_full( WP_Post $post ) {
		$data = self::post_summary( $post );

		$author = get_userdata( (int) $post->post_author );
		$thumb  = (int) get_post_thumbnail_id( $post );

		$data['author_name']    = $author ? $author->display_name : null;
		$data['content']        = $post->post_content;
		$data['excerpt']        = $post->post_excerpt;
		$data['menu_order']     = (int) $post->menu_order;
		$data['comment_status'] = $post->comment_status;
		$data['has_password']   = '' !== $post->post_password;
		$data['template']       = get_page_template_slug( $post );
		$data['edit_link']      = get_edit_post_link( $post->ID, 'raw' );
		$data['featured_media'] = $thumb ? array(
			'id'  => $thumb,
			'url' => wp_get_attachment_url( $thumb ),
		) : null;

		$data['terms'] = array();
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->show_ui ) {
				continue;
			}
			$terms = wp_get_object_terms( $post->ID, $taxonomy->name );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$data['terms'][ $taxonomy->name ] = array_map(
				function ( $term ) {
					return array(
						'id'   => (int) $term->term_id,
						'name' => $term->name,
					);
				},
				$terms
			);
		}

		$data['meta'] = array();
		foreach ( (array) get_post_meta( $post->ID ) as $key => $values ) {
			if ( is_protected_meta( $key, 'post' ) ) {
				continue;
			}
			$values               = array_map( 'maybe_unserialize', (array) $values );
			$data['meta'][ $key ] = 1 === count( $values ) ? $values[0] : $values;
			if ( count( $data['meta'] ) >= 50 ) {
				break;
			}
		}
		$data['meta'] = $data['meta'] ? $data['meta'] : new stdClass();

		return $data;
	}

	public static function media_item( WP_Post $attachment ) {
		$meta = wp_get_attachment_metadata( $attachment->ID );
		$item = array(
			'id'          => (int) $attachment->ID,
			'title'       => $attachment->post_title,
			'url'         => wp_get_attachment_url( $attachment->ID ),
			'mime_type'   => $attachment->post_mime_type,
			'alt_text'    => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'date'        => $attachment->post_date,
			'parent'      => (int) $attachment->post_parent,
		);
		if ( wp_attachment_is_image( $attachment ) ) {
			$item['width']     = isset( $meta['width'] ) ? (int) $meta['width'] : null;
			$item['height']    = isset( $meta['height'] ) ? (int) $meta['height'] : null;
			$item['thumbnail'] = wp_get_attachment_image_url( $attachment->ID, 'thumbnail' );
		}
		return $item;
	}

	public static function user_item( WP_User $user, $full = false ) {
		$item = array(
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'roles'        => array_values( $user->roles ),
			'registered'   => $user->user_registered,
			'url'          => $user->user_url,
		);
		if ( $full ) {
			$item['first_name']  = $user->first_name;
			$item['last_name']   = $user->last_name;
			$item['nickname']    = $user->nickname;
			$item['description'] = $user->description;
			$item['post_count']  = (int) count_user_posts( $user->ID );
			$item['author_url']  = get_author_posts_url( $user->ID );
		}
		return $item;
	}
}
