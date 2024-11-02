<?php
/*
Plugin Name: Citrasms Woocommerce SMS Notification
Plugin URI: https://citrasms.com/citrasms-woocommerce-sms-notification.html
Description: This is a WooCommerce add-on. By Using this plugin admin and buyer can get notification after placing order via sms using citrasms.com API.
Version: 1.9
Author: Citrasms
Author URI: http://citrasms.com/
*/

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

// Lib Directory Path Constant
define( 'CITRASMS_PLUGIN_LIB_PATH', dirname(__FILE__). '/lib' );
define( 'CITRASMS_DIR', dirname(__FILE__) );

// Requere settings api
require_once CITRASMS_PLUGIN_LIB_PATH. '/class.settings-api.php';

/**
 * Autoload class files on demand
 *
 * @param string $class requested class name
 */
function citra_sms_autoload( $class ) {

    if ( stripos( $class, 'CitraSMS_' ) !== false ) {

        $class_name = str_replace( array('CitraSMS_', '_'), array('', '-'), $class );
        $filename = dirname( __FILE__ ) . '/classes/' . strtolower( $class_name ) . '.php';

        if ( file_exists( $filename ) ) {
            require_once $filename;
        }
    }
}

spl_autoload_register( 'citra_sms_autoload' );

/**
 * Get SMS Settings Settings options value
 * @param  string $option
 * @param  string $section
 * @param  string $default
 * @return mixed
 */
function citrasms_get_option( $option, $section, $default = '' ) {

    $options = get_option( $section );

    if ( isset( $options[$option] ) ) {
        return $options[$option];
    }

    return $default;
}

/**
 * Citra_WC_Order_SMS class
 *
 * @class citra_WC_Order_SMS The class that holds the entire Citra_WC_Order_SMS plugin
 */
class Citra_WC_Order_SMS {

