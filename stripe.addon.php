<?php
/*
Plugin Name: Music Store - Stripe Add On
Plugin URI: https://musicstore.dwbooster.com/add-ons/stripe
Version: 1.2.3
Author: CodePeople
Author URI: https://musicstore.dwbooster.com
Description: Integrates the Stripe payment gateway with the Music Store.
Text Domain: music-store-stripe-addon
*/

/*
Documentation: https://stripe.com/docs/quickstart
*/
if ( ! class_exists( 'MUSIC_STORE_STRIPE_ADDON' ) ) {
	class MUSIC_STORE_STRIPE_ADDON {

		// Properties
		private $default_settings;
		private $current_settings;
		private $licenses;

		public function __construct() {
			 $this->licenses        = array();
			$this->default_settings = array(
				'enabled'         => 0,
				'integrationtype' => '',
				'label'           => 'Pay with credit card',
				'mode'            => 'live',
				'publishable_key' => '',
				'secret_key'      => '',
				'language'        => '',
				'billing_address' => 0,
				'subtitle'        => 'Music Store',
				'logo'            => '',
			);
			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		} // End __construct

		/**************************** PRIVATE METHODS ***************************/

		private function _settings( $reload = false ) {
			if ( empty( $this->current_settings ) || $reload ) {
				$this->current_settings = get_option( 'ms_stripe_settings', $this->default_settings );
			}

			return $this->current_settings;
		} // End _settings

		private function _fix_price( $v, $c ) {
			 $c = strtoupper( $c );

			if ( in_array( $c, array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ) ) ) {
				return ceil( $v );
			}

			if ( 'UGX' == $c ) {
				$v = ceil( $v );
			}

			return round( $v * 100 );
		} // End _fix_price

		private function _check_plugin_requirements() {
			 return true;
		} // End _check_plugin_requirements

		private function _set_license( $license_type ) {
			if ( function_exists( 'music_store_set_license' ) ) {
				music_store_set_license( $license_type, $this->licenses );
			}
		} // End _set_license

		private function _sanitize() {
			array_walk_recursive( $_POST, function( &$item, $index ){ $item = sanitize_text_field( wp_unslash( $item ) ); } );
		} // End _sanitize

		/**************************** PUBLIC METHODS ****************************/

		public function plugins_loaded() {
			if ( $this->_check_plugin_requirements() ) {
				// I18n
				add_action( 'after_setup_theme', function() {
					load_plugin_textdomain( 'music-store-stripe-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
				});
				add_action( 'admin_init', array( $this, 'admin_init' ) );

				if ( ! is_admin() ) {
					add_action( 'musicstore_calling_payment_gateway', array( $this, 'payment_gateway_integration' ), 10, 2 );
					add_action( 'musicstore_checking_payment', array( $this, 'check_payment' ), 1 );
					add_filter( 'musicstore_payment_gateway_enabled', array( $this, 'is_enabled' ), 10 );
					add_filter( 'musicstore_payment_gateway_list', array( $this, 'populate_list' ), 10 );
					add_action( 'wp_footer', array( $this, 'stripe_js_code' ), 10 );
				}
			}
		} // End plugins_loaded

		public function is_enabled( $enabled ) {
			$settings = $this->_settings();
			return $settings['enabled'] || $enabled;
		} // End is_enabled

		public function populate_list( $payment_gateways ) {
			if ( $this->is_enabled( false ) ) {
				$settings                   = $this->_settings();
				$label                      = ( ! empty( $settings['label'] ) ) ? __( $settings['label'], 'music-store-stripe-addon' ) : $this->default_settings['label'];
				$payment_gateways['stripe'] = $label;
			}
			return $payment_gateways;
		} // End populate_list

		public function stripe_js_code() {
			global $music_store_settings;

			if ( $this->is_enabled( false ) ) {
				$settings = $this->_settings();
				if (
					! empty( $settings['publishable_key'] ) &&
					! empty( $settings['secret_key'] ) &&
					(
						empty( $settings['integrationtype'] ) ||
						'sca' != $settings['integrationtype']
					)
				) {
					?>
					<script src="https://checkout.stripe.com/checkout.js"></script>
					<script>
						var ms_form,
							ms_stripe_handle = StripeCheckout.configure({
							key: '<?php print esc_js( $settings['publishable_key'] ); ?>',
							image: '<?php if ( ! empty( $settings['logo'] ) ) {
								print str_replace( '&amp;', '&', esc_js( $settings['logo'] ) );} ?>',
							locale: '<?php if ( isset( $settings['language'] ) ) {
								print esc_js( $settings['language'] );} ?>',
							token: function(token, args){
								if('undefined' != typeof ms_form)
								{
									ms_form.append('<input type="hidden" name="ms_stripe_token" value="'+token.id+'">');
									ms_form.append('<input type="hidden" name="ms_stripe_email" value="'+token.email+'">');
									ms_form.submit();
								}
							}
						});
						jQuery(function(){
							var $ = jQuery,
								ms_buy_now_stripe = ('ms_buy_now' in window) ? ms_buy_now : function(){ return true; };
							window['ms_buy_now'] = function(e, p){
								var o = $(e),
									f = o.closest('form'),
									l = f.find('select');
								ms_form = f;
								if(f.find('[name="ms_stripe_token"]').length) return false;
								if(!l.length || l.val() == 'stripe')
								{
									if(ms_buy_now_stripe(e, p))
									{
										$.ajax({
											dataType: 'json',
											method: 'POST',
											url: f.attr('action'),
											data: f.serialize(),
											headers: {'X-Alt-Referer': document.referrer},
											success: function(data)
											{
												if(
													typeof data == 'object' &&
													'name' in data &&
													'amount' in data
												)
												{
													ms_stripe_handle.open(
														{
															name: data['name'],
															description: '<?php if ( ! empty( $settings['subtitle'] ) ) {
																print esc_js( $settings['subtitle'] );} ?>',
															zipCode: true,
															amount: parseFloat(data['amount']),
															image: '<?php if ( ! empty( $settings['logo'] ) ) {
																print str_replace( '&amp;', '&', esc_js( $settings['logo'] ) );} ?>',
															currency: '<?php echo esc_js( strtolower( ( ! empty( $music_store_settings['ms_paypal_currency'] ) ) ? $music_store_settings['ms_paypal_currency'] : 'usd' ) ); ?>',
															<?php
															if ( ! empty( $settings['billing_address'] ) ) {
																print 'billingAddress:true';
															}
															?>
														}
													);
												}
											}
										});
									}
									return false;
								}
								else
								{
								   return ms_buy_now_stripe(e, p);
								}
							};
							jQuery(document).on('click', '.ms-purchase-button', function(){return ms_buy_now(this);});
						});
					</script>
					<?php
				}
			}
		} // end stripe_js_code

		public function payment_gateway_integration( $amount, $purchase_settings ) {
			global $music_store_settings;

			if (
				! $this->is_enabled( false ) ||
				! isset( $_REQUEST['ms_payment_gateway'] ) ||
				'stripe' != $_REQUEST['ms_payment_gateway']
			) {
				return;
			}

			if ( $amount > 0 ) {
				$currency = strtolower( ( ! empty( $music_store_settings['ms_paypal_currency'] ) ) ? $music_store_settings['ms_paypal_currency'] : 'usd' );

				$item_name = ( ! empty( $purchase_settings['item_name'] ) ) ? $purchase_settings['item_name'] : '';
				$item_name = html_entity_decode( $item_name, ENT_COMPAT, 'UTF-8' );
				$amount    = $this->_fix_price( $amount, $currency );

				$settings  = $this->_settings();
				$baseurl   = $purchase_settings['baseurl'];
				$returnurl = $purchase_settings['returnurl'];
				$cancelurl = $purchase_settings['cancelurl'];

				if ( ! empty( $settings['integrationtype'] ) && 'sca' == $settings['integrationtype'] ) {

					$products_id = '';
					if ( ! empty( $purchase_settings['products'] ) ) {
						foreach ( $purchase_settings['products'] as $product ) {
							$products_id .= ( isset( $product->product_id ) ) ? $product->product_id : $product->ID;
						}
					}
					$purchase_id = ( ! empty( $purchase_settings['id'] ) ) ? $purchase_settings['id'] : '';
					$coupon_code = ( ! empty( $purchase_settings['coupon'] ) ) ? '|ms_coupon_code=' . $purchase_settings['coupon'] : '';
					$buyer       = ( ( $user_id = get_current_user_id() ) != 0 ) ? '|buyer_id=' . $user_id : '';

					if ( ! class_exists( '\Stripe\Stripe' ) ) {
						require_once dirname( __FILE__ ) . '/stripe-php.addon/init.php';
					}

					\Stripe\Stripe::setApiKey( $settings['secret_key'] );

					$session = \Stripe\Checkout\Session::create(
						array(
//							'payment_method_types' => array( 'card' ),
							'line_items'           => array(
								array(
//									'name'     => $item_name,
//									'amount'   => $amount,
//									'currency' => $currency,
									'quantity' => 1,
									'price_data' => array(
										'currency' => $currency,
										'unit_amount' => $amount,
										'product_data' => array(
											'name' => $item_name,
											'description' => $item_name,
										),
									),
								),
							),
							'mode' 				   => 'payment',
							'success_url'          => apply_filters( 'musicstore_notify_url', $baseurl . '|pid=' . $products_id . '|purchase_id=' . $purchase_id . '|rtn_act=purchased_product_music_store' . $coupon_code . $buyer . '&ms_stripe_ipncheck={CHECKOUT_SESSION_ID}' ),
							'cancel_url'           => $cancelurl,
						)
					);
					?>
<html><head><title>Redirecting to Stripe Checkout</title><body>
<script src="https://js.stripe.com/v3"></script>
<script>
var stripe = Stripe('<?php print esc_js( $settings['publishable_key'] ); ?>');
stripe.redirectToCheckout({
sessionId: '<?php echo esc_js( $session->id ); ?>'
}).then(function (result) {
	alert(result.error.message);
});
</script>
</body>
</html>
					<?php
				} else {
					// Exists the token so complete the payment
					if ( isset( $_REQUEST['ms_stripe_token'] ) ) {
						if ( ! class_exists( '\Stripe\Stripe' ) ) {
							require_once dirname( __FILE__ ) . '/stripe-php.addon/init.php';
						}
						\Stripe\Stripe::setApiKey( $settings['secret_key'] );

						// Get the credit card details submitted by the form
						$token = sanitize_text_field( wp_unslash( $_REQUEST['ms_stripe_token'] ) );
						$email = isset( $_REQUEST['ms_stripe_email'] ) ? sanitize_email( wp_unslash( $_REQUEST['ms_stripe_email'] ) ) : '';

						try {
							$customer = \Stripe\Customer::create(
								array(
									'source' => $token,
									'email'  => $email,
								)
							);

							$charge = \Stripe\Charge::create(
								array(
									'customer'    => $customer->id,
									'amount'      => $amount, // amount in cents, again
									'currency'    => $currency,
									'description' => $item_name,
								)
							);
							if ( $charge->paid ) {
								// Registering the purchase, generating the notification emails,
								// and redirecting the user to the download page
								$this->reporting_payment( $charge, $customer->email, $purchase_settings );
							} elseif ( ! empty( $charge->failure_code ) ) {
								print esc_html( $charge->failure_message ) . esc_html__( 'Please', 'music-store-stripe-addon' ) . ' <a href="javascript:window.history.back();">' . esc_html__( 'go back and try again', 'music-store-stripe-addon' ) . '</a>.';
							}
						} catch ( Stripe_CardError $e ) {
							// The card has been declined
							print esc_html__( 'Transaction failed. The card has been declined. Please', 'music-store-stripe-addon' ) . ' <a href="javascript:window.history.back();">' . esc_html__( 'go back and try again', 'music-store-stripe-addon' ) . '</a>';
						}
					} else {
						$output = array(
							'amount'    => $amount,
							'baseurl'   => $baseurl,
							'returnurl' => $returnurl,
							'name'      => $item_name,
						);

						// If there is not the token, returns a json with teh amount information
						if ( ! headers_sent() ) {
							header( 'Content-Type: application/json' );
						}
						print json_encode( $output );
					}
				}
				exit;
			}
		} // End calling_gateway

		public function check_payment() {
			global $music_store_settings;
			if ( ! $this->is_enabled( false ) || empty( $_REQUEST['ms_stripe_ipncheck'] ) ) {
				return;
			}

			$settings = $this->_settings();
			if ( ! class_exists( '\Stripe\Stripe' ) ) {
				require_once dirname( __FILE__ ) . '/stripe-php.addon/init.php';
			}

			\Stripe\Stripe::setApiKey( $settings['secret_key'] );

			$session = \Stripe\Checkout\Session::retrieve( sanitize_text_field( wp_unslash( $_REQUEST['ms_stripe_ipncheck'] ) ) );
			$pintent = \Stripe\PaymentIntent::retrieve( $session->payment_intent );

			if ( 'succeeded' == $pintent->status && ! empty( $_GET['ms-action'] ) ) {
				$charge              = \Stripe\Charge::retrieve( $pintent->latest_charge );
				$charge->description = $session->display_items[0]->custom->name;
				$_parameters         = explode( '|', sanitize_text_field( wp_unslash( $_GET['ms-action'] ) ) );
				foreach ( $_parameters as $_parameter ) {
					$_parameter_parts = explode( '=', $_parameter );
					if ( count( $_parameter_parts ) == 2 ) {
						$ipn_parameters[ $_parameter_parts[0] ] = sanitize_text_field( $_parameter_parts[1] );
					}
				}

				$purchase_id = isset( $ipn_parameters['purchase_id'] ) ? $ipn_parameters['purchase_id'] : 0;
				$product_id  = isset( $ipn_parameters['pid'] ) ? $ipn_parameters['pid'] : 0;
				// If the buyer_id is empty the result would be 0
				$GLOBALS['buyer_id'] = @intval( $ipn_parameters['buyer_id'] );
				$returnurl           = $GLOBALS['music_store']->_ms_create_pages( 'ms-download-page', 'Download Page' );
				$returnurl          .= ( ( strpos( $returnurl, '?' ) === false ) ? '?' : '&' ) . 'ms-action=download';

				$products = array();
				if ( ! empty( $music_store_settings['ms_paypal_shopping_cart'] ) ) {
					if ( ! empty( $purchase_id ) ) {
						$products = music_store_getProducts( $purchase_id );
					}
				} elseif ( ! empty( $product_id ) ) {
					$_post = get_post( $product_id );
					if ( is_null( $_post ) ) {
						exit;
					}

					switch ( $_post->post_type ) {
						case 'ms_song':
							$products[] = new MSSong( $_post->ID );
							break;
						case 'ms_collection':
							$products[] = new MSCollection( $_post->ID );
							break;
						default:
							exit;
						break;
					}
				}

				$purchase_settings = array(
					'id'        => $purchase_id,
					'products'  => $products,
					'coupon'    => ! empty( $ipn_parameters['coupon'] ) ? $ipn_parameters['coupon'] : '',
					'returnurl' => $returnurl,
				);

				$this->reporting_payment( $charge, $charge->billing_details->email, $purchase_settings );
			} else {
				echo 'Error: Purchase cannot be verified. Please contact the seller.';
				exit;
			}
			exit;
		} // End check_payment

		public function reporting_payment( $charge, $email, $purchase_settings ) {
			global $music_store_settings, $wpdb;
			try {
				$purchase_id      = $purchase_settings['id'];
				$transact_id      = $charge->id;
				$item_name        = $charge->description;
				$payment_amount   = $charge->amount;
				$payment_currency = strtoupper( $charge->currency );
				// $billing 				= $charge->billing_details;
				// $payer_email 			= (!empty($billing['email'])) ? $billing['email'] : $billing['name'];
				$payer_email          = $email;
				$payment_gateway_data = json_encode( $charge );

				$percent       = 0;
				$discount_note = '';
				$base_price    = 0;

				// Products
				if ( ! empty( $purchase_settings['products'] ) ) {
					$products = $purchase_settings['products'];

					// Walking the products list to get the determine the price applied
					foreach ( $products as $i => $product ) {
						$processed_product             = new stdClass();
						$processed_product->product_id = ( isset( $product->product_id ) ) ? $product->product_id : $product->id;

						if ( isset( $product->price_type ) && 'exclusive' == $product->price_type && ! empty( $product->exclusive_price ) ) {
							$processed_product->exclusive_price_applied = true;
							$processed_product->final_price             = $product->exclusive_price;
							$this->_set_license( 'exclusive' );
						} else {
							if ( function_exists( 'music_store_getValidProductDiscount' ) ) {
									$discount = music_store_getValidProductDiscount( $product->product_id );
							}

							$processed_product->exclusive_price_applied = false;
							$processed_product->final_price             = ( ! empty( $discount ) ) ? $discount->discount : $product->price;
							$processed_product->discount_applied        = ( ! empty( $discount ) && ! empty( $discount->note ) ) ? ' - ' . $discount->note : '';
							$this->_set_license( 'regular' );
						}
						$products[ $i ] = $processed_product;
						$base_price    += $processed_product->final_price;
					}

					// Coupon
					if ( ( $coupon_code = ( ! empty( $purchase_settings['coupon'] ) ) ? $purchase_settings['coupon'] : '' ) != '' ) {
						$coupon = $wpdb->get_row(
							$wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . MSDB_COUPON . ' WHERE coupon=%s AND (onetime=0 OR times=0)', $coupon_code )
						);

						if ( ! empty( $coupon ) ) {
							$percent = $coupon->discount;
							$wpdb->query(
								$wpdb->prepare( 'UPDATE ' . $wpdb->prefix . MSDB_COUPON . ' SET times=times+1 WHERE coupon=%s', $coupon_code )
							);
							$discount_note = ' - Coupon code: ' . $coupon->coupon . ' discount: ' . $percent . '%';
						}
					}

					// Checks the store discount
					if ( function_exists( 'music_store_getValidStoreDiscount' ) ) {
						$store_discount = music_store_getValidStoreDiscount( $base_price );
						if ( $store_discount && $percent < $store_discount->discount ) {
							$percent       = $store_discount->discount;
							$discount_note = ' - ' . $store_discount->note;
							$coupon_code   = '';
						}
					}

					// Check if the charged amount is the correct one
					if ( $base_price * ( 100 - $percent ) <= $charge->amount ) {
						// Register the purchases
						foreach ( $products as $key => $product ) {
							if ( ! isset( $product->post_title ) ) {
								$product->post_title = '';
							}
							$products[ $key ] = $product;

							$note = strip_tags( ( ( ! empty( $product->discount_applied ) ) ? $product->discount_applied : '' ) . $discount_note );

							if (
								function_exists( 'music_store_register_purchase' ) &&
								music_store_register_purchase(
									$product->product_id,
									$purchase_id,
									( ! empty( $payer_email ) ) ? $payer_email : $transact_id,
									round( $product->final_price * ( 100 - $percent ) / 100, 2 ),
									$payment_gateway_data,
									$note
								)
							) {
								if ( $product->exclusive_price_applied ) {
									$wpdb->query(
										$wpdb->prepare( 'UPDATE ' . $wpdb->prefix . MSDB_POST_DATA . ' SET purchases=purchases+1, purchased_exclusively=1 WHERE id=%d', $product->product_id )
									);
									$wpdb->query(
										$wpdb->prepare( 'UPDATE ' . $wpdb->posts . " SET post_status='pexclusively' WHERE ID=%d", $product->product_id )
									);
								} else {
									$wpdb->query(
										$wpdb->prepare( 'UPDATE ' . $wpdb->prefix . MSDB_POST_DATA . ' SET purchases=purchases+1 WHERE id=%d', $product->product_id )
									);
								}
							}
						}
						if ( function_exists( 'music_store_removeCart' ) ) {
							music_store_removeCart( $purchase_id );
						}
						if ( ! empty( $payer_email ) && function_exists( 'music_store_send_emails' ) ) {
							music_store_send_emails(
								array(
									'item_name'   => $item_name,
									'currency'    => $payment_currency,
									'purchase_id' => $purchase_id,
									'amount'      => number_format( $payment_amount / 100, 2 ),
									'payer_email' => $payer_email,
								),
								$this->licenses
							);
						}

						$this->_sanitize();

						$_POST['ms_purchase_id']    = $purchase_id;
						$_POST['ms_payment_amount'] = number_format( $payment_amount / 100, 2 );

						do_action( 'musicstore_payment_received', $_POST, ( isset( $products ) ) ? $products : array() );
						print '<script>document.location.href="' . str_replace( '&amp;', '&', esc_js( $purchase_settings['returnurl'] ) ) . '&purchase_id=' . urlencode( $purchase_id ) . '";</script>';
					} else {
						esc_html_e( 'The amount charged was incorrect. Please contact us directly', 'music-store-stripe-addon' );
					}
				} else {
					esc_html_e( 'There are no products', 'music-store-stripe-addon' );
				}
			} catch ( Exception $err ) {
				esc_html_e( 'Has occurred an error', 'music-store-stripe-addon' );
				print esc_html( $err->getMessage() );
			}
			exit;
		} // End reporting_payment

		public function admin_init() {
			add_action( 'musicstore_settings_page', array( &$this, 'show_settings' ), 11 );
			add_action( 'musicstore_save_settings', array( &$this, 'save_settings' ), 11 );
		} // End admin_init

		public function show_settings() {
			$settings = $this->_settings( true );
			if ( empty( $settings['language'] ) ) {
				$settings['language'] = '';
			}
			?>
			<div id="metabox_basic_settings" class="postbox" >
				<h3 class='hndle' style="padding:5px;"><span>Stripe <? _e('Payment Gateway', 'music-store-stripe-addon')?></span></h3>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Stripe?', 'music-store-stripe-addon' ); ?></th>
							<td>
								<input type="checkbox" name="ms_stripe_enabled" <?php print ( ! empty( $settings['enabled'] ) ? 'checked' : '' ); ?> />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Integration Type', 'music-store-stripe-addon' ); ?></th>
							<td>
								<select name="ms_stripe_integrationtype">
									<option value="" <?php if ( empty( $settings['integrationtype'] ) || 'sca' != $settings['integrationtype'] ) {
										echo 'selected';} ?>>
										<?php esc_html_e( 'Classic - Valid for NON European Sellers (European Union Sellers)', 'music-store-stripe-addon' ); ?>
									</option>
									<option value="sca" <?php if ( ! empty( $settings['integrationtype'] ) && 'sca' == $settings['integrationtype'] ) {
										echo 'selected';} ?>>
										<?php esc_html_e( 'SCA Ready  - Valid for European Sellers (European Union Sellers)', 'music-store-stripe-addon' ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Label', 'music-store-stripe-addon' ); ?></th>
							<td><input type="text" name="ms_stripe_label" size="40" value="<?php print esc_attr( ! empty( $settings['label'] ) ? $settings['label'] : '' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Payment mode?', 'music-store-stripe-addon' ); ?></th>
							<td>
								<select name="ms_stripe_mode">
									<option value="test" <?php if ( ! empty( $settings['mode'] ) && 'test' == $settings['mode'] ) {
										print 'SELECTED';} ?>><?php esc_html_e( 'Test Mode', 'music-store-stripe-addon' ); ?></option>
									<option value="live" <?php if ( empty( $settings['mode'] ) || 'live' == $settings['mode'] ) {
										print 'SELECTED';} ?>><?php esc_html_e( 'Live/Production Mode', 'music-store-stripe-addon' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<a href="https://manage.stripe.com/account/apikeys" target="_blank"><?php esc_html_e( 'Publishable key', 'music-store-stripe-addon' ); ?></a>
							</th>
							<td>
								<input type="text" name="ms_stripe_publishable_key" size="40" value="<?php print esc_attr( ! empty( $settings['publishable_key'] ) ? $settings['publishable_key'] : '' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<a href="https://manage.stripe.com/account/apikeys" target="_blank"><?php esc_html_e( 'Secret key', 'music-store-stripe-addon' ); ?></a>
							</th>
							<td>
								<input type="text" name="ms_stripe_secret_key" size="40" value="<?php print esc_attr( ! empty( $settings['secret_key'] ) ? $settings['secret_key'] : '' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Language', 'music-store-stripe-addon' ); ?></th>
							<td>
								<select name="ms_stripe_language">
									<option value="" <?php if ( empty( $settings['language'] ) ) {
										print 'selected';} ?>><?php esc_html_e( 'auto (recommended)', 'music-store-stripe-addon' ); ?></option>
									<option value="da" <?php if ( 'da' == $settings['language'] ) {
										print 'selected';} ?>>Danish (da)</option>
									<option value="nl" <?php if ( 'nl' == $settings['language'] ) {
										print 'selected';} ?>>Dutch (nl)</option>
									<option value="en" <?php if ( 'en' == $settings['language'] ) {
										print 'selected';} ?>>English (en)</option>
									<option value="fi" <?php if ( 'fi' == $settings['language'] ) {
										print 'selected';} ?>>Finnish (fi)</option>
									<option value="fr" <?php if ( 'fr' == $settings['language'] ) {
										print 'selected';} ?>>French (fr)</option>
									<option value="de" <?php if ( 'de' == $settings['language'] ) {
										print 'selected';} ?>>German (de)</option>
									<option value="it" <?php if ( 'it' == $settings['language'] ) {
										print 'selected';} ?>>Italian (it)</option>
									<option value="ja" <?php if ( 'ja' == $settings['language'] ) {
										print 'selected';} ?>>Japanese (ja)</option>
									<option value="no" <?php if ( 'no' == $settings['language'] ) {
										print 'selected';} ?>>Norwegian (no)</option>
									<option value="zh" <?php if ( 'zh' == $settings['language'] ) {
										print 'selected';} ?>>Simplified Chinese (zh)</option>
									<option value="es" <?php if ( 'es' == $settings['language'] ) {
										print 'selected';} ?>>Spanish (es)</option>
									<option value="sv" <?php if ( 'sv' == $settings['language'] ) {
										print 'selected';} ?>>Swedish (sv)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Ask for billing address?', 'music-store-stripe-addon' ); ?></th>
							<td>
								<input type="checkbox" name="ms_stripe_billing_address" <?php if ( ! empty( $settings['billing_address'] ) ) {
									print 'CHECKED';} ?> />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Subtitle for payment panel', 'music-store-stripe-addon' ); ?></th>
							<td>
								<input type="text" name="ms_stripe_subtitle" size="40" value="<?php if ( ! empty( $settings['subtitle'] ) ) {
									print esc_attr( $settings['subtitle'] );} ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'URL of logo image', 'music-store-stripe-addon' ); ?></th>
							<td>
								<input type="text" name="ms_stripe_logo" size="40" value="<?php if ( ! empty( $settings['logo'] ) ) {
									print esc_attr( $settings['logo'] );} ?>" /><br />
								<em>* <?php esc_html_e( 'Absolute URL pointing to a square image of your brand or product. The recommended minimum size is 128x128px. The supported image types are: .gif, .jpeg, and .png', 'music-store-stripe-addon' ); ?>.</em>
							</td>
						</tr>
				   </table>
				</div>
			</div>
			<?php
		} // End show_settings

		public function save_settings() {
			$settings               = array(
				'enabled'         => ( isset( $_REQUEST['ms_stripe_enabled'] ) ? 1 : 0 ),
				'integrationtype' => ( isset( $_REQUEST['ms_stripe_integrationtype'] ) && 'sca' == $_REQUEST['ms_stripe_integrationtype'] ? 'sca' : '' ),
				'label'           => ( ! empty( $_REQUEST['ms_stripe_label'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ms_stripe_label'] ) ) : '' ),
				'mode'            => ( ( empty( $_REQUEST['ms_stripe_mode'] ) || 'test' == $_REQUEST['ms_stripe_mode'] ) ? 'test' : 'live' ),
				'publishable_key' => ( ! empty( $_REQUEST['ms_stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ms_stripe_publishable_key'] ) ) : '' ),
				'secret_key'      => ( ! empty( $_REQUEST['ms_stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ms_stripe_secret_key'] ) ) : '' ),
				'language'        => ( empty( $_REQUEST['ms_stripe_language'] ) ? 'auto' : strtolower( sanitize_text_field( wp_unslash( $_REQUEST['ms_stripe_language'] ) ) ) ),
				'billing_address' => ( isset( $_REQUEST['ms_stripe_billing_address'] ) ? 1 : 0 ),
				'subtitle'        => ( ! empty( $_REQUEST['ms_stripe_subtitle'] ) ? wp_kses_data( wp_unslash( $_REQUEST['ms_stripe_subtitle'] ) ) : '' ),
				'logo'            => ( ! empty( $_REQUEST['ms_stripe_logo'] ) ? esc_url_raw( trim( wp_unslash( $_REQUEST['ms_stripe_logo'] ) ) ) : '' ),
			);
			$this->current_settings = $settings;
			update_option( 'ms_stripe_settings', $settings );
		} // End save_settings

	} // End class MUSIC_STORE_STRIPE_ADDON
}

new MUSIC_STORE_STRIPE_ADDON();
