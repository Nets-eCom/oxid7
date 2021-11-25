<?php
if (! class_exists("nets_log")) {

    class nets_log
    {

        static function log($log)
        {
            // static function log() {
            if (! $log) {
                return;
            }

            $date = date("r");
            $logfile = getShopBasePath() . "log/nets.log";
            $x = 0;
            foreach (func_get_args() as $val) {
                $x ++;
                if ($x == 1) {
                    continue;
                }
                if (is_string($val) || is_numeric($val)) {
                    // file_put_contents(self::$logfile, "[$date] $val\n", FILE_APPEND);
                    file_put_contents($logfile, "[$date] $val\n", FILE_APPEND);
                } else {
                    // file_put_contents(self::$logfile, "[$date] ".print_r($val,true)."\n", FILE_APPEND);
                    file_put_contents($logfile, "[$date] " . print_r($val, true) . "\n", FILE_APPEND);
                }
            }
        }
    }
}

if (! function_exists('seems_utf8')) {

    function seems_utf8($Str)
    {
        for ($i = 0; $i < strlen($Str); $i ++) {
            if (ord($Str[$i]) < 0x80)
                continue; # 0bbbbbbb
            else if ((ord($Str[$i]) & 0xE0) == 0xC0)
                $n = 1; # 110bbbbb
            else if ((ord($Str[$i]) & 0xF0) == 0xE0)
                $n = 2; # 1110bbbb
            else if ((ord($Str[$i]) & 0xF8) == 0xF0)
                $n = 3; # 11110bbb
            else if ((ord($Str[$i]) & 0xFC) == 0xF8)
                $n = 4; # 111110bb
            else if ((ord($Str[$i]) & 0xFE) == 0xFC)
                $n = 5; # 1111110b
            else
                return false; // Does not match any model

            for ($j = 0; $j < $n; $j ++) {
                // n bytes matching 10bbbbbb follow ?
                if ((++ $i == strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80)) {
                    return false;
                }
            }
        }
        return true;
    }
}

if (! function_exists('utf8_ensure')) {

    function utf8_ensure($data)
    {
        if (is_string($data)) {
            return seems_utf8($data) ? $data : utf8_encode($data);
        } else if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = utf8_ensure($value);
            }
            unset($value);
            unset($key);
        } else if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = utf8_ensure($value);
            }
            unset($value);
            unset($key);
        }
        return $data;
    }
}

if (! class_exists("nets_table")) {

    class nets_table
    {

        /**
         *
         * @param
         *            $req_data
         * @param
         *            $ret_data
         * @param
         *            $hash
         * @param
         *            $payment_method
         * @param
         *            $oxorder_id
         * @param
         *            $amount
         * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
         * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
         */
        static function createTransactionEntry($req_data, $ret_data, $hash, $payment_id, $oxorder_id, $amount)
        {
            $oDB = oxDb::getDb(true);
            $sSQL = "INSERT INTO oxnets (req_data, ret_data, transaction_id, oxordernr, oxorder_id, amount, created)" . " VALUES(?, ?, ?, ?, ?, ?,now())";
            $oDB->execute($sSQL, [
                $req_data,
                $ret_data,
                $payment_id,
                $oxorder_id,
                $hash,
                $amount
            ]);
        }

        /**
         *
         * @param
         *            $hash
         * @param
         *            $transaction_id
         * @param bool $log_error
         * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
         * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
         */
        static function setTransactionId($hash, $transaction_id, $log_error = false)
        {
            if (! empty($hash) & ! empty($transaction_id)) {
                $oDB = oxDb::getDb(true);
                $sqlQuery = "UPDATE oxnets SET transaction_id = ? WHERE ISNULL(transaction_id) AND hash = ?";
                nets_log::log($log_error, 'nets_api, setTransactionId queries', $sqlQuery);
                $oDB->execute($sqlQuery, [
                    $transaction_id,
                    $hash
                ]);
            } else {
                nets_log::log($log_error, 'nets_api, hash or transaction_id empty');
            }
        }
    }
}
