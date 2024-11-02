<?php


/**
 * SMS Gateway handler class
 *
 * @author citrasms
 */
class CitraSMS_SMS_Gateways {

    private static $_instance;

    public static function init() {
        if ( !self::$_instance ) {
            self::$_instance = new CitraSMS_SMS_Gateways();
        }

        return self::$_instance;
    }

    

    /**
     * Sending SMS via Citrasms
     *
     * @param  array $sms_data
     * @return boolean
     */
    function citrasms( $sms_data ) {
		
       $citrasms_auth             = citrasms_get_option( 'citrasms_auth', 'citrasms_gateway', '' );
        $citrasms_secret           = citrasms_get_option( 'citrasms_secret', 'citrasms_gateway', '' );
			if(  $citrasms_auth !="" and $citrasms_secret!="" and $sms_data['number']!="" and $sms_data['sms_body']!=""){
					$content = 'to=' . rawurlencode( $sms_data['number'] ) .
							'&pesan=' . rawurlencode( $sms_data['sms_body'] ) .
							'&outhkey=' .  $citrasms_auth   .
							'&secret=' . $citrasms_secret ;
			$citrasmsreq = wp_remote_get( 'https://sms.citrahost.com/citra-sms.api.php?action=send&' . $content  );		
		
			$citrasms_response= wp_remote_retrieve_body( $citrasmsreq);

					if(preg_match('/SUCCESS/i',$citrasms_response)){
						 return true;
					}	
				   
			}
        return false;
    }

}
