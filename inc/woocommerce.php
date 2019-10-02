<?php
add_action( 'woocommerce_thankyou_kopokopo', 'wc_kopo_add_content_thankyou_kopokopo' );
function wc_kopo_add_content_thankyou_kopokopo($order_id) 
{
    $order = wc_get_order($order_id);

	if ($order->get_payment_method() !== 'kopokopo'){
		return;
	} ?>

	<style>
		@keyframes wave {
			0%, 60%, 100% {
				transform: initial;
			}

			30% {
				transform: translateY(-15px);
			}
		}

		@keyframes blink {
			0% {
				opacity: .2;
			}

			20% {
				opacity: 1;
			}

			100% {
				opacity: .2;
			}
		}

		.saving span {
			animation: blink 1.4s linear infinite;
			animation-fill-mode: both;
		}

		.saving span:nth-child(2) {
			animation-delay: .2s;
		}

		.saving span:nth-child(3) {
			animation-delay: .4s;
		}
	</style>
	<section class="woocommerce-order-details kopokopo">
		<input type="hidden" id="current_order" value="<?php echo $order_id; ?>">
		<input type="hidden" id="payment_method" value="<?php echo $order->get_payment_method(); ?>">
		<p class="saving" id="kopokopo_receipt">Confirming receipt, please wait</p>
	</section><?php
}

add_action('wp_footer', 'kopo_ajax_polling');
function kopo_ajax_polling()
{ ?>
	<script id="kopoipn_kopochecker">
		var kopochecker = setInterval(() => {
			if (document.getElementById("payment_method") !== null && document.getElementById("payment_method").value !== 'kopokopo') {
				clearInterval(kopochecker);
			}

			jQuery(function($) {
				var order = $("#current_order").val();
				if (order !== undefined || order !== '') {
					$.get('<?php echo home_url('?kopoipncheck&order='); ?>' + order, [], function(data) {
						if (data.receipt == '' || data.receipt == 'N/A') {
							$("#kopokopo_receipt").html('Confirming payment <span>.</span><span>.</span><span>.</span><span>.</span><span>.</span><span>.</span>');
						} else {
							$(".woocommerce-order-overview").append('<li class="woocommerce-order-overview__payment-method method">Receipt number: <strong>' + data.receipt + '</strong></li>');
							$(".woocommerce-table--order-details > tfoot").find('tr:last-child').prev().after('<tr><th scope="row">Receipt number:</th><td>' + data.receipt +'</td></tr>');
							$("#kopokopo_receipt").html('Payment confirmed. Receipt number: <b>' + data.receipt + '</b>');
							clearInterval(kopochecker);
							return false;
						}
					})
				}
			});
		}, 3000);
	</script><?php
}