    /**
     * Constructor for the citra_WC_Order_SMS class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @uses is_admin()
     * @uses add_action()
     */
    public function __construct() {

        // Includes all files
        $this->includes();

        // Instantiate necessary class
        $this->instantiate();

        // Localize our plugin
        add_action( 'init', array( $this, 'localization_setup' ) );
        add_action( 'admin_init', array( $this, 'send_sms_to_any_receiver' ), 11 );

        // Loads frontend scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        // If not enable this feature then just simply return.
        if( citrasms_get_option( 'enable_notification', 'citrasms_general', 'off' ) == 'off' ) {
            return;
        }

        add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'add_buyer_notification_field' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'add_buyer_notification_field_process' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'buyer_notification_update_order_meta' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'buyer_sms_notify_display_admin_order_meta' ) , 10, 1 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box_order_page' ) );
        add_action( 'wp_ajax_citrasms_send_sms_to_buyer', array( $this, 'send_sms_from_order_page' ) );
        add_action( 'woocommerce_order_status_changed', array( $this, 'trigger_after_order_place' ), 10, 3 );

    }

    /**
     * Instantiate necessary Class
     * @return void
     */
    function instantiate() {
        new CitraSMS_Setting_Options();
        new CitraSMS_SMS_Gateways();
    }

    /**
     * Initializes the Citra_WC_Order_SMS() class
     *
     * Checks for an existing Citra_WC_Order_SMS() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new Citra_WC_Order_SMS();
        }

        return $instance;
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup() {
        load_plugin_textdomain( 'citrasms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Enqueue admin scripts
     *
     * Allows plugin assets to be loaded.
     *
     * @uses wp_enqueue_script()
     * @uses wp_localize_script()
     * @uses wp_enqueue_style
     */
    public function admin_enqueue_scripts() {

        wp_enqueue_style( 'admin-citrasms-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), false, date( 'Ymd' ) );
        wp_enqueue_script( 'admin-citrasms-scripts', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), false, true );

        wp_localize_script( 'admin-citrasms-scripts', 'citrasms', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' )
        ) );
    }

    /**
     * Includes all files
     *
     * @since 1.7
     *
     * @return void
     */
    public function includes() {
        require_once CITRASMS_DIR . '/inc/functions.php';
    }

    /**
     * Add Buyer Notification field in checkout page
     */
    function add_buyer_notification_field() {

        if( citrasms_get_option( 'buyer_notification', 'citrasms_general', 'off' ) == 'off' ) {
            return;
        }

        $required = ( citrasms_get_option( 'force_buyer_notification', 'citrasms_general', 'no' ) == 'yes' ) ? true : false;
        $checkbox_text = citrasms_get_option( 'buyer_notification_text', 'citrasms_general', 'Send me order status notifications via sms' );
        woocommerce_form_field( 'buyer_sms_notify', array(
            'type'          => 'checkbox',
            'class'         => array('buyer-sms-notify form-row-wide form-control'),
            'label'         => __( "&nbsp;&nbsp;&nbsp;&nbsp;".$checkbox_text, 'citrasms' ),
            'required'      => $required,
        ), 0);
    }

    /**
     * Add Buyer Notification field validation
     */
    function add_buyer_notification_field_process() {

        if( citrasms_get_option( 'force_buyer_notification', 'citrasms_general', 'no' ) == 'no' ) {
            return;
        }
		    if( citrasms_get_option( 'buyer_notification', 'citrasms_general', 'off' ) == 'off' ) {
				
            return;
        }

        // Check if the field is set, if not then show an error message.
        if ( ! isset( $_POST['buyer_sms_notify'] ) ) {
            wc_add_notice( __( '<strong>Send Notification Via SMS</strong> must be required' ), 'error' );
        }
    }

    /**
     * Display Buyer notification in Order admin page
     * @param  object $order
     * @return void
     */
    function buyer_sms_notify_display_admin_order_meta( $order ) {
        $want_notification =  get_post_meta( $order->get_id(), '_buyer_sms_notify', true );
        $display_info = (  isset( $want_notification ) && !empty( $want_notification ) ) ? 'Yes' : 'No';
        echo '<p><strong>'.__('Buyer want to get SMS notification').':</strong> ' . $display_info . '</p>';
    }

    /**
     * Update Order buyer notify meta in checkout page
     * @param  integer $order_id
     * @return void
     */
    function buyer_notification_update_order_meta( $order_id ) {
        if ( ! empty( $_POST['buyer_sms_notify'] ) ) {
            update_post_meta( $order_id, '_buyer_sms_notify', sanitize_text_field( $_POST['buyer_sms_notify'] ) );
        }
    }

    /**
     * Trigger when and order is placed
     * @param  integer $order_id
     * @param  string $old_status
     * @param  string $new_status
     * @return void
     */
    public  function trigger_after_order_place( $order_id, $old_status, $new_status ) {

        $order = new WC_Order( $order_id );

        if( !$order_id ) {
            return;
        }

        $admin_sms_data = $buyer_sms_data = array();

        $default_admin_sms_body  = __( 'You have a new Order. The [order_id] is now [order_status]', 'citrasms' );
        $default_buyer_sms_body  = __( 'Thanks for purchasing. Your [order_id] is now [order_status]. Thank you', 'citrasms' );
        $order_status_settings   = citrasms_get_option( 'order_status', 'citrasms_general', array() );
        $admin_phone_number      = citrasms_get_option( 'sms_admin_phone', 'citrasms_message', '' );
        $active_gateway          = citrasms_get_option( 'sms_gateway', 'citrasms_gateway', '' );
        $want_to_notify_buyer    = get_post_meta( $order_id, '_buyer_sms_notify', true );

        $wc_country_state        = new WC_Countries();
        $countries               = $wc_country_state->countries;
        $states                  = $wc_country_state->states;
        $order_amount            = get_post_meta( $order_id, '_order_total', true );

        $billing_firstname       = get_post_meta( $order_id, '_billing_first_name', true );
        $billing_lastname        = get_post_meta( $order_id, '_billing_last_name', true );
        $billing_email           = get_post_meta( $order_id, '_billing_email', true );

        $order_billing_address1  = get_post_meta( $order_id, '_billing_address_1', true );
        $order_billing_address2  = get_post_meta( $order_id, '_billing_address_2', true );
        $order_billing_country   = get_post_meta( $order_id, '_billing_country', true );
        $order_billing_city      = get_post_meta( $order_id, '_billing_city', true );
        $order_billing_state     = get_post_meta( $order_id, '_billing_state', true );
        $order_billing_zipcode   = get_post_meta( $order_id, '_billing_postcode', true );
        $order_billing_phone     = get_post_meta( $order_id, '_billing_phone', true );

        $order_shipping_address1 = get_post_meta( $order_id, '_shipping_address_1', true );
        $order_shipping_address2 = get_post_meta( $order_id, '_shipping_address_2', true );
        $order_shipping_country  = get_post_meta( $order_id, '_shipping_country', true );
        $order_shipping_city     = get_post_meta( $order_id, '_shipping_city', true );
        $order_shipping_state    = get_post_meta( $order_id, '_shipping_state', true );
        $order_shipping_zipcode  = get_post_meta( $order_id, '_shipping_postcode', true );

        $payment_method          = get_post_meta( $order_id, '_payment_method_title', true );
        $product_list            = $this->get_product_list( $order );
        $product_description_list = $this->get_product_description_list( $order );
        $check_if_diff_msg       = citrasms_get_option( 'enable_diff_status_mesg', 'citrasms_message_diff_status', 'off' );

        $new_wc_status = 'wc-' . $new_status;

        if ( $check_if_diff_msg == 'on' ) {
            $admin_sms_body     = citrasms_get_option( 'admin-'. $new_wc_status, 'citrasms_message_diff_status', $default_admin_sms_body );
            $buyer_sms_body     = citrasms_get_option( 'buyer-'. $new_wc_status, 'citrasms_message_diff_status', $default_buyer_sms_body );
        } else {
            $admin_sms_body     = citrasms_get_option( 'admin_sms_body', 'citrasms_message', $default_admin_sms_body );
            $buyer_sms_body     = citrasms_get_option( 'sms_body', 'citrasms_message', $default_buyer_sms_body );
        }

        if( count( $order_status_settings ) < 0 || empty( $active_gateway ) ) {
            return;
        }

        if ( empty( $admin_sms_body ) ) {
            $admin_sms_body = $default_admin_sms_body;
        }

        if ( empty( $buyer_sms_body ) ) {
            $buyer_sms_body = $default_buyer_sms_body;
        }

        $parse_data = array(
            'order_status'       => $new_status,
            'order_id'           => $order_id,
            'order_amount'       => $order_amount,
            'order_item'         => $product_list,
            'order_items_description' => $product_description_list,
            'billing_first_name' => $billing_firstname,
            'billing_last_name'  => $billing_lastname,
            'billing_email'      => $billing_email,
            'billing_address1'   => $order_billing_address1,
            'billing_address2'   => $order_billing_address2,
            'billing_country'    => isset( $countries[$order_billing_country] ) ? $countries[$order_billing_country] : '',
            'billing_city'       => $order_billing_city,
            'billing_state'      => ( isset( $states[$order_billing_country] ) && !empty( $states[$order_billing_country] ) ) ? $states[$order_billing_country][ $order_billing_state] : $order_billing_state,
            'billing_zipcode'    => $order_billing_zipcode,
            'billing_phone'      => $order_billing_phone,
            'shipping_address1'  => $order_shipping_address1,
            'shipping_address2'  => $order_shipping_address2,
            'shipping_country'   => isset( $countries[$order_shipping_country] ) ? $countries[$order_shipping_country] : '',
            'shipping_city'      => $order_shipping_city,
            'shipping_state'     => ( isset( $states[$order_shipping_country] ) && !empty( $states[$order_shipping_country] ) ) ? $states[$order_shipping_country][ $order_shipping_state] : $order_shipping_state,
            'shipping_zipcode'   => $order_shipping_zipcode,
            'payment_method'     => $payment_method
        );

        if( in_array( $new_wc_status, $order_status_settings ) ) {

            if( $want_to_notify_buyer ) {

                if(  citrasms_get_option( 'admin_notification', 'citrasms_general', 'on' ) == 'on' ) {
                    $parse_body_data =
                    $admin_sms_data['number']   = $admin_phone_number;
                    $admin_sms_data['sms_body'] = $this->pharse_sms_body( $admin_sms_body, $parse_data );
                    $admin_response             = CitraSMS_SMS_Gateways::init()->$active_gateway( $admin_sms_data );

                    if( $admin_response ) {
                        $order->add_order_note( __( 'SMS Send Berhasil Ke Admin '.  $admin_sms_data['number'], 'citrasms' ) );
                    } else {
                        $order->add_order_note( __( 'SMS Send Failed, Something wrong '.  $admin_sms_data['number'], 'citrasms' ) );
                    }
                }

                $buyer_sms_data['number']   = get_post_meta( $order_id, '_billing_phone', true );
                $buyer_sms_data['sms_body'] = $this->pharse_sms_body( $buyer_sms_body, $parse_data );
                $buyer_response             = citraSMS_SMS_Gateways::init()->$active_gateway( $buyer_sms_data );

                if( $buyer_response ) {
                    $order->add_order_note( __( 'SMS Berhasil Di Kirimkan Ke Pembeli', 'citrasms' ) );
                } else {
                    $order->add_order_note( __( 'SMS Gagal Di Kirimkan Ke Pembeli', 'citrasms' ) );
                }

            } else {

                if(  citrasms_get_option( 'admin_notification', 'citrasms_general', 'on' ) == 'on' ) {
                    $admin_sms_data['number']   = $admin_phone_number;
                    $admin_sms_data['sms_body'] = $this->pharse_sms_body( $admin_sms_body, $parse_data );
                    $admin_response             = CitraSMS_SMS_Gateways::init()->$active_gateway( $admin_sms_data );

                    if( $admin_response ) {
                        $order->add_order_note( __( 'SMS Berhasil Di Kirimkan Ke Admin '.  $admin_sms_data['number'], 'citrasms' ) );
                    } else {
                        $order->add_order_note( __( 'SMS Gagal Dikirimkan Ke Admin ', 'citrasms' ) );
                    }
                }
            }
        }
    }

    /**
     * Pharse Message body with necessary variables
     * @param  string $content
     * @param  string $order_status
     * @param  integer $order_id
     * @return string
     */
    public function pharse_sms_body( $content, $data ) {

        $order = 'Order#'.$data['order_id'];
        $order_total = $data['order_amount']. ' '. get_post_meta( $data['order_id'], '_order_currency', true );
        $find = citra_sms_get_order_shortcodes();
        $replace = array(
            $data['order_id'],
            $data['order_status'],
            $data['order_amount'],
            $data['order_item'],
            $data['order_items_description'],
            $data['billing_first_name'],
            $data['billing_last_name'],
            $data['billing_email'],
            $data['billing_address1'],
            $data['billing_address2'],
            $data['billing_country'],
            $data['billing_city'],
            $data['billing_state'],
            $data['billing_zipcode'],
            $data['billing_phone'],
            $data['shipping_address1'],
            $data['shipping_address2'],
            $data['shipping_country'],
            $data['shipping_city'],
            $data['shipping_state'],
            $data['shipping_zipcode'],
            $data['payment_method'],
        );

        $body = str_replace( $find, $replace, $content );

        return apply_filters( 'citra_sms_pharse_body', $body, $data );
    }
    /**
     * Add Meta box in Order admin page
     * @param string $post_type
     */
    public function add_meta_box_order_page( $post_type ) {
        if( $post_type == 'shop_order' ) {
            add_meta_box( 'send_sms_to_buyer', __( 'Send SMS to Buyer', 'citrasms' ), array( $this, 'render_meta_box_content' ), 'shop_order', 'side', 'high' );
        }
    }

    /**
     * Callback for add beta box for displaying content
     * @param  object $post
     * @return void
     */
    public function render_meta_box_content( $post ) {
        ?>
        <div class="citrasms_send_sms" style="position:relative">
            <div class="citrasms_send_sms_result"></div>
            <h4><?php _e( 'Send Custom SMS to this buyer', 'citrasms' ) ?></h4>
            <p><?php _e( 'Message will be send in this buyer billing number ', 'citrasms' ) ?><code><?php echo get_post_meta( $post->ID, '_billing_phone', 'true' ) ?></code></p>
            <p>
                <textarea rows="5" cols="20" class="input-text" id="citrasms_sms_to_buyer" name="citrasms_sms_to_buyer" style="width: 246px; height: 78px;"></textarea>
            </p>
            <p>
                <?php wp_nonce_field('citrasms_send_sms_action','citrasms_send_sms_nonce'); ?>
                <input type="hidden" name="order_id" value="<?php echo $post->ID; ?>">
                <input type="submit" class="button" name="citrasms_send_sms" id="citrasms_send_sms_button" value="Send SMS">
            </p>
            <div id="citrasms_send_sms_overlay_block"><img src="<?php echo plugins_url('assets/images/ajax-loader.gif', __FILE__ ); ?>" alt=""></div>
        </div>

        <?php
    }

    /**
     * Send SMS from order edit page
     * @return json true|false
     */
    function send_sms_from_order_page() {
        $active_gateway = citrasms_get_option( 'sms_gateway', 'citrasms_gateway', '' );

        if( empty( $active_gateway ) ) {
            wp_send_json_error( array('message' => 'Your gateway doesn\'t set') );
        }

        $buyer_sms_data['number']   = get_post_meta( $_POST['order_id'], '_billing_phone', true );
        $buyer_sms_data['sms_body'] = $_POST['textareavalue'];

        $buyer_response = CitraSMS_SMS_Gateways::init()->$active_gateway( $buyer_sms_data );
        if( $buyer_response ) {
            wp_send_json_success( array('message' => 'Message Send Successfully') );
        } else {
            wp_send_json_error( array('message' => 'Sending Failed, Somthing Wrong') );
        }
    }

    /**
     * Get product items list from order
     * @param  object $order
     * @return string  [list of product]
     */
    function get_product_list( $order ) {

        $product_list = '';
        $order_item = $order->get_items();

        foreach( $order_item as $product ) {
            $prodct_name[] = $product['name'];
        }

        $product_list = implode( ',', $prodct_name );

        return $product_list;
    }

    /**
     * Get product items list from order
     * @param  object $order
     * @return string  [list of product]
     */
    function get_product_description_list( $order ) {
        $product_list = '';
        $order_item = $order->get_items();

        foreach( $order_item as $product ) {
            $product_description[] = get_post( $product['product_id'] )->post_content;
        }

        $product_list = implode( ',', $product_description );

        return $product_list;
    }

    /**
     * Send SMS to any Number from admin panel
     * @return void
     */
    function send_sms_to_any_receiver() {
	
        if( isset( $_POST['citrasms_send_sms'] ) && wp_verify_nonce( $_POST['send_sms_to_any_nonce'], 'send_sms_to_any_action' ) ) {
            if( isset( $_POST['citrasms_receiver_number'] ) && empty( $_POST['citrasms_receiver_number'] ) ) {
                wp_redirect( add_query_arg( array( 'page'=> 'citra-order-sms-send-any', 'message' => 'error' ), admin_url( 'admin.php' ) ) );
            } else {
                $active_gateway = citrasms_get_option( 'sms_gateway', 'citrasms_gateway', '' );

                if( empty( $active_gateway ) || $active_gateway == 'none' ) {

                    wp_redirect( add_query_arg( array( 'page'=> 'citra-order-sms-send-any', 'message' => 'gateway_problem' ), admin_url( 'admin.php' ) ) );

                } else {

                    $receiver_sms_data['number']   = $_POST['citrasms_receiver_number'];
                    $receiver_sms_data['sms_body'] = $_POST['citrasms_sms_body'];
                    $receiver_response = CitraSMS_SMS_Gateways::init()->$active_gateway( $receiver_sms_data );

                    if( $receiver_response ) {
                        wp_redirect( add_query_arg( array( 'page'=> 'citra-order-sms-send-any', 'message' => 'success' ), admin_url( 'admin.php' ) ) );
                    } else {
                        wp_redirect( add_query_arg( array( 'page'=> 'citra-order-sms-send-any', 'message' => 'sending_failed' ), admin_url( 'admin.php' ) ) );
                    }
                }
            }
        }
    }

} // Citrasms_WC_Order_SMS

/**
 * Loaded after all plugin initialize
 */
add_action( 'plugins_loaded', 'load_citra_wc_order_sms' );

function load_citra_wc_order_sms() {
    $citrasms = Citra_WC_Order_SMS::init();
}
