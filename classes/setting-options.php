<?php

/**
 * WordPress settings API class
 *
 * @author Citrasms
 */

class CitraSMS_Setting_Options {

    private $settings_api;
    public static $shortcodes;

    function __construct() {

        $this->settings_api = new WeDevs_Settings_API;
        self::$shortcodes = apply_filters( 'citra_sms_shortcode_insert_description', 'For order id just insert <code>[order_id]</code> and for order status insert <code>[order_status]</code>. Similarly <code>[order_items]</code>, <code>[order_items_description]</code>, <code>[order_amount]</code>, <code>[billing_firstname]</code>, <code>[billing_lastname]</code>, <code>[billing_email]</code>, <code>[billing_address1]</code>, <code>[billing_address2]</code>, <code>[billing_country]</code>, <code>[billing_city]</code>, <code>[billing_state]</code>, <code>[billing_postcode]</code>, <code>[billing_phone]</code>, <code>[shipping_address1]</code>, <code>[shipping_country]</code>, <code>[shipping_city]</code>, <code>[shipping_state]</code>, <code>[shipping_postcode]</code>, <code>[payment_method]</code>' );

        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'admin_menu', array($this, 'admin_menu') );
        add_action( 'wsa_form_bottom_citrasms_message_diff_status', array( $this, 'citrasms_settings_field_message_diff_status' ) );
        add_action( 'wsa_form_bottom_citrasms_gateway', array( $this, 'citrasms_settings_field_gateway' ) );
    }

    /**
     * Admin init hook
     * @return void
     */
    function admin_init() {

        //set the settings
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );

        //initialize settings
        $this->settings_api->admin_init();
    }

    /**
     * Admin Menu CB
     * @return void
     */
    function admin_menu() {
        add_menu_page( __( 'SMS Settings', 'citrasms' ), __( 'SMS Settings', 'citrasms' ), 'manage_options', 'citra-order-sms-notification-settings', array( $this, 'plugin_page' ), 'dashicons-email-alt' );
        add_submenu_page( 'citra-order-sms-notification-settings', __( 'Kirim SMS', 'citrasms' ), __( 'Kirim SMS', 'citrasms' ), 'manage_options', 'citra-order-sms-send-any', array( $this, 'send_sms_to_any' ) );
    }

    /**
     * Send SMS to any submenu callback
     * @return void
     */
    function send_sms_to_any() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Send SMS to Any Number', 'citrasms' ); ?></h1>
            <div class="postbox send_sms_to_any_notice">
               
            </div>
            <?php if( isset( $_GET['message'] ) && $_GET['message'] == 'error' ): ?>
                <div class="error">
                    <p><?php _e( '<strong>Error:</strong> No Tujuan Harus Di Isi', 'citrasms' ) ?></p>
                </div>
            <?php endif; ?>

            <?php if( isset( $_GET['message'] ) && $_GET['message'] == 'gateway_problem' ): ?>
                <div class="error">
                    <p><?php _e( '<strong>Error:</strong> SMS Belum Di setting', 'citrasms' ); ?> </p>
                </div>
            <?php endif; ?>

            <?php if( isset( $_GET['message'] ) && $_GET['message'] == 'sending_failed' ): ?>
                <div class="error">
                    <p><?php _e( '<strong>Error:</strong> SMS Tidak Terkirim,Cek No Tujuan Ataupun Settingan SMS Anda', 'citrasms' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if( isset( $_GET['message'] ) && $_GET['message'] == 'success' ): ?>
                <div class="updated">
                    <p><?php _e( '<strong>Success:</strong> SMS Berhasil Di Kirimkan', 'citrasms' ) ?></p>
                </div>
            <?php endif; ?>

            <div class="postbox " id="citrasms_send_sms_any">
                <h3 class="hndle">Kirim SMS</h3>
                <div class="inside">
                    <form class="initial-form" id="citrasms-send-sms-any-form" method="post" action="" name="post">
                        <p>
                            <label for="citrasms_receiver_number">No Tujuan</label><br>
                            <input type="text" name="citrasms_receiver_number" id="citrasms_receiver_number">
                            <span><?php _e( 'Masukan No Tujuan SMS') ?></span>
                        </p>

                        <p>
                            <label for="citrasms_sms_body"><?php _e( 'Pesan', 'citrasms' ) ?></label><br>
                            <textarea name="citrasms_sms_body" id="citrasms_sms_body" cols="50" rows="6"></textarea>
                            <span><?php _e( 'Masukan Isi Pesan SMS ANDA') ?></span>
                        </p>

                        <p>
                            <?php wp_nonce_field( 'send_sms_to_any_action','send_sms_to_any_nonce' ); ?>
                            <input type="submit" class="button button-primary" name="citrasms_send_sms" value="<?php _e( 'Send SMS', 'citrasms' ); ?>">
                        </p>

                    </form>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Get All settings Field
     * @return array
     */
    function get_settings_sections() {
        $sections = array(
            array(
                'id' => 'citrasms_general',
                'title' => __( 'General Settings', 'citrasms' )
            ),
            array(
                'id' => 'citrasms_gateway',
                'title' => __( 'SMS Gateway Settings', 'citrasms' )
            ),

            array(
                'id' => 'citrasms_message',
                'title' => __( 'SMS Settings', 'citrasms' )
            ),

            array(
                'id' => 'citrasms_message_diff_status',
                'title' => __( 'SMS Body Settings', 'citrasms' )
            )
        );
        return apply_filters( 'citrasms_settings_sections' , $sections );
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {

        $buyer_message = "Thanks for purchasing\nYour [order_id] is now [order_status]\nThank you";
        $admin_message = "You have a new Order\nThe [order_id] is now [order_status]\n";
        $settings_fields = array(

            'citrasms_general' => apply_filters( 'citrasms_general_settings', array(
                array(
                    'name' => 'enable_notification',
                    'label' => __( 'Kirim SMS Notifikasi', 'citrasms' ),
                    'desc' => __( 'Jika di centang akan mengirimkan sms pada saat ada order', 'citrasms' ),
                    'type' => 'checkbox',
                ),

                array(
                    'name' => 'admin_notification',
                    'label' => __( 'Kirim Notifikasi Ke Admin', 'citrasms' ),
                    'desc' => __( 'Jika Di centang akan mengirimkan sms kepada admin pada saat ada order', 'citrasms' ),
                    'type' => 'checkbox',
                    'default' => 'on'
                ),

                array(
                    'name' => 'buyer_notification',
                    'label' => __( 'Kirim Notifikasi Ke Pembeli', 'citrasms' ),
                    'desc' => __( 'Jika dicentang, pembeli dapat memperoleh opsi pemberitahuan di halaman checkout', 'citrasms' ),
                    'type' => 'checkbox',
                ),

                array(
                    'name' => 'force_buyer_notification',
                    'label' => __( 'User Wajib Mengcentang Notifikasi SMS', 'citrasms' ),
                    'desc' => __( 'Jika pilih ya maka opsi pemberitahuan pembeli harus diwajibkan di halaman checkout', 'citrasms' ),
                    'type' => 'select',
                    'default' => 'no',
                    'options' => array(
                        'yes' => 'Yes',
                        'no'   => 'No'
                    )
                ),

                array(
                    'name' => 'buyer_notification_text',
                    'label' => __( 'Text Pada Form Checkout', 'citrasms' ),
                    'desc' => __( 'Text akan di tampilkan pada halaman checkout sebagai checkbox', 'citrasms' ),
                    'type' => 'textarea',
                    'default' => 'Kirim Notifikasi Pesanan Anda Melalui,Pastikan No Telepon Anda Benar)'
                ),
                array(
                    'name' => 'order_status',
                    'label' => __( 'Check Order Status ', 'citrasms' ),
                    'desc' => __( 'Pada status order mana sms akan dikirimkan', 'citrasms' ),
                    'type' => 'multicheck',
                    'options' => wc_get_order_statuses()
                )
            ) ),

            'citrasms_gateway' => apply_filters( 'citrasms_gateway_settings',  array(
                array(
                    'name' => 'sms_gateway',
                    'label' => __( 'Select your Gateway', 'citrasms' ),
                    'desc' => __( 'Select your sms gateway', 'citrasms' ),
                    'type' => 'select',
                    'default' => '-1',
                    'options' => $this->get_sms_gateway()
                ),
            ) ),

            'citrasms_message' => apply_filters( 'citrasms_message_settings',  array(
                array(
                    'name' => 'sms_admin_phone',
                    'label' => __( 'No Tujuan SMS', 'citrasms' ),
                    'desc' => __( '<br>Pemberitahuan SMS pesanan admin akan dikirim ke nomor ini.', 'citrasms' ),
                    'type' => 'text'
                ),
                array(
                    'name' => 'admin_sms_body',
                    'label' => __( 'Isi Pesan Ke Admin', 'citrasms' ),
                    'desc' => __( 'Tulis pesan Anda. Saat pesanan diterima, Anda akan mendapatkan pesan format ini.', 'citrasms' ) . ' ' . self::$shortcodes,
                    'type' => 'textarea',
                    'default' => __( $admin_message, 'citrasms' )
                ),

                array(
                    'name' => 'sms_body',
                    'label' => __( 'Isi Pesan Ke Pembeli', 'citrasms' ),
                    'desc' => __( 'Tulis pesan Anda. Saat pesanan diterima, Anda akan mendapatkan pesan format ini', 'citrasms' ) . ' ' . self::$shortcodes,
                    'type' => 'textarea',
                    'default' => __( $buyer_message, 'citrasms' )
                ),
            ) ),

            'citrasms_message_diff_status' => apply_filters( 'citrasms_message_diff_status_settings',  array(

                array(
                    'name' => 'enable_diff_status_mesg',
                    'label' => __( 'Bedakan isi pesan sms berdasarkan status order', 'citrasms' ),
                    'desc' => __( 'Jika dicentang maka admin dan pembeli mendapatkan isi pesan sms sesuai dengan status pesanan yang berbeda', 'citrasms' ),
                    'type' => 'checkbox'
                ),

            ) ),
        );
		

        return apply_filters( 'citrasms_settings_section_content', $settings_fields );
    }

    /**
     * Loaded Plugin page
     * @return void
     */
    function plugin_page() {
        echo '<div class="wrap">';

        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();

        echo '</div>';
    }

    /**
     * Get all the pages
     *
     * @return array page names with key value pairs
     */
    function get_pages() {
        $pages = get_pages();
        $pages_options = array();
        if ( $pages ) {
            foreach ($pages as $page) {
                $pages_options[$page->ID] = $page->post_title;
            }
        }

        return $pages_options;
    }

    /**
     * Get sms Gateway settings
     * @return array
     */
    function get_sms_gateway() {
        $gateway = array(
            'none'         => __( '--select--', 'citrasms' ),
            'citrasms' => __( 'Citrasms', 'citrasms' )
        );

        return apply_filters( 'citrasms_sms_gateway', $gateway );
    }

    function citrasms_settings_field_message_diff_status() {
        $enabled_order_status = citrasms_get_option( 'order_status', 'citrasms_general', array() );
        ?>
        <div class="citrasms_different_message_status_wrapper citrasms_hide_class">
            <hr>
            <?php if ( $enabled_order_status  ): ?>
                <h3>Format SMS Ke Pembeli</h3>
                <p style="margin-top:15px; margin-bottom:0px; padding-left: 20px; font-style: italic; font-size: 14px;">
                    <strong><?php _e( 'Atur konten sms Anda sesuai dengan status pesanan Anda yang diaktifkan di General Setting', 'citrasms' ); ?></strong><br>
                    <span><?php _e( 'Tulis pesan Anda. Saat pesanan dibuat, Anda akan mendapatkan jenis pesan format ini.', 'citrasms' ); echo ' ' .self::$shortcodes; ?></span>
                </p>
                <table class="form-table">
                    <?php foreach ( $enabled_order_status as $buyer_status_key => $buyer_status_value ): ?>
                        <?php
                            $buyer_display_order_status = str_replace( 'wc-', '', $buyer_status_key );
                            $buyer_content_value = citrasms_get_option( 'buyer-'.$buyer_status_key, 'citrasms_message_diff_status', '' );
                        ?>
                        <tr valign="top">
                            <th scrope="row"><?php echo sprintf( '%s %s', ucfirst( str_replace( '-', ' ', $buyer_display_order_status ) ) , __( 'Order Status', 'citrasms' ) ); ?></th>
                            <td>
                                <textarea class="regular-text" name="citrasms_message_diff_status[buyer-<?php echo $buyer_status_key; ?>]" id="citrasms_message_diff_status[buyer-<?php echo $buyer_status_key; ?>]" cols="55" rows="5"><?php echo $buyer_content_value; ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </table>

                <hr>

                <h3>For SMS Ke Admin</h3>
                <p style="margin-top:15px; margin-bottom:0px; padding-left: 20px; font-style: italic; font-size: 14px;">
                    <strong><?php _e( 'Tulis pesan Anda. Saat pesanan dibuat, Anda akan mendapatkan jenis pesan format ini', 'citrasms' ); ?></strong><br>
                    <span><?php _e( 'Tulis pesan khusus Anda. Saat pesanan dibuat, Anda akan mendapatkan jenis pesan format ini', 'citrasms' ); echo ' ' .self::$shortcodes; ?></span>
                </p>
                <table class="form-table">
                    <?php foreach ( $enabled_order_status as $admin_status_key => $admin_status_value ): ?>
                        <?php
                            $admin_display_order_status = str_replace( 'wc-', '', $admin_status_key );
                            $admin_content_value = citrasms_get_option( 'admin-'.$admin_status_key, 'citrasms_message_diff_status', '' );
                        ?>
                        <tr valign="top">
                            <th scrope="row"><?php echo sprintf( '%s %s', ucfirst( str_replace( '-', ' ', $admin_display_order_status ) ) , __( 'Order Status', 'citrasms' ) ); ?></th>
                            <td>
                                <textarea class="regular-text" name="citrasms_message_diff_status[admin-<?php echo $admin_status_key; ?>]" id="citrasms_message_diff_status[buyer-<?php echo $admin_status_key; ?>]" cols="55" rows="5"><?php echo $admin_content_value; ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </table>

            <?php else: ?>
                <p style="margin-top:15px; margin-bottom:0px; padding-left: 20px; font-size: 14px;"><?php _e( 'Maaf tidak ada order status yang anda pilih,silahkan setting melalui General Settings Tab') ?></p>
            <?php endif ?>
        </div>

        <?php
    }

    /**
     * SMS Gateway Settings Extra panel options
     * @return void
     */
    function citrasms_settings_field_gateway() {


        $citrasms_auth             = citrasms_get_option( 'citrasms_auth', 'citrasms_gateway', '' );
        $citrasms_secret           = citrasms_get_option( 'citrasms_secret', 'citrasms_gateway', '' );
       

        $citrasms_helper        = sprintf( 'Silahkan masukan auth secret api citrasms. If not then visit <a href="%s" target="_blank">%s</a>', 'http://citrasms.com/', 'Citrasms' );
      
        ?>

        <?php do_action( 'citrasms_gateway_settings_options_before' ); ?>

        

        <div class="citrasms_wrapper hide_class">
            <hr>
            <p style="margin-top:15px; margin-bottom:0px; padding-left: 20px; font-style: italic; font-size: 14px;">
                <strong><?php _e( $citrasms_helper , 'citrasms' ); ?></strong>
           </p>
            <table class="form-table">
               

                <tr valign="top">
                    <th scrope="row"><?php _e( 'Citrasms Auth', 'citrasms' ) ?></th>
                    <td>
                        <input type="text" name="citrasms_gateway[citrasms_auth]" id="citrasms_gateway[citrasms_auth]" value="<?php echo $citrasms_auth ; ?>">
                        <span><?php _e( 'Citrasms Auth', 'citrasms' ); ?></span>

                    </td>
                </tr>

                <tr valign="top">
                    <th scrope="row"><?php _e( 'Citrasms Secret', 'citrasms' ) ?></th>
                    <td>
                        <input type="text" name="citrasms_gateway[citrasms_secret]" id="citrasms_gateway[citrasms_secret]" value="<?php echo $citrasms_secret; ?>">
                        <span><?php _e( 'Citrasms Secret', 'citrasms' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>


        <?php do_action( 'citrasms_gateway_settings_options_after' ) ?>
        <?php
    }


} // End of CitraSMS_Setting_Options Class