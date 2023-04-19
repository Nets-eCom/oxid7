[{if $sPaymentID == "nets_easy"}]
	<div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy" type="radio" name="paymentid" value="nets_easy" checked="">
				<label for="payment_nets_easy"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						<div class="desc">
							[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
						</div>
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{elseif $sPaymentID == "nets_easy_card"}]
    <div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy_card" type="radio" name="paymentid" value="nets_easy_card" checked="">
				<label for="payment_nets_easy_card"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_card" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{elseif $sPaymentID == "nets_easy_sofort"}]
    <div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy_sofort" type="radio" name="paymentid" value="nets_easy_sofort" checked="">
				<label for="payment_nets_easy_sofort"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_sofort" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{elseif $sPaymentID == "nets_easy_ratepay_invoice"}]
    <div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy_ratepay_invoice" type="radio" name="paymentid" value="nets_easy_ratepay_invoice" checked="">
				<label for="payment_nets_easy_ratepay_invoice"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_ratepay_invoice" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{elseif $sPaymentID == "nets_easy_afterpay_invoice"}]
    <div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy_afterpay_invoice" type="radio" name="paymentid" value="nets_easy_afterpay_invoice" checked="">
				<label for="payment_nets_easy_afterpay_invoice"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_invoice" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{elseif $sPaymentID == "nets_easy_afterpay_instalment"}]
    <div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy_afterpay_instalment" type="radio" name="paymentid" value="nets_easy_afterpay_instalment" checked="">
				<label for="payment_nets_easy_afterpay_instalment"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_afterpay_instalment" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{elseif $sPaymentID == "nets_easy_paypal"}]
    <div class="well well-sm nets">
		<dl>
			<dt>
				<input id="payment_nets_easy_paypal" type="radio" name="paymentid" value="nets_easy_paypal" checked="">
				<label for="payment_nets_easy_paypal"> 
					<span></span> 
					<b>[{ oxmultilang ident="NETS_PAY_METHOD_nets_easy_paypal" }]</b> 
				</label>
                <div class="nets_payment_dec">
                    [{if $paymentmethod->oxpayments__oxlongdesc->value}]
						[{$paymentmethod->oxpayments__oxlongdesc->getRawValue()}]
					[{/if}]
                </div>
			</dt>
		</dl>
	</div>
[{else}]
	[{$smarty.block.parent}]
[{/if}]