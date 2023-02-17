<?php 

/**
 * Extending thank you controller for adding payment id in frontend
 */
class netsThankyou extends netsThankyou_parent
{
	public function getPaymentId()
	{
		$oxSession = \oxNew(\OxidEsales\EshopCommunity\Core\Session::class);
		
		$oOrder = $this->getOrder();
		$oDB = oxDb::getDb(true);

		$sSQL_select = "SELECT transaction_id FROM oxnets WHERE oxorder_id = ? ORDER BY oxnets_id DESC LIMIT 1 ";
		$paymentId = $oDB->getOne($sSQL_select, [
			$oOrder->oxorder__oxid->value
		]);

		$oxSession->deleteVariable('payment_id');
		$oxSession->deleteVariable('paymentid');
		$oxSession->deleteVariable('sess_challenge');
		$oxSession->deleteVariable('orderNr');

		return  $paymentId;
	}

}
