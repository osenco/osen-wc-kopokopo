<?php
add_action( 'woocommerce_thankyou_kopokopo', 'wc_kopo_add_content_thankyou_kopokopo' );
function wc_kopo_add_content_thankyou_kopokopo($order_id) {

    $order = wc_get_order($order_id);

	if ($order->get_payment_method() !== 'kopokopo'){
		return;
	} ?>

	<style>
		@keyframes wave {

			0%,
			60%,
			100% {
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
