<?php

/*
 * nets payment type mapping to oxid payment ids
 *
 */
if (! class_exists("netsPaymentTypes")) {
	class netsPaymentTypes
	{
		static $nets_payment_types = Array(
			Array(
				'payment_id' => 'nets_easy',
				'payment_type' => 'netseasy',
				'payment_option_name' => 'nets_easy_active',
				'payment_desc' => 'Nets Easy',
				'payment_shortdesc' => 'Nets Easy'
			)
		);

		static function getNetsPaymentType($payment_id)
		{
			foreach (self::$nets_payment_types as $type) {
				if ($type['payment_id'] == $payment_id) {
					return $type['payment_type'];
				}
			}
			return false;
		}

		static function getNetsPaymentDesc($payment_id)
		{
			foreach (self::$nets_payment_types as $type) {
				if ($type['payment_id'] == $payment_id) {
					return $type['payment_desc'];
				}
			}
			 return false;
		}

		static function getNetsPaymentShortDesc($payment_id)
		{
			foreach (self::$nets_payment_types as $type) {
				if ($type['payment_id'] == $payment_id) {
					return $type['payment_shortdesc'];
				}
			}
			return false;
		}
	}
}
