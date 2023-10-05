<?php

/**
 * This file is part of OXID NETS module.
 * 
 */

namespace Es\NetsEasy\Core;

use OxidEsales\DoctrineMigrationWrapper\MigrationsBuilder;
use Symfony\Component\Console\Output\BufferedOutput;
use Es\NetsEasy\Application\Helper\Payment;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use Es\NetsEasy\Application\Helper\DebugLog;

/**
 * Class defines what module does on Shop events.
 */
class Events
{
    /**
     * Function to execute action on activate event
     * @return void
     */
    public static function onActivate()
    {
        self::executeModuleMigrations();
        self::addPaymentMethods();
    }

    /**
     * Function to execute action on deactivate event
     * @return void
     */
    public static function onDeactivate()
    {
        self::executeModuleMigrations();
    }

    /**
     * Execute necessary module migrations on activate event
     * @return void
     */
    protected static function executeModuleMigrations()
    {
        $migrations = (new MigrationsBuilder())->build();
        $output = new BufferedOutput();
        $migrations->setOutput($output);
        $needsUpdate = $migrations->execute('migrations:up-to-date', 'esnetseasy');
        if ($needsUpdate) {
            $migrations->execute('migrations:migrate', 'esnetseasy');
        }
    }

     /**
     * Add necessary payment method on activate event
     * @return void
     */
    protected static function addPaymentMethods()
    {
        DebugLog::getInstance()->log("addPaymentMethods called");
        
        foreach (Payment::getInstance()->getNetsPaymentTypes() as $sPaymentId => $aPaymentType) {
            //check if nets payment is completed
            $queryBuilder = ContainerFactory::getInstance()->getContainer()->get(QueryBuilderFactoryInterface::class)->create();
            $queryBuilder
                ->select('oxid')
                ->from('oxpayments')
                ->where('oxid = :paymentId')
                ->setParameter('paymentId', $sPaymentId);
            $sPaymentOxid = $queryBuilder->execute()->fetchOne();

            if (empty($sPaymentOxid)) {
                DebugLog::getInstance()->log("payment method added for : ".json_encode($aPaymentType));
                //create payment
                $sDesc = $aPaymentType['descEN'];
                $sDescDE = $aPaymentType['descDE'];
                if (!empty($sDesc)) {
                    $queryBuilder = ContainerFactory::getInstance()->getContainer()->get(QueryBuilderFactoryInterface::class)->create();
                    $queryBuilder->insert('oxpayments')
                        ->values([
                            'OXID' => '?',
                            'OXACTIVE' => '?',
                            'OXDESC' => '?',
                            'OXADDSUM' => '?',
                            'OXADDSUMTYPE' => '?',
                            'OXFROMBONI' => '?',
                            'OXFROMAMOUNT' => '?',
                            'OXTOAMOUNT' => '?',
                            'OXVALDESC' => '?',
                            'OXCHECKED' => '?',
                            'OXDESC_1' => '?',
                            'OXVALDESC_1' => '?',
                            'OXDESC_2' => '?',
                            'OXVALDESC_2' => '?',
                            'OXDESC_3' => '?',
                            'OXVALDESC_3' => '?',
                            'OXLONGDESC' => '?',
                            'OXLONGDESC_1' => '?',
                            'OXLONGDESC_2' => '?',
                            'OXLONGDESC_3' => '?',
                            'OXSORT' => '?',
                        ])
                        ->setParameter(0, $sPaymentId)->setParameter(1, 0)->setParameter(2, $sDescDE)->setParameter(3, 0)
                        ->setParameter(4, 'abs')->setParameter(5, 0)->setParameter(6, 0)->setParameter(7, 1000000)->setParameter(8, '')->setParameter(9, 0)
                        ->setParameter(10, $sDesc)->setParameter(11, '')->setParameter(12, '')->setParameter(13, '')->setParameter(14, '')->setParameter(15, '')
                        ->setParameter(16, '')->setParameter(17, '')->setParameter(18, '')->setParameter(19, '')->setParameter(20, 0)
                        ->execute();
                }
            }
        }
    }
}
