<?php
/**
 * Update deposit price using cron.
 *
 * @package WordPress.
 */

/**
 * Register a custom menu page.
 */
function ts_register_deposit_update_menu() {
	add_submenu_page(
		'ts-price-update',
		'Admin deposit',
		'Admin deposit',
		'manage_options',
		'ts-deposit-price-update',
		'ts_admin_deposit_price_update',
	);
}
add_action( 'admin_menu', 'ts_register_deposit_update_menu' );

/**
 * Manufacturer pricr update page content.
 */
function ts_admin_deposit_price_update() {
	?>
	<div class="admin-deposit-update-wrap price-deposit-update-wrap">
		<div class="title"><?php esc_html_e( 'Deposit update', 'trailer-showroom' ); ?></div>
		<div class="message-info"></div>
		<form class="admin-deposit-update-form" id="admin-deposit-update-form" name="admin-deposit-update" method="post">
			<?php

			$manufacturer_args  = array(
				'role'    => 'manufacturer',
				'orderby' => 'user_nicename',
				'order'   => 'ASC',
			);
			$manufacturer_lists = get_users( $manufacturer_args );
			if ( ! empty( $manufacturer_lists ) ) {
				?>
				<div class="admin-list-group">
					<div class="list-items">
						<div class="manufacturer-lists">
							<label>
								<?php echo esc_html( __( 'Manufacturer List', 'trailer-showroom' ) ); ?>
							</label>
							<select class="wpadmin-custom-input manufacturers required" id="manufacturers" name="manufacturers">
								<option value=""><?php echo esc_html( __( 'Select Manufacturer', 'trailer-showroom' ) ); ?></option>
								<option value="all"><?php echo esc_html( __( 'All', 'trailer-showroom' ) ); ?></option>
								<?php
								foreach ( $manufacturer_lists as $manufacturer ) {
									if ( 0 < count_user_posts( $manufacturer->ID, 'product' ) ) {
										?>
										<option value="<?php echo esc_attr( esc_html( $manufacturer->ID ) ); ?>">
											<?php echo esc_html( $manufacturer->display_name ); ?>
										</option>
										<?php
									}
								}
								?>
							</select>
						</div>
					</div>
					<div class="list-items">
						<div class="product-type-section">
							<label>
								<?php echo esc_html( __( 'Product Type', 'trailer-showroom' ) ); ?>
							</label>
							<select class="wpadmin-custom-input required product-type" id="product-type" name="product-type">
								<option value=""><?php echo esc_html( __( 'Select product type', 'trailer-showroom' ) ); ?></option>
								<option value="main_products"><?php echo esc_html( __( 'Main Products', 'trailer-showroom' ) ); ?></option>
								<option value="option_products"><?php echo esc_html( __( 'Option Products', 'trailer-showroom' ) ); ?></option>
							</select>
						</div>
					</div>
					<div class="list-items">
						<div class="price-update-type">
							<label>
								<?php echo esc_html( __( 'Price Type', 'trailer-showroom' ) ); ?>
							</label>
							<select class="wpadmin-custom-input required price-type" id="price-type" name="price-type">
								<option value=""><?php echo esc_html( __( 'Select price type', 'trailer-showroom' ) ); ?></option>
								<option value="fixed"><?php echo esc_html( __( 'Fixed', 'trailer-showroom' ) ); ?></option>
								<option value="percentage"><?php echo esc_html( __( 'Percentage', 'trailer-showroom' ) ); ?></option>
							</select>
						</div>
					</div>
					<div class="list-items">
						<div class="price-action-type">
							<label>
								<?php echo esc_html( __( 'Action', 'trailer-showroom' ) ); ?>
							</label>
							<select class="wpadmin-custom-input required price-action-type" id="price-action-type" name="price-action-type">
								<option value=""><?php echo esc_html( __( 'Select price action type', 'trailer-showroom' ) ); ?></option>
								<option value="increase"><?php echo esc_html( __( 'Increase', 'trailer-showroom' ) ); ?></option>
								<option value="decrease"><?php echo esc_html( __( 'Decrease', 'trailer-showroom' ) ); ?></option>
							</select>
						</div>
					</div>
					<div class="list-items">
						<div class="price-amount">
							<label>
								<?php echo esc_html( __( 'Price', 'trailer-showroom' ) ); ?>
								<span class="required in-percentage"></span>
							</label>
							<input type="number" name="price-amount" class="wpadmin-custom-input required price-amount" id="price-amount" min="1" value="1">
						</div>
					</div>
					<div class="list-items">
						<input type="submit" class="wpadmin-submit button button-primary submit-price-update-form" name="submit-deposit-update-form">
					</div>
					
				</div>
				<?php
			}
			?>
		</form>
	</div>
	<?php
}


