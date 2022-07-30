<?php
/**
 *
 * @package     BNR Currency rates importer
 *
 * @subpackage  CRON script for daily imports
 *
 * @author      <Sebastian Costiug, sebastian@overbyte.dev>
 * @copyright   2022 Sebastian Costiug, <sebastian@overbyte.dev>
 * @license     https://opensource.org/licenses/GPL-3.0
 *
 * @category    index
 *
 * @since       2022.07.26
 *
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use bnrapi\Helper;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'Helper.php';
$helper = new Helper(DB_HOSTNAME, DB_DATABASE, DB_USERNAME, DB_PASSWORD, USED_CURRENCIES);

$rates  = $helper->fetchDaily();
if (count($rates) > 0) {
    foreach ($rates as $rate) {
        $dbRate = $helper->getRate($rate['code'], $rate['date']);
        if ($dbRate) {
            if ($dbRate['rate'] != $rate['rate']) {
                $updateDbRate = $helper->updateRate($rate, $dbRate['id']);
            }
        } else {
            $insertDbRate = $helper->insertRate($rate);
        }
    }
}
