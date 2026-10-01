<?php
/**
 * Media library tools.
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_Media {

	public static function available() {
		return true;
	}

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wp_list_media',
				'title'       => 'List media',
				'group'       => 'media',
				'description' => 'List or search the media library.',
				'input'       => array(
					'search'    => array( 'type' => 'string' ),
					'mime_type' => array(
						'type'        => 'string',
						'description' => 'e.g. "image", "video", "application/pdf".',
					),
					'parent'    => array(
						'type'        => 'integer',
						'description' => 'Only media attached to this post ID.',
					),
					'per_page'  => array(
						'type'        => 'integer',
						'description' => '1–100, default 20.',
					),
					'page'      => array( 'type' => 'integer' ),
				),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_media' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_upload_media',
				'title'       => 'Upload media',
				'group'       => 'media',
				'description' => 'Add a file to the media library from a public URL or from base64 data. Optionally attach it to a post and set it as the featured image.',
				'input'       => array(
					'url'             => array(
						'type'        => 'string',
						'description' => 'Public http(s) URL of the file.',
					),
					'base64'          => array(
						'type'        => 'string',
						'description' => 'File contents as base64 (a data: URI prefix is allowed). Requires filename.',
					),
					'filename'        => array(
						'type'        => 'string',
						'description' => 'File name with extension, e.g. "hero.jpg".',
					),
					'title'           => array( 'type' => 'string' ),
					'alt_text'        => array( 'type' => 'string' ),
					'caption'         => array( 'type' => 'string' ),
					'description'     => array( 'type' => 'string' ),
					'post_id'         => array(
						'type'        => 'integer',
						'description' => 'Attach to this post.',
					),
					'set_as_featured' => array(
						'type'        => 'boolean',
						'description' => 'Make it the featured image of post_id.',
					),
				),
				'open_world'  => true,
				'callback'    => array( __CLASS__, 'upload_media' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_update_media',
				'title'       => 'Update media',
				'group'       => 'media',
				'description' => 'Update the title, alt text, caption or description of a media item.',
				'input'       => array(
					'id'          => array( 'type' => 'integer' ),
					'title'       => array( 'type' => 'string' ),
					'alt_text'    => array( 'type' => 'string' ),
					'caption'     => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
				'required'    => array( 'id' ),
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'update_media' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wp_delete_media',
				'title'       => 'Delete media',
				'group'       => 'media',
				'description' => 'Permanently delete a media item and its files.',
				'input'       => array( 'id' => array( 'type' => 'integer' ) ),
				'required'    => array( 'id' ),
				'destructive' => true,
				'callback'    => array( __CLASS__, 'delete_media' ),
			)
		);
	}

	private static function get_attachment( $args ) {
		$post = get_post( MCP100P_Util::int( $args, 'id' ) );
		return $post && 'attachment' === $post->post_type ? $post : null;
	}

	public static function list_media( $args ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return MCP100P_Util::error( 'You are not allowed to browse the media library.' );
		}

		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => MCP100P_Util::int( $args, 'per_page', 20, 1, 100 ),
			'paged'          => MCP100P_Util::int( $args, 'page', 1, 1 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( MCP100P_Util::has( $args, 'search' ) ) {
			$query['s'] = MCP100P_Util::str( $args, 'search' );
		}
		if ( MCP100P_Util::has( $args, 'mime_type' ) ) {
			$query['post_mime_type'] = MCP100P_Util::str( $args, 'mime_type' );
		}
		if ( MCP100P_Util::has( $args, 'parent' ) ) {
			$query['post_parent'] = MCP100P_Util::int( $args, 'parent' );
		}

		$result = new WP_Query( $query );

		return array(
			'total'       => (int) $result->found_posts,
			'total_pages' => (int) $result->max_num_pages,
			'page'        => $query['paged'],
			'items'       => array_map( array( 'MCP100P_Util', 'media_item' ), $result->posts ),
		);
	}

	public static function upload_media( $args ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return MCP100P_Util::error( 'You are not allowed to upload files.' );
		}

		$post_id = MCP100P_Util::int( $args, 'post_id', 0, 0 );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return MCP100P_Util::error( "You are not allowed to attach files to post $post_id." );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url      = MCP100P_Util::str( $args, 'url' );
		$base64   = MCP100P_Util::str( $args, 'base64' );
		$filename = MCP100P_Util::str( $args, 'filename' );

		if ( '' !== $url ) {
			if ( ! wp_http_validate_url( $url ) ) {
				return MCP100P_Util::error( 'The URL is not a valid public http(s) address.' );
			}
			$tmp = download_url( $url, 60 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			if ( '' === $filename ) {
				$filename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			}
		} elseif ( '' !== $base64 ) {
			if ( '' === $filename ) {
				return MCP100P_Util::error( 'filename is required when uploading base64 data.' );
			}
			$data = base64_decode( preg_replace( '#^data:[^;,]*;base64,#', '', $base64 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $data ) {
				return MCP100P_Util::error( 'base64 data could not be decoded.' );
			}
			if ( strlen( $data ) > wp_max_upload_size() ) {
				return MCP100P_Util::error( 'File exceeds the maximum upload size of ' . size_format( wp_max_upload_size() ) . '.' );
			}
			$tmp = wp_tempnam( $filename );
			file_put_contents( $tmp, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		} else {
			return MCP100P_Util::error( 'Provide either url or base64.' );
		}

		$filename = sanitize_file_name( '' !== $filename ? $filename : 'upload' );
		if ( '' === pathinfo( $filename, PATHINFO_EXTENSION ) && function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $tmp );
			foreach ( wp_get_mime_types() as $extensions => $type ) {
				if ( $type === $mime ) {
					$filename .= '.' . strtok( $extensions, '|' );
					break;
				}
			}
		}

		$post_data = array();
		if ( MCP100P_Util::has( $args, 'title' ) ) {
			$post_data['post_title'] = MCP100P_Util::str( $args, 'title' );
		}
		if ( MCP100P_Util::has( $args, 'caption' ) ) {
			$post_data['post_excerpt'] = MCP100P_Util::str( $args, 'caption' );
		}
		if ( MCP100P_Util::has( $args, 'description' ) ) {
			$post_data['post_content'] = MCP100P_Util::str( $args, 'description' );
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $tmp,
			),
			$post_id,
			null,
			$post_data
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}

		if ( MCP100P_Util::has( $args, 'alt_text' ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( MCP100P_Util::str( $args, 'alt_text' ) ) );
		}
		if ( $post_id && MCP100P_Util::bool( $args, 'set_as_featured' ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}

		return MCP100P_Util::media_item( get_post( $attachment_id ) );
	}

	public static function update_media( $args ) {
		$attachment = self::get_attachment( $args );
		if ( ! $attachment ) {
			return MCP100P_Util::error( 'Media item not found.' );
		}
		if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
			return MCP100P_Util::error( 'You are not allowed to edit this media item.' );
		}

		$data   = array( 'ID' => $attachment->ID );
		$fields = array(
			'title'       => 'post_title',
			'caption'     => 'post_excerpt',
			'description' => 'post_content',
		);
		foreach ( $fields as $arg => $field ) {
			if ( MCP100P_Util::has( $args, $arg ) ) {
				$data[ $field ] = MCP100P_Util::str( $args, $arg );
			}
		}
		if ( count( $data ) > 1 ) {
			$result = wp_update_post( wp_slash( $data ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( MCP100P_Util::has( $args, 'alt_text' ) ) {
			update_post_meta( $attachment->ID, '_wp_attachment_image_alt', sanitize_text_field( MCP100P_Util::str( $args, 'alt_text' ) ) );
		}

		return MCP100P_Util::media_item( get_post( $attachment->ID ) );
	}

	public static function delete_media( $args ) {
		$attachment = self::get_attachment( $args );
		if ( ! $attachment ) {
			return MCP100P_Util::error( 'Media item not found.' );
		}
		if ( ! current_user_can( 'delete_post', $attachment->ID ) ) {
			return MCP100P_Util::error( 'You are not allowed to delete this media item.' );
		}
		if ( ! wp_delete_attachment( $attachment->ID, true ) ) {
			return MCP100P_Util::error( 'The media item could not be deleted.' );
		}
		return array(
			'id'      => (int) $attachment->ID,
			'deleted' => true,
		);
	}
}