/**
 * Admin Deposit update action.
 */
function ts_admin_deposit_price_update_ajax() {
	check_ajax_referer( 'ts-general-nonce', 'ts-deposit-nonce' );
	$msg               = array();
	$manufacturers     = ! empty( $_POST['manufacturers'] ) ? sanitize_text_field( wp_unslash( $_POST['manufacturers'] ) ) : '';
	$product_type      = ! empty( $_POST['product-type'] ) ? sanitize_text_field( wp_unslash( $_POST['product-type'] ) ) : '';
	$price_type        = ! empty( $_POST['price-type'] ) ? sanitize_text_field( wp_unslash( $_POST['price-type'] ) ) : '';
	$price_amount      = ! empty( $_POST['price-amount'] ) ? floatval( $_POST['price-amount'] ) : '';
	$price_action_type = ! empty( $_POST['price-action-type'] ) ? sanitize_text_field( wp_unslash( $_POST['price-action-type'] ) ) : 'increase';
	if ( ! empty( $manufacturers ) && ! empty( $product_type ) && ! empty( $price_type ) && ! empty( $price_amount ) ) {
		if ( 'all' === $manufacturers ) {
			$manufacturer_products = ts_get_manufacturer_products( $product_type );
		} else {
			$manufacturer_products = ts_get_manufacturer_products( $product_type, $manufacturers );
		}
		if ( ! empty( $manufacturer_products ) ) {
			$product_group_array = ts_array_divide( $manufacturer_products, 2 );
			if ( ! empty( $product_group_array ) ) {
				$cron_job_action_data = get_option( 'ts_deposit_price_update_cron' ) ? get_option( 'ts_deposit_price_update_cron' ) : array();
				$i                    = 5;
				foreach ( $product_group_array  as $key => $manufacturer_product ) {
					$action_name                          = 'ts_' . $key . '_deposit_price_update_' . strtotime( "+$i seconds" );
					$cron_job_action_data[ $action_name ] = array(
						'action'      => $action_name,
						'time'        => strtotime( "+$i seconds" ),
						'action_args' => array(
							'products' => $manufacturer_product,
							'args'     => $_POST,
						),
					);
					$i                                   += 10;
				}
				update_option( 'ts_deposit_price_update_cron', $cron_job_action_data );
				$msg = array(
					'result'  => 1,
					'message' => __( 'The product deposit prices have been updated for the selected manufacturer. You will get an email notification once it is reflected.', 'woocommerce' ),
				);
			} else {
				$msg = array(
					'result'  => 0,
					'message' => __( 'Manufacturer Products not found.', 'woocommerce' ),
				);
			}
		} else {
			$msg = array(
				'result'  => 0,
				'message' => __( 'Manufacturer Products not found.', 'woocommerce' ),
			);
		}
	} else {
		$msg = array(
			'result'  => 0,
			'message' => __( 'Something went wrong kindly contact site admin.', 'woocommerce' ),
		);
	}
	wp_send_json( $msg );
	wp_die();
}
add_action( 'wp_ajax_ts_admin_deposit_price_update_ajax', 'ts_admin_deposit_price_update_ajax' );
add_action( 'wp_ajax_nopriv_ts_admin_deposit_price_update_ajax', 'ts_admin_deposit_price_update_ajax' );


/**
 * Get products by manufacturer.
 *
 * @param string $product_type string.
 * @param mixed  $manufacturer int.
 * @param string $product_taxonomy string.
 */
