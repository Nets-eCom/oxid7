<?php

namespace NexiCheckout\Core;

use Doctrine\DBAL\Query\QueryBuilder;
use OxidEsales\DoctrineMigrationWrapper\MigrationsBuilder;
use Symfony\Component\Console\Output\BufferedOutput;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;

class Events
{
    public static function onActivate(): void
    {
        self::executeModuleMigrations();
        self::addPayment();
    }

    public static function onDeactivate(): void
    {
        self::getQueryBuilder()
            ->update('oxpayments')
            ->set("oxactive", 0)
            ->where('oxid = :moduleId')
            ->setParameter('moduleId', Module::ID)
            ->execute();
    }

    private static function executeModuleMigrations(): void
    {
        $migrations = (new MigrationsBuilder())->build();
        $output = new BufferedOutput();
        $migrations->setOutput($output);
        $needsUpdate = $migrations->execute('migrations:up-to-date', Module::ID);
        if ($needsUpdate) {
            $migrations->execute('migrations:migrate', Module::ID);
        }
    }

    private static function addPayment(): void
    {
        $queryBuilder = self::getQueryBuilder();
        $paymentExists = $queryBuilder
            ->select('oxid')
            ->from('oxpayments')
            ->where('oxid = :paymentId')
            ->setParameter('paymentId', Module::ID)
            ->execute()
            ->fetchOne();

        if ($paymentExists) {
            return;
        }

        $queryBuilder->insert('oxpayments')
            ->values([
                'OXID' => ':oxid',
                'OXACTIVE' => ':oxactive',
                'OXDESC' => ':oxdesc',
                'OXADDSUM' => ':oxaddsum',
                'OXADDSUMTYPE' => ':oxaddsumtype',
                'OXFROMBONI' => ':oxfromboni',
                'OXFROMAMOUNT' => ':oxfromamount',
                'OXTOAMOUNT' => ':oxtoamount',
                'OXVALDESC' => ':oxvaldesc',
                'OXCHECKED' => ':oxchecked',
                'OXDESC_1' => ':oxdesc1',
                'OXVALDESC_1' => ':oxvaldesc1',
                'OXDESC_2' => ':oxdesc2',
                'OXVALDESC_2' => ':oxvaldesc2',
                'OXDESC_3' => ':oxdesc3',
                'OXVALDESC_3' => ':oxvaldesc3',
                'OXLONGDESC' => ':oxlongdesc',
                'OXLONGDESC_1' => ':oxlongdesc1',
                'OXLONGDESC_2' => ':oxlongdesc2',
                'OXLONGDESC_3' => ':oxlongdesc3',
                'OXSORT' => ':oxsort',
            ])
            ->setParameters([
                'oxid' => Module::ID,
                'oxactive' => 0,
                'oxdesc' => 'Nexi Checkout',
                'oxaddsum' => 0,
                'oxaddsumtype' => 'abs',
                'oxfromboni' => 0,
                'oxfromamount' => 0,
                'oxtoamount' => 1000000,
                'oxvaldesc' => '',
                'oxchecked' => 0,
                'oxdesc1' => 'Nexi Checkout',
                'oxvaldesc1' => '',
                'oxdesc2' => '',
                'oxvaldesc2' => '',
                'oxdesc3' => '',
                'oxvaldesc3' => '',
                'oxlongdesc' => '',
                'oxlongdesc1' => '',
                'oxlongdesc2' => '',
                'oxlongdesc3' => '',
                'oxsort' => 0,
            ])
            ->execute();
    }

    private static function getQueryBuilder(): QueryBuilder
    {
        return ContainerFactory::getInstance()
            ->getContainer()
            ->get(QueryBuilderFactoryInterface::class)
            ->create();
    }
}
