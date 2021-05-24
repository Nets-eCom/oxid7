<?php 
require_once getShopBasePath() . 'modules/nets/api/nets_api.php';
require_once getShopBasePath() . 'modules/nets/api/netsPaymentTypes.php';

class netsPaymentGateway extends netsPaymentGateway_parent
{
	protected $_nets_log = false;

	public function executePayment($dAmount, &$oOrder)
	{
		nets_log::log($this->_nets_log, 'executePayment ', $this->getFunctionName);
		$this->_nets_log = $this->getConfig()->getConfigParam('nets_blDebug_log');
		// $ox_payment_id = $this->getSession()->getInstance()->getBasket()->getPaymentId();
		$ox_payment_id = $this->getSession()->getBasket()->getPaymentId();
		$payment_type = netsPaymentTypes::getNetsPaymentType($ox_payment_id);
		nets_log::log($this->_nets_log, "netsPaymentGateway executePayment: " . $payment_type);

		if (! isset($payment_type) || ! $payment_type) {
			nets_log::log($this->_nets_log, "netsPaymentGateway executePayment, parent");
			return parent::executePayment($dAmount, $oOrder);
		}
		nets_log::log($this->_nets_log, "netsPaymentGateway executePayment");
		$success = true;
		$this->getSession()->deleteVariable('nets_success');

		if (isset($success) && $success === true) {
			nets_log::log($this->_nets_log, "netsPaymentGateway executePayment - success");
			return true;
		}
		nets_log::log($this->_nets_log, "netsPaymentGateway executePayment - failure");
		return false;
	}
}