function ts_get_manufacturer_products( $product_type = 'main_products', $manufacturer = '', $product_taxonomy = '' ) {
	$product_ids       = array();
	$manufacturer_args = array(
		'post_type' => 'product',
	);
	if ( ! empty( $manufacturer ) ) {
		$manufacturer_args['author'] = $manufacturer;
	}
	$is_option_product = false;
	if ( 'option_products' === $product_type ) {
		$is_option_product = true;
	}
	if ( $is_option_product ) {
		$product_taxonomy = '';
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$manufacturer_args['tax_query'] = array(
			'relation' => 'OR',
			array(
				'taxonomy' => 'additional_equipment',
				'operator' => 'EXISTS',
			),
			array(
				'taxonomy' => 'master_equipment',
				'operator' => 'EXISTS',
			),
		);
	} else {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$manufacturer_args['tax_query'] = array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'additional_equipment',
				'operator' => 'NOT EXISTS',
			),
			array(
				'taxonomy' => 'master_equipment',
				'operator' => 'NOT EXISTS',
			),
		);
	}
	if ( ! empty( $product_taxonomy ) ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$manufacturer_args['tax_query'] = array(
			array(
				'taxonomy' => $product_taxonomy,
				'operator' => 'EXISTS',
			),
		);
	}
	$manufacturer_products = new WP_Query( $manufacturer_args );
	if ( $manufacturer_products->have_posts() ) {
		while ( $manufacturer_products->have_posts() ) :
			$manufacturer_products->the_post();
			$product_ids[] = get_the_ID();
		endwhile;
		wp_reset_postdata();
	}
	return $product_ids;
}

/**
 * Group number of products array.
 *
 * @param array $product_array array.
 * @param int   $col_count int.
 */
function ts_array_divide( $product_array, $col_count = 100 ) {
	$result = array();
	if ( ! empty( $product_array ) && is_array( $product_array ) ) {
		$row_count = ceil( count( $product_array ) / $col_count );
		$pointer   = 0;
		for ( $row = 0; $row < $row_count; $row++ ) {
			for ( $col = 0; $col < $col_count; ++$col ) {
				if ( isset( $product_array[ $pointer ] ) ) {
					$result[ $row ][ $col ] = $product_array[ $pointer ];
					++$pointer;
				}
			}
		}
	}
	return $result;
}
/**
 * Ser custom cron schedule.
 *
 * @param array $schedules array.
 */
function ts_custom_cron_schedule( $schedules ) {
	$schedules['per_minute'] = array(
		'interval' => 60,
		'display'  => __( 'Every Minute' ),
	);
	return $schedules;
}
// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
add_filter( 'cron_schedules', 'ts_custom_cron_schedule' );

/**
 * Updated price by manufaturer calculation.
 *
 * @param mixed  $product_old_price mixed.
 * @param mixed  $new_price mixed.
 * @param string $price_type string.
 * @param string $action string.
 */
function ts_manufacturer_update_price_amount( $product_old_price, $new_price, $price_type = 'fixed', $action = 'increase' ) {
	$updated_price = 0;
	if ( empty( $product_old_price ) && empty( $new_price ) ) {
		return $updated_price;
	}
	if ( 'percentage' === $price_type ) {
		$percentage_amount = floatval( $product_old_price ) * floatval( $new_price ) / 100;
		if ( 'increase' === $action ) {
			$updated_price = floatval( $product_old_price ) + floatval( $percentage_amount );
		} elseif ( 'decrease' === $action ) {
			$updated_price = floatval( $product_old_price ) - floatval( $percentage_amount );
		} else {
			$updated_price = floatval( $product_old_price );
		}
	} elseif ( 'fixed' === $price_type ) {
		if ( 'increase' === $action ) {
			$updated_price = floatval( $product_old_price ) + floatval( $new_price );
		} elseif ( 'decrease' === $action ) {
			$updated_price = floatval( $product_old_price ) - floatval( $new_price );
		} else {
			$updated_price = floatval( $product_old_price );
		}
	} else {
		$updated_price = floatval( $product_old_price );
	}
	return ceil( $updated_price );
}


/**
 * Update Admin Deposit.
 *
 * @param int    $product_id int.
 * @param string $price_type string.
 * @param mixed  $new_price  mixed.
 * @param string $price_action_type string.
 */
