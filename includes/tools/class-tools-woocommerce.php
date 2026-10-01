<?php
/**
 * WooCommerce product and order tools. Registered only when WooCommerce is active.
 *
 * @package AgentPressMCP
 */

defined( 'ABSPATH' ) || exit;

class MCP100P_Tools_WooCommerce {

	public static function available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
	}

	public static function register() {
		MCP100P_Tools::add(
			array(
				'name'        => 'wc_list_products',
				'title'       => 'List products',
				'group'       => 'woocommerce',
				'description' => 'List or search WooCommerce products.',
				'input'       => array(
					'search'       => array( 'type' => 'string' ),
					'status'       => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft', 'pending', 'private', 'any' ),
					),
					'category'     => array(
						'type'        => 'string',
						'description' => 'Product category slug.',
					),
					'type'         => array(
						'type' => 'string',
						'enum' => array( 'simple', 'variable', 'grouped', 'external' ),
					),
					'sku'          => array( 'type' => 'string' ),
					'stock_status' => array(
						'type' => 'string',
						'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
					),
					'per_page'     => array(
						'type'        => 'integer',
						'description' => '1–100, default 20.',
					),
					'page'         => array( 'type' => 'integer' ),
				),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_products' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wc_get_product',
				'title'       => 'Get product',
				'group'       => 'woocommerce',
				'description' => 'Get one product with description, prices, stock, categories, attributes and variation IDs.',
				'input'       => array( 'id' => array( 'type' => 'integer' ) ),
				'required'    => array( 'id' ),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'get_product' ),
			)
		);

		$product_props = array(
			'name'              => array( 'type' => 'string' ),
			'status'            => array(
				'type' => 'string',
				'enum' => array( 'draft', 'pending', 'private', 'publish' ),
			),
			'regular_price'     => array(
				'type'        => 'string',
				'description' => 'e.g. "19.99".',
			),
			'sale_price'        => array(
				'type'        => 'string',
				'description' => 'Empty string removes the sale price.',
			),
			'description'       => array( 'type' => 'string' ),
			'short_description' => array( 'type' => 'string' ),
			'sku'               => array( 'type' => 'string' ),
			'manage_stock'      => array( 'type' => 'boolean' ),
			'stock_quantity'    => array( 'type' => 'integer' ),
			'stock_status'      => array(
				'type' => 'string',
				'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
			),
			'categories'        => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => 'Product category IDs or names. Missing names are created.',
			),
			'tags'              => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => 'Product tag IDs or names.',
			),
			'image_id'          => array(
				'type'        => 'integer',
				'description' => 'Attachment ID of the main image (upload with wp_upload_media first).',
			),
			'gallery_image_ids' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
			'weight'            => array( 'type' => 'string' ),
			'featured'          => array( 'type' => 'boolean' ),
			'virtual'           => array( 'type' => 'boolean' ),
			'product_url'       => array(
				'type'        => 'string',
				'description' => 'External products only.',
			),
			'button_text'       => array(
				'type'        => 'string',
				'description' => 'External products only.',
			),
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wc_create_product',
				'title'       => 'Create product',
				'group'       => 'woocommerce',
				'description' => 'Create a simple or external product. Status defaults to "draft".',
				'input'       => array_merge(
					array(
						'type' => array(
							'type' => 'string',
							'enum' => array( 'simple', 'external' ),
						),
					),
					$product_props
				),
				'required'    => array( 'name' ),
				'callback'    => array( __CLASS__, 'create_product' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wc_update_product',
				'title'       => 'Update product',
				'group'       => 'woocommerce',
				'description' => 'Update a product. Only the fields you pass are changed.',
				'input'       => array_merge( array( 'id' => array( 'type' => 'integer' ) ), $product_props ),
				'required'    => array( 'id' ),
				'idempotent'  => true,
				'callback'    => array( __CLASS__, 'update_product' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wc_list_orders',
				'title'       => 'List orders',
				'group'       => 'woocommerce',
				'description' => 'List WooCommerce orders, newest first.',
				'input'       => array(
					'status'     => array(
						'type'        => 'string',
						'description' => 'e.g. "processing", "completed", "on-hold", "pending", "cancelled", "refunded".',
					),
					'customer'   => array(
						'type'        => 'string',
						'description' => 'Customer user ID or billing email.',
					),
					'date_after' => array(
						'type'        => 'string',
						'description' => 'Only orders created after this date, YYYY-MM-DD.',
					),
					'per_page'   => array(
						'type'        => 'integer',
						'description' => '1–100, default 20.',
					),
					'page'       => array( 'type' => 'integer' ),
				),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_orders' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wc_get_order',
				'title'       => 'Get order',
				'group'       => 'woocommerce',
				'description' => 'Get one order with line items, addresses, totals and notes.',
				'input'       => array( 'id' => array( 'type' => 'integer' ) ),
				'required'    => array( 'id' ),
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'get_order' ),
			)
		);

		MCP100P_Tools::add(
			array(
				'name'        => 'wc_update_order',
				'title'       => 'Update order',
				'group'       => 'woocommerce',
				'description' => 'Change an order status and/or add an order note. Status changes can send emails to the customer.',
				'input'       => array(
					'id'              => array( 'type' => 'integer' ),
					'status'          => array(
						'type'        => 'string',
						'description' => 'New status, e.g. "completed".',
					),
					'note'            => array( 'type' => 'string' ),
					'notify_customer' => array(
						'type'        => 'boolean',
						'description' => 'Send the note to the customer by email. Default false.',
					),
				),
				'required'    => array( 'id' ),
				'callback'    => array( __CLASS__, 'update_order' ),
			)
		);
	}

	/* ---------- Products ---------- */

	public static function list_products( $args ) {
		if ( ! current_user_can( 'edit_products' ) ) {
			return MCP100P_Util::error( 'You are not allowed to manage products.' );
		}

		$status = MCP100P_Util::str( $args, 'status', 'any' );
		$query  = array(
			'limit'    => MCP100P_Util::int( $args, 'per_page', 20, 1, 100 ),
			'page'     => MCP100P_Util::int( $args, 'page', 1, 1 ),
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => 'any' === $status ? array( 'publish', 'draft', 'pending', 'private' ) : $status,
		);
		foreach ( array( 'type', 'sku', 'stock_status' ) as $key ) {
			if ( MCP100P_Util::has( $args, $key ) ) {
				$query[ $key ] = MCP100P_Util::str( $args, $key );
			}
		}
		if ( MCP100P_Util::has( $args, 'category' ) ) {
			$query['category'] = array( MCP100P_Util::str( $args, 'category' ) );
		}
		if ( MCP100P_Util::has( $args, 'search' ) ) {
			$ids              = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'any',
					's'              => MCP100P_Util::str( $args, 'search' ),
					'fields'         => 'ids',
					'posts_per_page' => 500,
				)
			);
			$query['include'] = $ids ? $ids : array( 0 );
		}

		$result = wc_get_products( $query );

		return array(
			'total'       => (int) $result->total,
			'total_pages' => (int) $result->max_num_pages,
			'page'        => $query['page'],
			'items'       => array_map(
				function ( $product ) {
					return self::product_item( $product );
				},
				$result->products
			),
		);
	}

	public static function get_product( $args ) {
		if ( ! current_user_can( 'edit_products' ) ) {
			return MCP100P_Util::error( 'You are not allowed to manage products.' );
		}
		$product = wc_get_product( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $product ) {
			return MCP100P_Util::error( 'Product not found.' );
		}
		return self::product_item( $product, true );
	}

	public static function create_product( $args ) {
		if ( ! current_user_can( 'edit_products' ) ) {
			return MCP100P_Util::error( 'You are not allowed to create products.' );
		}
		$product = 'external' === MCP100P_Util::str( $args, 'type' ) ? new WC_Product_External() : new WC_Product_Simple();
		$product->set_status( 'draft' );
		return self::save_product( $product, $args );
	}

	public static function update_product( $args ) {
		$product = wc_get_product( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $product ) {
			return MCP100P_Util::error( 'Product not found.' );
		}
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return MCP100P_Util::error( 'You are not allowed to edit this product.' );
		}
		return self::save_product( $product, $args );
	}

	private static function save_product( WC_Product $product, $args ) {
		$warnings = array();

		try {
			if ( MCP100P_Util::has( $args, 'name' ) ) {
				$product->set_name( MCP100P_Util::str( $args, 'name' ) );
			}
			if ( MCP100P_Util::has( $args, 'status' ) ) {
				$status = MCP100P_Util::str( $args, 'status' );
				if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_products' ) ) {
					return MCP100P_Util::error( 'You are not allowed to publish products.' );
				}
				$product->set_status( $status );
			}
			if ( MCP100P_Util::has( $args, 'regular_price' ) ) {
				$product->set_regular_price( wc_format_decimal( MCP100P_Util::str( $args, 'regular_price' ) ) );
			}
			if ( MCP100P_Util::has( $args, 'sale_price' ) ) {
				$product->set_sale_price( wc_format_decimal( MCP100P_Util::str( $args, 'sale_price' ) ) );
			}
			foreach ( array( 'description', 'short_description' ) as $key ) {
				if ( MCP100P_Util::has( $args, $key ) ) {
					$value = MCP100P_Util::str( $args, $key );
					$value = current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
					$product->{"set_$key"}( $value );
				}
			}
			if ( MCP100P_Util::has( $args, 'sku' ) ) {
				$product->set_sku( MCP100P_Util::str( $args, 'sku' ) );
			}
			if ( MCP100P_Util::has( $args, 'manage_stock' ) ) {
				$product->set_manage_stock( MCP100P_Util::bool( $args, 'manage_stock' ) );
			}
			if ( MCP100P_Util::has( $args, 'stock_quantity' ) ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( wc_stock_amount( $args['stock_quantity'] ) );
			}
			if ( MCP100P_Util::has( $args, 'stock_status' ) ) {
				$product->set_stock_status( MCP100P_Util::str( $args, 'stock_status' ) );
			}
			if ( MCP100P_Util::has( $args, 'image_id' ) ) {
				$product->set_image_id( MCP100P_Util::int( $args, 'image_id' ) );
			}
			if ( MCP100P_Util::has( $args, 'gallery_image_ids' ) ) {
				$product->set_gallery_image_ids( array_map( 'absint', MCP100P_Util::list_arg( $args, 'gallery_image_ids' ) ) );
			}
			if ( MCP100P_Util::has( $args, 'weight' ) ) {
				$product->set_weight( MCP100P_Util::str( $args, 'weight' ) );
			}
			if ( MCP100P_Util::has( $args, 'featured' ) ) {
				$product->set_featured( MCP100P_Util::bool( $args, 'featured' ) );
			}
			if ( MCP100P_Util::has( $args, 'virtual' ) ) {
				$product->set_virtual( MCP100P_Util::bool( $args, 'virtual' ) );
			}
			if ( $product instanceof WC_Product_External ) {
				if ( MCP100P_Util::has( $args, 'product_url' ) ) {
					$product->set_product_url( esc_url_raw( MCP100P_Util::str( $args, 'product_url' ) ) );
				}
				if ( MCP100P_Util::has( $args, 'button_text' ) ) {
					$product->set_button_text( MCP100P_Util::str( $args, 'button_text' ) );
				}
			}

			$taxonomies = array(
				'categories' => array( 'product_cat', 'set_category_ids' ),
				'tags'       => array( 'product_tag', 'set_tag_ids' ),
			);
			foreach ( $taxonomies as $arg => $info ) {
				if ( ! MCP100P_Util::has( $args, $arg ) ) {
					continue;
				}
				$ids = MCP100P_Util::resolve_terms( MCP100P_Util::list_arg( $args, $arg ), $info[0] );
				if ( is_wp_error( $ids ) ) {
					$warnings[] = $ids->get_error_message();
					continue;
				}
				$product->{$info[1]}( $ids );
			}

			$product->save();
		} catch ( Exception $e ) {
			return MCP100P_Util::error( $e->getMessage() );
		}

		$out = self::product_item( wc_get_product( $product->get_id() ), true );
		if ( $warnings ) {
			$out['warnings'] = $warnings;
		}
		return $out;
	}

	private static function product_item( WC_Product $product, $full = false ) {
		$id    = $product->get_id();
		$image = $product->get_image_id();
		$item  = array(
			'id'             => $id,
			'name'           => $product->get_name(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'sku'            => $product->get_sku(),
			'price'          => $product->get_price(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'stock_status'   => $product->get_stock_status(),
			'manage_stock'   => $product->get_manage_stock(),
			'stock_quantity' => $product->get_stock_quantity(),
			'categories'     => wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) ),
			'link'           => $product->get_permalink(),
			'image'          => $image ? wp_get_attachment_url( $image ) : null,
		);

		if ( $full ) {
			$attributes = array();
			foreach ( $product->get_attributes() as $attribute ) {
				if ( ! $attribute instanceof WC_Product_Attribute ) {
					continue;
				}
				$attributes[] = array(
					'name'      => wc_attribute_label( $attribute->get_name() ),
					'options'   => $attribute->is_taxonomy()
						? wc_get_product_terms( $id, $attribute->get_name(), array( 'fields' => 'names' ) )
						: $attribute->get_options(),
					'variation' => $attribute->get_variation(),
				);
			}

			$item['image_id']          = (int) $image;
			$item['description']       = $product->get_description();
			$item['short_description'] = $product->get_short_description();
			$item['tags']              = wp_get_post_terms( $id, 'product_tag', array( 'fields' => 'names' ) );
			$item['gallery_image_ids'] = $product->get_gallery_image_ids();
			$item['weight']            = $product->get_weight();
			$item['dimensions']        = array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			);
			$item['featured']          = $product->get_featured();
			$item['virtual']           = $product->get_virtual();
			$item['attributes']        = $attributes;
			$item['variation_ids']     = $product->is_type( 'variable' ) ? $product->get_children() : array();
			$item['total_sales']       = (int) $product->get_total_sales();
			if ( $product instanceof WC_Product_External ) {
				$item['product_url'] = $product->get_product_url();
				$item['button_text'] = $product->get_button_text();
			}
		}

		return $item;
	}

	/* ---------- Orders ---------- */

	private static function normalize_status( $status ) {
		$status = sanitize_key( $status );
		return 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
	}

	public static function list_orders( $args ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return MCP100P_Util::error( 'You are not allowed to view orders.' );
		}

		$query = array(
			'limit'    => MCP100P_Util::int( $args, 'per_page', 20, 1, 100 ),
			'page'     => MCP100P_Util::int( $args, 'page', 1, 1 ),
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'type'     => 'shop_order',
		);
		if ( MCP100P_Util::has( $args, 'status' ) ) {
			$query['status'] = array( self::normalize_status( MCP100P_Util::str( $args, 'status' ) ) );
		}
		if ( MCP100P_Util::has( $args, 'customer' ) ) {
			$customer          = MCP100P_Util::str( $args, 'customer' );
			$query['customer'] = ctype_digit( $customer ) ? (int) $customer : $customer;
		}
		if ( MCP100P_Util::has( $args, 'date_after' ) ) {
			$query['date_created'] = '>' . MCP100P_Util::str( $args, 'date_after' );
		}

		$result = wc_get_orders( $query );

		return array(
			'total'       => (int) $result->total,
			'total_pages' => (int) $result->max_num_pages,
			'page'        => $query['page'],
			'items'       => array_map(
				function ( $order ) {
					return self::order_item( $order );
				},
				$result->orders
			),
		);
	}

	public static function get_order( $args ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return MCP100P_Util::error( 'You are not allowed to view orders.' );
		}
		$order = wc_get_order( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $order || ! $order instanceof WC_Order ) {
			return MCP100P_Util::error( 'Order not found.' );
		}
		return self::order_item( $order, true );
	}

	public static function update_order( $args ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return MCP100P_Util::error( 'You are not allowed to edit orders.' );
		}
		$order = wc_get_order( MCP100P_Util::int( $args, 'id' ) );
		if ( ! $order || ! $order instanceof WC_Order ) {
			return MCP100P_Util::error( 'Order not found.' );
		}

		$status = MCP100P_Util::str( $args, 'status' );
		$note   = MCP100P_Util::str( $args, 'note' );
		if ( '' === $status && '' === $note ) {
			return MCP100P_Util::error( 'Provide a status and/or a note.' );
		}

		if ( '' !== $status ) {
			$status = self::normalize_status( $status );
			if ( ! array_key_exists( $status, wc_get_order_statuses() ) ) {
				return MCP100P_Util::error( 'Invalid order status. Valid statuses: ' . implode( ', ', array_map( array( __CLASS__, 'strip_prefix' ), array_keys( wc_get_order_statuses() ) ) ) . '.' );
			}
			$order->update_status( self::strip_prefix( $status ), '', true );
		}
		if ( '' !== $note ) {
			$order->add_order_note( $note, MCP100P_Util::bool( $args, 'notify_customer' ), true );
		}

		return self::order_item( wc_get_order( $order->get_id() ), true );
	}

	public static function strip_prefix( $status ) {
		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	private static function order_item( WC_Order $order, $full = false ) {
		$created = $order->get_date_created();
		$item    = array(
			'id'             => $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'currency'       => $order->get_currency(),
			'total'          => $order->get_total(),
			'date_created'   => $created ? $created->date( 'c' ) : null,
			'customer_id'    => $order->get_customer_id(),
			'billing_name'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'billing_email'  => $order->get_billing_email(),
			'payment_method' => $order->get_payment_method_title(),
			'item_count'     => $order->get_item_count(),
		);

		if ( $full ) {
			$lines = array();
			foreach ( $order->get_items() as $line ) {
				$product = is_callable( array( $line, 'get_product' ) ) ? $line->get_product() : null;
				$lines[] = array(
					'name'         => $line->get_name(),
					'product_id'   => is_callable( array( $line, 'get_product_id' ) ) ? $line->get_product_id() : null,
					'variation_id' => is_callable( array( $line, 'get_variation_id' ) ) ? $line->get_variation_id() : null,
					'sku'          => $product ? $product->get_sku() : null,
					'quantity'     => $line->get_quantity(),
					'subtotal'     => $line->get_subtotal(),
					'total'        => $line->get_total(),
				);
			}

			$notes = array();
			foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
				$notes[] = array(
					'content'       => $note->content,
					'date'          => $note->date_created ? $note->date_created->date( 'c' ) : null,
					'customer_note' => (bool) $note->customer_note,
					'added_by'      => $note->added_by,
				);
			}

			$item['items']          = $lines;
			$item['subtotal']       = $order->get_subtotal();
			$item['shipping_total'] = $order->get_shipping_total();
			$item['discount_total'] = $order->get_discount_total();
			$item['total_tax']      = $order->get_total_tax();
			$item['billing']        = $order->get_address( 'billing' );
			$item['shipping']       = $order->get_address( 'shipping' );
			$item['customer_note']  = $order->get_customer_note();
			$item['notes']          = $notes;
			$item['edit_link']      = $order->get_edit_order_url();
		}

		return $item;
	}
}
