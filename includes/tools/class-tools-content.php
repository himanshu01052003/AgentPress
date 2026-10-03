<?php
/**
 * Posts, pages, custom post types and taxonomy tools.
 *
 * @package Mcp100p
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Content {

	const STATUSES = array( 'draft', 'publish', 'pending', 'private', 'future' );

	public static function available() {
		return true;
	}

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wp_list_posts',
				'title'       => 'List posts',
				'group'       => 'content',
				'description' => 'List or search posts, pages or any custom post type. Returns summaries (no full content); use wp_get_post for the full post.',
				'input'       => array(
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type, e.g. "post", "page", "product". Default "post".',
					),
					'status'    => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ),
						'description' => 'Default "publish".',
					),
					'search'    => array( 'type' => 'string' ),
					'category'  => array(
						'type'        => 'string',
						'description' => 'Category ID or slug.',
					),
					'tag'       => array(
						'type'        => 'string',
						'description' => 'Tag ID or slug.',
					),
					'author'    => array(
						'type'        => 'integer',
						'description' => 'Author user ID.',
					),
					'parent'    => array(
						'type'        => 'integer',
						'description' => 'Parent post ID (hierarchical types such as pages).',
					),
					'orderby'   => array(
						'type' => 'string',
						'enum' => array( 'date', 'modified', 'title', 'menu_order', 'ID' ),
					),
					'order'     => array(
						'type' => 'string',
						'enum' => array( 'DESC', 'ASC' ),
					),
					'per_page'  => array(
						'type'        => 'integer',
						'description' => '1–100, default 20.',
					),
					'page'      => array( 'type' => 'integer' ),
				),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_posts' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_get_post',
				'title'       => 'Get post',
				'group'       => 'content',
				'description' => 'Get one post/page/custom post by ID, including raw content (HTML or block markup), terms, featured image, template and public custom fields.',
				'input'       => array( 'id' => array( 'type' => 'integer' ) ),
				'required'    => array( 'id' ),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'get_post' ),
			)
		);

		$writable = self::writable_props();

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_create_post',
				'title'       => 'Create post',
				'group'       => 'content',
				'description' => 'Create a post, page or custom post. Status defaults to "draft". Content can be HTML or Gutenberg block markup.',
				'input'       => array_merge(
					array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Default "post".',
						),
					),
					$writable
				),
				'required'    => array( 'title' ),
				'callback'    => array( __CLASS__, 'create_post' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_update_post',
				'title'       => 'Update post',
				'group'       => 'content',
				'description' => 'Update an existing post/page/custom post. Only the fields you pass are changed. Passing categories, tags or terms replaces the existing ones for that taxonomy.',
				'input'       => array_merge( array( 'id' => array( 'type' => 'integer' ) ), $writable ),
				'required'    => array( 'id' ),
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'update_post' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_delete_post',
				'title'       => 'Delete post',
				'group'       => 'content',
				'description' => 'Move a post/page to the trash, or delete it permanently with force=true.',
				'input'       => array(
					'id'    => array( 'type' => 'integer' ),
					'force' => array(
						'type'        => 'boolean',
						'description' => 'Skip the trash and delete permanently. Default false.',
					),
				),
				'required'    => array( 'id' ),
				'destructive' => true,
				'callback'    => array( __CLASS__, 'delete_post' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_list_terms',
				'title'       => 'List terms',
				'group'       => 'content',
				'description' => 'List categories, tags or terms of any taxonomy.',
				'input'       => array(
					'taxonomy'   => array(
						'type'        => 'string',
						'description' => 'e.g. "category", "post_tag", "product_cat". Default "category".',
					),
					'search'     => array( 'type' => 'string' ),
					'parent'     => array( 'type' => 'integer' ),
					'hide_empty' => array( 'type' => 'boolean' ),
					'per_page'   => array(
						'type'        => 'integer',
						'description' => '1–500, default 100.',
					),
					'page'       => array( 'type' => 'integer' ),
				),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_terms' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_create_term',
				'title'       => 'Create term',
				'group'       => 'content',
				'description' => 'Create a category, tag or term in any taxonomy.',
				'input'       => array(
					'taxonomy'    => array(
						'type'        => 'string',
						'description' => 'Default "category".',
					),
					'name'        => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'parent'      => array(
						'type'        => 'integer',
						'description' => 'Parent term ID (hierarchical taxonomies).',
					),
				),
				'required'    => array( 'name' ),
				'callback'    => array( __CLASS__, 'create_term' ),
			)
		);
	}

	private static function writable_props() {
		$term_list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);

		return array(
			'title'          => array( 'type' => 'string' ),
			'content'        => array(
				'type'        => 'string',
				'description' => 'HTML or Gutenberg block markup, e.g. <!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			),
			'excerpt'        => array( 'type' => 'string' ),
			'status'         => array(
				'type' => 'string',
				'enum' => self::STATUSES,
			),
			'slug'           => array( 'type' => 'string' ),
			'date'           => array(
				'type'        => 'string',
				'description' => 'Publish date in site time, "YYYY-MM-DD HH:MM:SS". Use with status "future" to schedule.',
			),
			'author'         => array(
				'type'        => 'integer',
				'description' => 'Author user ID.',
			),
			'parent'         => array(
				'type'        => 'integer',
				'description' => 'Parent post ID (pages).',
			),
			'menu_order'     => array( 'type' => 'integer' ),
			'password'       => array( 'type' => 'string' ),
			'comment_status' => array(
				'type' => 'string',
				'enum' => array( 'open', 'closed' ),
			),
			'template'       => array(
				'type'        => 'string',
				'description' => 'Page template slug.',
			),
			'categories'     => $term_list + array( 'description' => 'Category IDs or names. Missing names are created.' ),
			'tags'           => $term_list + array( 'description' => 'Tag IDs or names. Missing names are created.' ),
			'terms'          => array(
				'type'                 => 'object',
				'description'          => 'Terms for other taxonomies, e.g. {"product_cat": ["Shoes"]}.',
				'additionalProperties' => $term_list,
			),
			'featured_media' => array(
				'type'        => 'integer',
				'description' => 'Attachment ID for the featured image; 0 removes it.',
			),
			'meta'           => array(
				'type'        => 'object',
				'description' => 'Custom fields to set, {"key": value}. Null deletes a field. Keys starting with "_" are not allowed.',
			),
		);
	}

	public static function list_posts( $args ) {
		$type = sanitize_key( MCP100P_Util::str( $args, 'post_type', 'post' ) );
		$pto  = get_post_type_object( $type );
		if ( ! $pto ) {
			return MCP100P_Util::error( "Unknown post type '$type'." );
		}

		$status = sanitize_key( MCP100P_Util::str( $args, 'status', 'publish' ) );
		if ( ( 'publish' !== $status || ! $pto->public ) && ! current_user_can( $pto->cap->edit_posts ) ) {
			return MCP100P_Util::error( "You are not allowed to list non-public {$pto->label}." );
		}

		$orderby = MCP100P_Util::str( $args, 'orderby', 'date' );
		$order   = strtoupper( MCP100P_Util::str( $args, 'order', 'DESC' ) );
		$query   = array(
			'post_type'           => $type,
			'post_status'         => $status,
			'posts_per_page'      => MCP100P_Util::int( $args, 'per_page', 20, 1, 100 ),
			'paged'               => MCP100P_Util::int( $args, 'page', 1, 1 ),
			'orderby'             => in_array( $orderby, array( 'date', 'modified', 'title', 'menu_order', 'ID' ), true ) ? $orderby : 'date',
			'order'               => 'ASC' === $order ? 'ASC' : 'DESC',
			'perm'                => 'readable',
			'ignore_sticky_posts' => true,
		);

		if ( MCP100P_Util::has( $args, 'search' ) ) {
			$query['s'] = MCP100P_Util::str( $args, 'search' );
		}
		if ( MCP100P_Util::has( $args, 'author' ) ) {
			$query['author'] = MCP100P_Util::int( $args, 'author' );
		}
		if ( MCP100P_Util::has( $args, 'parent' ) ) {
			$query['post_parent'] = MCP100P_Util::int( $args, 'parent' );
		}
		if ( MCP100P_Util::has( $args, 'category' ) ) {
			$category = MCP100P_Util::str( $args, 'category' );
			if ( ctype_digit( $category ) ) {
				$query['cat'] = (int) $category;
			} else {
				$query['category_name'] = $category;
			}
		}
		if ( MCP100P_Util::has( $args, 'tag' ) ) {
			$tag = MCP100P_Util::str( $args, 'tag' );
			if ( ctype_digit( $tag ) ) {
				$query['tag_id'] = (int) $tag;
			} else {
				$query['tag'] = $tag;
			}
		}

		$result = new WP_Query( $query );

		return array(
			'total'       => (int) $result->found_posts,
			'total_pages' => (int) $result->max_num_pages,
			'page'        => $query['paged'],
			'items'       => array_map( array( 'MCP100P_Util', 'post_summary' ), $result->posts ),
		);
	}

	public static function get_post( $args ) {
		$post = get_post( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $post ) {
			return MCP100P_Util::error( 'Post not found.' );
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return MCP100P_Util::error( 'You are not allowed to read this post.' );
		}
		return MCP100P_Util::post_full( $post );
	}

	public static function create_post( $args ) {
		return self::save( $args, null );
	}

	public static function update_post( $args ) {
		$post = get_post( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return MCP100P_Util::error( 'Post not found.' );
		}
		return self::save( $args, $post );
	}

	private static function save( $args, $post ) {
		$is_new    = null === $post;
		$post_type = $is_new ? sanitize_key( MCP100P_Util::str( $args, 'post_type', 'post' ) ) : $post->post_type;
		$pto       = get_post_type_object( $post_type );

		if ( ! $pto || 'attachment' === $post_type ) {
			return MCP100P_Util::error( "Unknown post type '$post_type'. Use wp_site_info to see the available types." );
		}
		if ( $is_new && ! current_user_can( $pto->cap->create_posts ) ) {
			return MCP100P_Util::error( "You are not allowed to create {$pto->label}." );
		}
		if ( ! $is_new && ! current_user_can( 'edit_post', $post->ID ) ) {
			return MCP100P_Util::error( 'You are not allowed to edit this post.' );
		}

		$data = $is_new
			? array(
				'post_type'   => $post_type,
				'post_status' => 'draft',
			)
			: array( 'ID' => $post->ID );

		$fields = array(
			'title'          => 'post_title',
			'content'        => 'post_content',
			'excerpt'        => 'post_excerpt',
			'slug'           => 'post_name',
			'password'       => 'post_password',
			'comment_status' => 'comment_status',
			'template'       => 'page_template',
		);
		foreach ( $fields as $arg => $field ) {
			if ( MCP100P_Util::has( $args, $arg ) ) {
				$data[ $field ] = MCP100P_Util::str( $args, $arg );
			}
		}
		if ( MCP100P_Util::has( $args, 'menu_order' ) ) {
			$data['menu_order'] = MCP100P_Util::int( $args, 'menu_order' );
		}
		if ( MCP100P_Util::has( $args, 'parent' ) ) {
			$data['post_parent'] = MCP100P_Util::int( $args, 'parent', 0, 0 );
		}

		if ( MCP100P_Util::has( $args, 'status' ) ) {
			$status = sanitize_key( MCP100P_Util::str( $args, 'status' ) );
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return MCP100P_Util::error( "Invalid status '$status'." );
			}
			if ( in_array( $status, array( 'publish', 'future', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
				return MCP100P_Util::error( 'You are not allowed to publish. Use status "draft" or "pending".' );
			}
			$data['post_status'] = $status;
		}

		if ( MCP100P_Util::has( $args, 'date' ) ) {
			$date = MCP100P_Util::str( $args, 'date' );
			if ( false === strtotime( $date ) ) {
				return MCP100P_Util::error( "Invalid date '$date'. Use YYYY-MM-DD HH:MM:SS." );
			}
			$data['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
			$data['post_date_gmt'] = get_gmt_from_date( $data['post_date'] );
			$data['edit_date']     = true;
		}

		if ( MCP100P_Util::has( $args, 'author' ) ) {
			$author_id = MCP100P_Util::int( $args, 'author' );
			if ( ! get_userdata( $author_id ) ) {
				return MCP100P_Util::error( "User $author_id does not exist." );
			}
			if ( get_current_user_id() !== $author_id && ! current_user_can( $pto->cap->edit_others_posts ) ) {
				return MCP100P_Util::error( 'You are not allowed to assign posts to other authors.' );
			}
			$data['post_author'] = $author_id;
		}

		$result = $is_new ? wp_insert_post( wp_slash( $data ), true ) : wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$post_id  = (int) $result;
		$warnings = array();

		// Taxonomies.
		$taxonomies = array();
		if ( MCP100P_Util::has( $args, 'categories' ) ) {
			$taxonomies['category'] = MCP100P_Util::list_arg( $args, 'categories' );
		}
		if ( MCP100P_Util::has( $args, 'tags' ) ) {
			$taxonomies['post_tag'] = MCP100P_Util::list_arg( $args, 'tags' );
		}
		if ( MCP100P_Util::has( $args, 'terms' ) && is_array( $args['terms'] ) ) {
			foreach ( $args['terms'] as $taxonomy => $values ) {
				$taxonomies[ sanitize_key( $taxonomy ) ] = MCP100P_Util::list_arg( array( 'v' => $values ), 'v' );
			}
		}
		foreach ( $taxonomies as $taxonomy => $values ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				$warnings[] = "Taxonomy '$taxonomy' does not apply to '$post_type'; skipped.";
				continue;
			}
			if ( ! current_user_can( get_taxonomy( $taxonomy )->cap->assign_terms ) ) {
				$warnings[] = "You are not allowed to assign '$taxonomy' terms; skipped.";
				continue;
			}
			$ids = MCP100P_Util::resolve_terms( $values, $taxonomy );
			if ( is_wp_error( $ids ) ) {
				$warnings[] = $ids->get_error_message();
				continue;
			}
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}

		// Featured image.
		if ( MCP100P_Util::has( $args, 'featured_media' ) ) {
			$media_id = MCP100P_Util::int( $args, 'featured_media' );
			if ( 0 === $media_id ) {
				delete_post_thumbnail( $post_id );
			} elseif ( 'attachment' !== get_post_type( $media_id ) ) {
				$warnings[] = "Attachment $media_id not found; featured image not set.";
			} else {
				set_post_thumbnail( $post_id, $media_id );
			}
		}

		// Custom fields.
		if ( MCP100P_Util::has( $args, 'meta' ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				$key = (string) $key;
				if ( is_protected_meta( $key, 'post' ) || ! current_user_can( 'edit_post_meta', $post_id, $key ) ) {
					$warnings[] = "Custom field '$key' is protected; skipped.";
					continue;
				}
				if ( null === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, wp_slash( $value ) );
				}
			}
		}

		$out = MCP100P_Util::post_full( get_post( $post_id ) );
		if ( $warnings ) {
			$out['warnings'] = $warnings;
		}
		return $out;
	}

	public static function delete_post( $args ) {
		$post = get_post( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $post || 'attachment' === $post->post_type ) {
			return MCP100P_Util::error( 'Post not found. Use wp_delete_media for attachments.' );
		}
		if ( ! current_user_can( 'delete_post', $post->ID ) ) {
			return MCP100P_Util::error( 'You are not allowed to delete this post.' );
		}

		$force  = MCP100P_Util::bool( $args, 'force' );
		$result = $force ? wp_delete_post( $post->ID, true ) : wp_trash_post( $post->ID );
		if ( ! $result ) {
			return MCP100P_Util::error( 'The post could not be deleted.' );
		}

		return array(
			'id'      => (int) $post->ID,
			'deleted' => true,
			'trashed' => ! $force && 'trash' === get_post_status( $post->ID ),
		);
	}

	public static function list_terms( $args ) {
		$taxonomy = sanitize_key( MCP100P_Util::str( $args, 'taxonomy', 'category' ) );
		$tax      = get_taxonomy( $taxonomy );
		if ( ! $tax ) {
			return MCP100P_Util::error( "Unknown taxonomy '$taxonomy'." );
		}
		if ( ! $tax->public && ! current_user_can( $tax->cap->manage_terms ) ) {
			return MCP100P_Util::error( "You are not allowed to list '$taxonomy' terms." );
		}

		$per_page = MCP100P_Util::int( $args, 'per_page', 100, 1, 500 );
		$page     = MCP100P_Util::int( $args, 'page', 1, 1 );
		$query    = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => MCP100P_Util::bool( $args, 'hide_empty' ),
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
		);
		if ( MCP100P_Util::has( $args, 'search' ) ) {
			$query['search'] = MCP100P_Util::str( $args, 'search' );
		}
		if ( MCP100P_Util::has( $args, 'parent' ) ) {
			$query['parent'] = MCP100P_Util::int( $args, 'parent' );
		}

		$terms = get_terms( $query );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$count_query = $query;
		unset( $count_query['number'], $count_query['offset'] );

		return array(
			'taxonomy' => $taxonomy,
			'total'    => (int) wp_count_terms( $count_query ),
			'page'     => $page,
			'items'    => array_map( array( 'MCP100P_Util', 'term_item' ), $terms ),
		);
	}

	public static function create_term( $args ) {
		$taxonomy = sanitize_key( MCP100P_Util::str( $args, 'taxonomy', 'category' ) );
		$tax      = get_taxonomy( $taxonomy );
		if ( ! $tax ) {
			return MCP100P_Util::error( "Unknown taxonomy '$taxonomy'." );
		}
		if ( ! current_user_can( $tax->cap->edit_terms ) ) {
			return MCP100P_Util::error( "You are not allowed to create '$taxonomy' terms." );
		}

		$extra = array();
		foreach ( array( 'slug', 'description' ) as $key ) {
			if ( MCP100P_Util::has( $args, $key ) ) {
				$extra[ $key ] = MCP100P_Util::str( $args, $key );
			}
		}
		if ( MCP100P_Util::has( $args, 'parent' ) ) {
			$extra['parent'] = MCP100P_Util::int( $args, 'parent' );
		}

		$result = wp_insert_term( MCP100P_Util::str( $args, 'name' ), $taxonomy, $extra );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return MCP100P_Util::term_item( get_term( $result['term_id'], $taxonomy ) );
	}
}