function ts_update_product_deposit_price_admin_side( $product_id, $price_type, $new_price, $price_action_type ) {
	$is_price_updated = false;
	if ( empty( $product_id ) ) {
		return $is_price_updated;
	}
	$product = wc_get_product( $product_id );

	if ( 'variable' !== $product->get_type() && 'simple' !== $product->get_type() ) {
		return $is_price_updated;
	}
	if ( 'variable' === $product->get_type() ) {
		$variation_args        = array(
			'fields' => 'ids',
		);
		$product_variation_ids = $product->get_children( $variation_args );
		if ( ! empty( $product_variation_ids ) ) {
			$is_variation_price_updated = array();
			foreach ( $product_variation_ids as $key => $product_variation_id ) {
				$old_price = ts_get_deposit_amount( $product_variation_id );
				if ( ! empty( $old_price ) ) {
					$updated_price = ts_manufacturer_update_price_amount( $old_price, $new_price, $price_type, $price_action_type );
				} else {
					$old_price     = ts_get_deposit_amount( $product_id );
					$updated_price = ts_manufacturer_update_price_amount( $old_price, $new_price, $price_type, $price_action_type );
				}
				if ( update_post_meta( $product_variation_id, '_wc_deposit_amount', $updated_price ) ) {
					$is_variation_price_updated[] = 'true';
				} else {
					$is_variation_price_updated[] = 'false';
				}
			}
			if ( ! in_array( 'false', $is_variation_price_updated, true ) ) {
				$is_price_updated = true;
			}
		}
	} else {
		$old_price     = ts_get_deposit_amount( $product_id );
		$updated_price = ts_manufacturer_update_price_amount( $old_price, $new_price, $price_type, $price_action_type );
		if ( update_post_meta( $product_id, '_wc_deposit_amount', $updated_price ) ) {
			$is_price_updated = true;
		}
	}
	return $is_price_updated;
}

/**
 * Admin Deposit update function.
 *
 * @param array $manufacturer_products array of manufacturer products.
 * @param array $manufacturer_product_args array of other price update details.
 */
function ts_admin_deposit_price_update_callback( $manufacturer_products, $manufacturer_product_args ) {
	$is_price_updated  = false;
	$chk_price_update  = array();
	$manufacturers     = ! empty( $manufacturer_product_args['manufacturers'] ) ? (int) $manufacturer_product_args['manufacturers'] : '';
	$product_type      = ! empty( $manufacturer_product_args['product-type'] ) ? sanitize_text_field( wp_unslash( $manufacturer_product_args['product-type'] ) ) : '';
	$price_type        = ! empty( $manufacturer_product_args['price-type'] ) ? sanitize_text_field( wp_unslash( $manufacturer_product_args['price-type'] ) ) : '';
	$price_amount      = ! empty( $manufacturer_product_args['price-amount'] ) ? floatval( $manufacturer_product_args['price-amount'] ) : '';
	$price_action_type = ! empty( $manufacturer_product_args['price-action-type'] ) ? sanitize_text_field( wp_unslash( $manufacturer_product_args['price-action-type'] ) ) : 'increase';
	foreach ( $manufacturer_products  as $key => $manufacturer_product ) {
		$update_product_price = ts_update_product_deposit_price_admin_side( $manufacturer_product, $price_type, $price_amount, $price_action_type );
		if ( $update_product_price ) {
			$price_update_timeline = get_post_meta( $manufacturer_product, 'admin_deposit_price_update_timeline', true );
			$price_timeline        = ! empty( $price_update_timeline ) ? $price_update_timeline : array();
			if ( ! empty( $price_timeline ) ) {
				if ( ! in_array( date_i18n( 'Y-m-d' ), $price_timeline, true ) ) {
					$price_timeline[] = date_i18n( 'Y-m-d' );
					update_post_meta( $manufacturer_product, 'admin_deposit_price_update_timeline', $price_timeline );
				}
			} else {
				add_post_meta( $manufacturer_product, 'admin_deposit_price_update_timeline', array( date_i18n( 'Y-m-d' ) ) );
			}
			$chk_price_update[] = 'true';
		} else {
			$price_update_fail_timeline = get_post_meta( $manufacturer_product, 'admin_deposit_price_update_timeline', true );
			$price_fail_timeline        = ! empty( $price_update_fail_timeline ) ? $price_update_fail_timeline : array();
			if ( ! empty( $price_timeline ) ) {
				if ( ! in_array( date_i18n( 'Y-m-d' ), $price_fail_timeline, true ) ) {
					$price_fail_timeline[] = array(
						'date-time' => date_i18n( 'Y-m-d H:i:s' ),
						'products'  => $manufacturer_product,
					);
					update_post_meta( $manufacturer_product, 'admin_deposit_price_fail_update_timeline', $price_fail_timeline );
				}
			} else {
				$price_fail_timeline = array(
					'date-time' => date_i18n( 'Y-m-d H:i:s' ),
					'products'  => $manufacturer_product,
				);
				add_post_meta( $manufacturer_product, 'admin_deposit_price_fail_update_timeline', array( date_i18n( 'Y-m-d' ) ) );
			}
			$chk_price_update[] = 'false';
		}
	}
	if ( ! in_array( 'false', $chk_price_update, true ) ) {
		$is_price_updated = true;
	}
	return $is_price_updated;
}

