<?php 

/**
 * Extending thank you controller for adding payment id in frontend
 */
class netsThankyou extends netsThankyou_parent
{
	public function getPaymentId()
	{
		$oOrder = $this->getOrder();
		$oDB = oxDb::getDb(true);
		$sSQL_select = "SELECT transaction_id FROM oxnets WHERE oxorder_id = ? LIMIT 1";
		$paymentId = $oDB->getOne($sSQL_select, [
			$oOrder->oxorder__oxid->value
		]);
		return $paymentId;
	}
}
