
[{assign var="payment" value=$oView->getPayment()}]
[{assign var=PaymentID value= $payment->oxpayments__oxid->value}]
							


[{if $oView->netsIsEmbedded() && $payment->netsIsNetsPaymentUsed()}]
	[{oxscript include="js/libs/jquery.min.js" priority=1}]
	[{oxscript include="js/libs/jquery-ui.min.js" priority=1}]
	<div class="row">
			<div class="col-xs-12 col-md-6" id="orderShipping">
				<form action="[{$oViewConf->getSslSelfLink()}]" method="post">
					<div class="hidden">
						[{$oViewConf->getHiddenSid()}]
						<input type="hidden" name="cl" value="payment">
						<input type="hidden" name="fnc" value="">
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								[{oxmultilang ident="SHIPPING_CARRIER"}]
								<button type="submit" class="btn btn-xs btn-warning pull-right submitButton largeButton" title="[{oxmultilang ident="EDIT"}]">
									<i class="fa fa-pencil"></i>
								</button>
							</h3>
						</div>
						<div class="panel-body">
							[{assign var="oShipSet" value=$oView->getShipSet()}]
							[{$oShipSet->oxdeliveryset__oxtitle->value}]
						</div>
					</div>
				</form>
			</div>
			<div class="col-xs-12 col-md-6" id="orderPayment">
				<form action="[{$oViewConf->getSslSelfLink()}]" method="post">
					<div class="hidden">
						[{$oViewConf->getHiddenSid()}]
						<input type="hidden" name="cl" value="payment">
						<input type="hidden" name="fnc" value="">
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								[{oxmultilang ident="PAYMENT_METHOD"}]
								<button type="submit" class="btn btn-xs btn-warning pull-right submitButton largeButton" title="[{oxmultilang ident="EDIT"}]">
									<i class="fa fa-pencil"></i>
								</button>
							</h3>
						</div>
						<div class="panel-body">
							[{assign var="payment" value=$oView->getPayment()}]
							[{assign var=PaymentID value= $payment->oxpayments__oxid->value}]

							[{if $PaymentID == "nets_easy"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy" }]
							[{elseif $PaymentID == "nets_easy_card"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_card" }]
							[{elseif $PaymentID == "nets_easy_sofort"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_sofort" }]
							[{elseif $PaymentID == "nets_easy_ratepay_invoice"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_ratepay_invoice" }]
							[{elseif $PaymentID == "nets_easy_afterpay_invoice"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_invoice" }]
							[{elseif $PaymentID == "nets_easy_afterpay_instalment"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_instalment" }]
							[{elseif $PaymentID == "nets_easy_paypal"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_paypal" }]
							[{else}]
								[{$payment->oxpayments__oxdesc->value}]
							[{/if}]

						</div>
					</div>
				</form>
			</div>
		</div>

	[{assign var="checkoutKey" value=$oView->netsGetCheckoutKey()}]
	[{assign var="paymentId" value=$oView->netsGetPaymentId()}]
	
	[{oxstyle include=$oViewConf->getModuleUrl("esnetseasy", "out/src/css/embedded.css")}]
	
	<div id="dibs-block" class="agb card">
		<div class="card-header">
			[{if $PaymentID == "nets_easy"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy" }] </h3>
			[{elseif $PaymentID == "nets_easy_card"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_card" }] </h3>
			[{elseif $PaymentID == "nets_easy_sofort"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_sofort" }] </h3>
			[{elseif $PaymentID == "nets_easy_ratepay_invoice"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_ratepay_invoice" }] </h3>
			[{elseif $PaymentID == "nets_easy_afterpay_invoice"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_invoice" }] </h3>
			[{elseif $PaymentID == "nets_easy_afterpay_instalment"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_instalment" }] </h3>
			[{elseif $PaymentID == "nets_easy_paypal"}]
				<h3 class="card-title"> [{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_paypal" }] </h3>
			[{else}]
				<h3 class="card-title">[{$payment->oxpayments__oxdesc->value}]</h3>
			[{/if}]
		</div>
		<div class="card-body">
			<div id="dibs-complete-checkout"></div>
		</div>
	</div>

	<script type="text/javascript" src="[{ $oView->netsGetCheckoutJs() }]"></script>
	<script type="text/javascript">
		var checkoutOptions = {
			checkoutKey: "[{$checkoutKey}]", // checkout-key
			paymentId : "[{$paymentId}]",
			containerId : "dibs-complete-checkout",
			language: "[{$oView->netsGetLocaleCode()}]"
		};

		var checkout = new Dibs.Checkout(checkoutOptions);
		checkout.on('payment-completed', function(response) {                         
			$("#orderConfirmAgbBottom").submit();
		});
	</script>
	[{ oxscript include=$oView->netsGetLayoutJs() }]
[{else}]
		<div class="row">
			<div class="col-xs-12 col-md-6" id="orderShipping">
				<form action="[{$oViewConf->getSslSelfLink()}]" method="post">
					<div class="hidden">
						[{$oViewConf->getHiddenSid()}]
						<input type="hidden" name="cl" value="payment">
						<input type="hidden" name="fnc" value="">
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								[{oxmultilang ident="SHIPPING_CARRIER"}]
								<button type="submit" class="btn btn-xs btn-warning pull-right submitButton largeButton" title="[{oxmultilang ident="EDIT"}]">
									<i class="fa fa-pencil"></i>
								</button>
							</h3>
						</div>
						<div class="panel-body">
							[{assign var="oShipSet" value=$oView->getShipSet()}]
							[{$oShipSet->oxdeliveryset__oxtitle->value}]
						</div>
					</div>
				</form>
			</div>
			<div class="col-xs-12 col-md-6" id="orderPayment">
				<form action="[{$oViewConf->getSslSelfLink()}]" method="post">
					<div class="hidden">
						[{$oViewConf->getHiddenSid()}]
						<input type="hidden" name="cl" value="payment">
						<input type="hidden" name="fnc" value="">
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h3 class="panel-title">
								[{oxmultilang ident="PAYMENT_METHOD"}]
								<button type="submit" class="btn btn-xs btn-warning pull-right submitButton largeButton" title="[{oxmultilang ident="EDIT"}]">
									<i class="fa fa-pencil"></i>
								</button>
							</h3>
						</div>
						<div class="panel-body">
							[{assign var="payment" value=$oView->getPayment()}]
							[{assign var=PaymentID value= $payment->oxpayments__oxid->value}]

							[{if $PaymentID == "nets_easy"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy" }]
							[{elseif $PaymentID == "nets_easy_card"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_card" }]
							[{elseif $PaymentID == "nets_easy_sofort"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_sofort" }]
							[{elseif $PaymentID == "nets_easy_ratepay_invoice"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_ratepay_invoice" }]
							[{elseif $PaymentID == "nets_easy_afterpay_invoice"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_invoice" }]
							[{elseif $PaymentID == "nets_easy_afterpay_instalment"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_instalment" }]
							[{elseif $PaymentID == "nets_easy_paypal"}]
								[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_paypal" }]
							[{else}]
								[{$payment->oxpayments__oxdesc->value}]
							[{/if}]

						</div>
					</div>
				</form>
			</div>
		</div>
[{/if}]