/**
 * Set price update cron.
 */
function ts_set_deposit_update_cron() {
	$deposit_price_cron_data = get_option( 'ts_deposit_price_update_cron' );
	if ( ! empty( $deposit_price_cron_data ) ) {
		foreach ( $deposit_price_cron_data as $key => $cron_data ) {
			$action_name = $cron_data['action'];
			$action_args = $cron_data['action_args'];
			if ( ! empty( $action_args ) ) {
				$manufacturer_products     = $action_args['products'];
				$manufacturer_product_args = $action_args['args'];
				$args                      = array( $manufacturer_products, $manufacturer_product_args );
				if ( ! wp_next_scheduled( $action_name, $args ) ) {
					wp_schedule_event( strtotime( 'now' ), 'per_minute', $action_name, $args );
				}
				add_action(
					$action_name,
					function ( $manufacturer_products, $manufacturer_product_args ) {
						if ( empty( $manufacturer_products ) ) {
							return;
						}
						$product_price_update = ts_admin_deposit_price_update_callback( $manufacturer_products, $manufacturer_product_args );
						$mailer               = WC()->mailer();
						if ( $product_price_update ) {
							$product_type       = 'main_products' === $manufacturer_product_args['product-type'] ? 'Main Products' : 'Option Products';
							$product_price_type = 'fixed' === $manufacturer_product_args['price-type'] ? sanitize_text_field( get_woocommerce_currency_symbol() . '' . $manufacturer_product_args['price-amount'] ) : $manufacturer_product_args['price-amount'] . '%';
							$message_content    = '<p>Thanks for updating the deposit price on the trailer showroom. The ' . $product_type . ' deposit prices ' . $manufacturer_product_args['price-action-type'] . ' ' . $product_price_type . ' have been updated successfully. A customer can see the updated price on the website.</p>';
							$message_content   .= '<p>The deposit prices update have be revised for the manufacturer\'s products.</p>';
							$message_content   .= '<p>Thank you</p>';
							$message            = $mailer->wrap_message( 'Deposit Update', $message_content );
							$mailer->send( 'hardik@krishaweb.com', __( 'Deposit Update', 'trailer-showroom' ), $message );
						} else {
							$product_type     = 'main_products' === $manufacturer_product_args['product-type'] ? 'Main Products' : 'Option Products';
							$message_content  = '<p>Thanks for updating the price on the trailer showroom. The ' . $product_type . ' prices have not been updated successfully. Please try again after sometime.</p>';
							$message_content .= '<p>Thank you</p>';
							$message          = $mailer->wrap_message( 'Deposit Update fail', $message_content );
							$mailer->send( 'hardik@krishaweb.com', __( 'Deposit Update fail', 'trailer-showroom' ), $message );
						}
					},
					10,
					2
				);
				if ( strtotime( 'now' ) > $cron_data['time'] ) {
					wp_clear_scheduled_hook( $action_name, $args );
					unset( $deposit_price_cron_data[ $action_name ] );
				}
			}
		}
	}
	if ( empty( $deposit_price_cron_data ) ) {
		delete_option( 'ts_deposit_price_update_cron' );
	}
}
ts_set_deposit_update_cron();
