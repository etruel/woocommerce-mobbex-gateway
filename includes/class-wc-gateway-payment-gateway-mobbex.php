<?php
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * WooCommerce Gateway Name.
 *
 * @class   WC_Gateway_Payment_Gateway_mobbex
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package WooCommerce Payment Gateway mobbex/Includes
 * @author  Sebastien Dumont
 */
class WC_Gateway_Payment_Gateway_mobbex extends WC_Payment_Gateway {

  /**
   * Constructor for the gateway.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    $this->id                 = 'mobbex';
    $this->icon               = apply_filters( 'woocommerce_payment_gateway_mobbex_icon', plugins_url( '/assets/images/cards.png', dirname( __FILE__ ) ) );
    $this->has_fields         = false;
    $this->credit_fields      = false;

    $this->order_button_text  = __( 'Pay with Mobbex', 'woocommerce-payment-gateway-mobbex' );

    $this->method_title       = __( 'Mobbex', 'woocommerce-payment-gateway-mobbex' );
    $this->method_description = __( 'Take payments via Mobbex.', 'woocommerce-payment-gateway-mobbex' );

    // TODO: Rename 'WC_Gateway_Payment_Gateway_mobbex' to match the name of this class.
    $this->notify_url         = WC()->api_request_url( 'WC_Gateway_Payment_Gateway_mobbex' );

    // TODO: 
    $this->api_endpoint       = 'https://api.payment-gateway.com/';

    // TODO: Use only what the payment gateway supports.
    $this->supports           = array(
      'subscriptions',
      'products',
      'subscription_cancellation',
      'subscription_reactivation',
      'subscription_suspension',
      'subscription_amount_changes',
      'subscription_payment_method_change',
      'subscription_date_changes',
      'default_credit_card_form',
      'refunds',
      'pre-orders'
    );

    // TODO: Replace the transaction url here or use the function 'get_transaction_url' at the bottom.
    $this->view_transaction_url = 'https://www.domain.com';

    // Load the form fields.
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();

    // Get setting values.
    $this->enabled        = $this->get_option( 'enabled' );

    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    $this->instructions   = $this->get_option( 'instructions' );

    $this->api_key    = $this->get_option( 'api_key' );
    $this->access_token     = $this->get_option( 'access_token' );

    $this->debug          = $this->get_option( 'debug' );

    // Logs.
    if( $this->debug == 'yes' ) {
      if( class_exists( 'WC_Logger' ) ) {
        $this->log = new WC_Logger();
      }
      else {
        $this->log = $woocommerce->logger();
      }
    }

    $this->init_gateway_sdk();

    // Hooks.
    if( is_admin() ) {
      add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
      add_action( 'admin_notices', array( $this, 'checks' ) );

      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

    // Customer Emails.
    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
  }

  /**
   * Init Payment Gateway SDK.
   *
   * @access protected
   * @return void
   */
  protected function init_gateway_sdk() {
    // TODO: Insert your gateway sdk script here and call it.
  }

  /**
   * Admin Panel Options
   * - Options for bits like 'title' and availability on a country-by-country basis
   *
   * @access public
   * @return void
   */
  public function admin_options() {
    include_once( WC_Gateway_Mobbex()->plugin_path() . '/includes/admin/views/admin-options.php' );
  }

  /**
   * Check if SSL is enabled and notify the user.
   *
   * @TODO:  Use only what you need.
   * @access public
   */
  public function checks() {
    if( $this->enabled == 'no' ) {
      return;
    }

    // PHP Version.
    if( version_compare( phpversion(), '5.3', '<' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'Mobbex Error: Mobbex requires PHP 5.3 and above. You are using version %s.', 'woocommerce-payment-gateway-mobbex' ), phpversion() ) . '</p></div>';
    }

    // Check required fields.
    else if( !$this->api_key || !$this->access_token) {
      echo '<div class="error"><p>' . __( 'Mobbex Error: Please enter your Api Key and Access Token', 'woocommerce-payment-gateway-mobbex' ) . '</p></div>';
    }

    // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
    /*else if( 'no' == get_option( 'woocommerce_force_ssl_checkout' ) && !class_exists( 'WordPressHTTPS' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'Mobbex is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Gateway Name will only work in sandbox mode.', 'woocommerce-payment-gateway-mobbex'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
    }
    */
  }

  /**
   * Check if this gateway is enabled.
   *
   * @access public
   */
  public function is_available() {
    if( $this->enabled == 'no' ) {
      return false;
    }

  
    if( !$this->api_key || !$this->access_token ) {
      return false;
    }

    return true;
  }

  /**
   * Initialise Gateway Settings Form Fields
   *
   * The standard gateway options have already been applied. 
   * Change the fields to match what the payment gateway your building requires.
   *
   * @access public
   */
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'       => __( 'Enable/Disable', 'woocommerce-payment-gateway-mobbex' ),
        'label'       => __( 'Enable Mobbex', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
      ),
      'title' => array(
        'title'       => __( 'Title', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-payment-gateway-mobbex' ),
        'default'     => __( 'Mobbex', 'woocommerce-payment-gateway-mobbex' ),
        'desc_tip'    => true
      ),
      'description' => array(
        'title'       => __( 'Description', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'text',
        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-payment-gateway-mobbex' ),
        'default'     => 'Pay with Mobbex.',
        'desc_tip'    => true
      ),
      'instructions' => array(
        'title'       => __( 'Instructions', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'textarea',
        'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-payment-gateway-mobbex' ),
        'default'     => '',
        'desc_tip'    => true,
      ),
      'debug' => array(
        'title'       => __( 'Debug Log', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'checkbox',
        'label'       => __( 'Enable logging', 'woocommerce-payment-gateway-mobbex' ),
        'default'     => 'no',
        'description' => sprintf( __( 'Log Mobbex events inside <code>%s</code>', 'woocommerce-payment-gateway-mobbex' ), wc_get_log_file_path( $this->id ) )
      ),
     
      
      'api_key' => array(
        'title'       => __( 'API Key', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'text',
        'description' => __( 'Get your API Key from your Mobbex Name account.', 'woocommerce-payment-gateway-mobbex' ),
        'default'     => '',
        'desc_tip'    => true
      ),
      'access_token' => array(
        'title'       => __( 'Access Token', 'woocommerce-payment-gateway-mobbex' ),
        'type'        => 'text',
        'description' => __( 'Get your API keys from your Mobbex Name account.', 'woocommerce-payment-gateway-mobbex' ),
        'default'     => '',
        'desc_tip'    => true
      ),
     
    );
  }

  /**
   * Output for the order received page.
   *
   * @access public
   * @return void
   */
  public function receipt_page( $order ) {
    echo '<p>' . __( 'Thank you - your order is now pending payment.', 'woocommerce-payment-gateway-mobbex' ) . '</p>';

    // TODO: 
  }

  /**
   * Payment form on checkout page.
   *
   * @TODO:  Use this function to add credit card 
   *         and custom fields on the checkout page.
   * @access public
   */
  public function payment_fields() {
    $description = $this->get_description();

    
    if( !empty( $description ) ) {
      echo wpautop( wptexturize( trim( $description ) ) );
    }

    // If credit fields are enabled, then the credit card fields are provided automatically.
    if( $this->credit_fields ) {
      $this->credit_card_form(
        array( 
          'fields_have_names' => false
        )
      );
    }

    // This includes your custom payment fields.
    include_once( WC_Gateway_Mobbex()->plugin_path() . '/includes/views/html-payment-fields.php' );

  }

  /**
   * Outputs scripts used for the payment gateway.
   *
   * @access public
   */
  public function payment_scripts() {
    if( !is_checkout() || !$this->is_available() ) {
      return;
    }

    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

    // TODO: Enqueue your wp_enqueue_script's here.

  }

  /**
   * Output for the order received page.
   *
   * @access public
   */
  public function thankyou_page( $order_id ) {
    if( !empty( $this->instructions ) ) {
      echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
    }

    $this->extra_details( $order_id );
  }

  /**
   * Add content to the WC emails.
   *
   * @access public
   * @param  WC_Order $order
   * @param  bool $sent_to_admin
   * @param  bool $plain_text
   */
  public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if( !$sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
      if( !empty( $this->instructions ) ) {
        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
      }

      $this->extra_details( $order->id );
    }
  }

  /**
   * Gets the extra details you set here to be 
   * displayed on the 'Thank you' page.
   *
   * @access private
   */
  private function extra_details( $order_id = '' ) {
    echo '<h2>' . __( 'Extra Details', 'woocommerce-payment-gateway-mobbex' ) . '</h2>' . PHP_EOL;

    // TODO: Place what ever instructions or details the payment gateway needs to display here.
  }

  /**
   * Process the payment and return the result.
   *
   * @TODO   You will need to add payment code inside.
   * @access public
   * @param  int $order_id
   * @return array
   */
  public function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );

    $return_url = add_query_arg( 'wcm_p_method', 'mobbex', $this->get_return_url( $order ));
    // This array is used just for demo testing a successful transaction.
    $description = '';
    foreach ( $order->get_items() as $item ) {
        $description .= $item['name'].PHP_EOL;
    }

    $response = wp_remote_post('https://mobbex.com/p/checkout/create', array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(
              'postman-token' => '4533ef25-f802-5fcc-cc03-'.md5(time()),
              'cache-control' => 'no-cache',
              'content-type' => 'application/x-www-form-urlencoded',
              'x-access-token' =>  $this->access_token,
              'x-api-key' => $this->api_key
          ),
        'body' => array(
              'total' => $order->get_total(),
              'reference' => '#'.$order_id,
              'description' => $description,
              'return_url' => $return_url
          ),
        'cookies' => array()
        )
    );

    if ( is_wp_error( $response ) ) {
        $this->log->add( $this->id, 'Mobbex error: ' . $response->get_error_message() . '' );
    } else {
      
      try {
        
        if( $this->debug == 'yes' ) {
          $this->log->add( $this->id, 'Mobbex payment response: ' . print_r($response['body'], true ) . ')' );
        }   
        $json_response = json_decode($response['body']);

        ob_start();
        var_dump($json_response);
        $deug = ob_get_clean();
        error_log($deug);

        if (!empty($json_response->data->url) &&  $json_response->result) {
            return array(
              'result' => 'success',
              'redirect' => $json_response->data->url
            );
        }
       
      } catch (HttpException $ex) {
          wc_add_notice($ex->getMessage(), 'error' );
          if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'Mobbex error: ' . $ex->getMessage() . '' );
          }
      }




    }
   
    
   
    return array(
        'result' => 'failure',
        'redirect' => ''
    );
    /**
     
     
    if( 'APPROVED' == $payment['status'] ) {
      // Payment complete.
      $order->payment_complete();

      // Store the transaction ID for WC 2.2 or later.
      add_post_meta( $order->id, '_transaction_id', $payment['id'], true );

      // Add order note.
      $order->add_order_note( sprintf( __( 'Gateway Name payment approved (ID: %s)', 'woocommerce-payment-gateway-mobbex' ), $payment['id'] ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Gateway Name payment approved (ID: ' . $payment['id'] . ')' );
      }

      // Reduce stock levels.
      $order->reduce_order_stock();

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Stocked reduced.' );
      }

      // Remove items from cart.
      WC()->cart->empty_cart();

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Cart emptied.' );
      }

      // Return thank you page redirect.
      return array(
        'result'   => 'success',
        'redirect' => $this->get_return_url( $order )
      );
    }
    else {
      // Add order note.
      $order->add_order_note( __( 'Gateway Name payment declined', 'woocommerce-payment-gateway-mobbex' ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Gateway Name payment declined (ID: ' . $payment['id'] . ')' );
      }

      // Return message to customer.
      return array(
        'result'   => 'failure',
        'message'  => '',
        'redirect' => ''
      );
    }*/
  }

  /**
   * Process refunds.
   * WooCommerce 2.2 or later
   *
   * @access public
   * @param  int $order_id
   * @param  float $amount
   * @param  string $reason
   * @return bool|WP_Error
   */
  public function process_refund( $order_id, $amount = null, $reason = '' ) {

    $payment_id = get_post_meta( $order_id, '_transaction_id', true );
    $response = ''; // TODO: Use this variable to fetch a response from your payment gateway, if any.

    if( is_wp_error( $response ) ) {
      return $response;
    }

    if( 'APPROVED' == $refund['status'] ) {

      // Mark order as refunded
      $order->update_status( 'refunded', __( 'Payment refunded via Gateway Name.', 'woocommerce-payment-gateway-mobbex' ) );

      $order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'woocommerce-payment-gateway-mobbex' ), $refunded_cost, $refund_transaction_id ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Gateway Name order #' . $order_id . ' refunded successfully!' );
      }
      return true;
    }
    else {

      $order->add_order_note( __( 'Error in refunding the order.', 'woocommerce-payment-gateway-mobbex' ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Error in refunding the order #' . $order_id . '. Gateway Name response: ' . print_r( $response, true ) );
      }

      return true;
    }

  }

  /**
   * Get the transaction URL.
   *
   * @TODO   Replace both 'view_transaction_url'\'s. 
   *         One for sandbox/testmode and one for live.
   * @param  WC_Order $order
   * @return string
   */
  public function get_transaction_url( $order ) {
    
      $this->view_transaction_url = 'https://www.sandbox.payment-gateway.com/?trans_id=%s';
   

    return parent::get_transaction_url( $order );
  }

} // end class.

?>