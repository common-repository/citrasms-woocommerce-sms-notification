;(function($) {

	// Gateway select change event
	$('.hide_class').hide();
	$('.citrasms_hide_class').hide();
	$('#citrasms_gateway\\[sms_gateway\\]').on( 'change', function() {
		var self = $(this),
			value = self.val();
		$('.hide_class').hide();
		$('.'+value+'_wrapper').fadeIn();
	});

	$('#wpuf-citrasms_message_diff_status\\[enable_diff_status_mesg\\]').on( 'change', function() {
		var self = $(this),
			value = self.val();
		if ( self.is(':checked')) {
			$('.citrasms_hide_class').hide();
			$('.citrasms_different_message_status_wrapper').fadeIn();
		} else {
			$('.citrasms_hide_class').hide();
		}
	});

	// Trigger when a change occurs in gateway select box
	$('#citrasms_gateway\\[sms_gateway\\]').trigger('change');
	$('#wpuf-citrasms_message_diff_status\\[enable_diff_status_mesg\\]').trigger('change');

	// handle send sms from order page in admin panale
	var w = $('.citrasms_send_sms').width(),
		h = $('.citrasms_send_sms').height(),
		block = $('#citrasms_send_sms_overlay_block').css({
					'width' : w+'px',
					'height' : h+'px',
				});


	$( 'input#citrasms_send_sms_button' ).on( 'click', function(e) {
		e.preventDefault();
		var self = $(this),
			textareaValue = $('#citrasms_sms_to_buyer').val(),
			smsNonce = $('#citrasms_send_sms_nonce').val(),
			orderId = $('input[name=order_id][type=hidden]').val(),
			data = {
				action : 'citrasms_send_sms_to_buyer',
				textareavalue: textareaValue,
				sms_nonce: smsNonce,
				order_id: orderId
			};

		if( !textareaValue ) {
			return;
		}
		self.attr( 'disabled', true );
		block.show();
		$.post( citrasms.ajaxurl, data , function( res ) {
			if ( res.success ) {
				$('div.citrasms_send_sms_result').html( res.data.message ).show();
				$('#citrasms_sms_to_buyer').val('');
				block.hide();
				self.attr( 'disabled', false );
			} else {
				$('div.citrasms_send_sms_result').html( res.data.message ).show();
				block.hide();
				self.attr( 'disabled', false );
			}
		});
	});


})(jQuery);