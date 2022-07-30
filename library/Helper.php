<?php
/**
 *
 * @package     BNR Currency rates importer
 *
 * @subpackage  Helper functions
 *
 * @author      <Sebastian Costiug, sebastian@overbyte.dev>
 * @copyright   2022 Sebastian Costiug, <sebastian@overbyte.dev>
 * @license     https://opensource.org/licenses/GPL-3.0
 *
 * @category    helpers
 *
 * @since       2022.07.26
 *
 */

namespace bnrapi;

use Jgauthi\Component\Database\DB;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Db.php';

/**
  * Helpers
  */
class Helper
{
    /**
     * @var Jgauthi\Component\Database\DB $db Database connection instance
     */
    public $db;

    /**
     * @var array $currencies Currencies of interrest to be saved in DB
     */
    public $currencies;

    /**
     * Class constructor
     *
     * @param string $dbHost     DbHost
     * @param string $dbName     DbName
     * @param string $dbUserName DbUserName
     * @param string $dbPassword DbPassword
     * @param string $currencies Currencies
     *
     * @return void
     */
    public function __construct($dbHost, $dbName, $dbUserName, $dbPassword, $currencies)
    {
        $this->db         = new DB($dbHost, $dbName, $dbUserName, $dbPassword);
        $this->currencies = $currencies;
    }

    /**
     * Fetches daily exchange rates from BNR using the fetchXML() method and writes them in the db
     *
     * @return array|null
     */
    public function fetchDaily()
    {
        $xml = self::__fetchXml('daily');
        if ($xml) {
            $dailyInfo = $xml[0]->getElementsByTagName('Rate');
            $result    = [];
            foreach ($dailyInfo as $rate) {
                if (in_array(strtoupper($rate->attributes[0]->value), $this->currencies)) {
                    $result[$rate->attributes[0]->value] = [
                    'code'       => $rate->attributes[0]->value,
                    'rate'       => $rate->nodeValue,
                    'multiplier' => (isset($rate->attributes[1])  && !empty($rate->attributes[1])) ? $rate->attributes[1]->value : 1,
                    'date'       => $xml[0]->attributes[0]->value,
                    ];
                }
            }
            krsort($result);

            return $result;
        }
        return null;
    }

    /**
     * Fetches last 10 days of exchange rates from BNR using
     * the __fetchXml() method checks for any that already
     * exist in the db and writes the ones missing in the db
     *
     * @return array|null
     */
    public function fetchLastTenDays()
    {
        $xml           = self::__fetchXml('tendays');

        $result = [];

        if ($xml) {
            foreach ($xml as $day) {
                $dailyInfo = $day->getElementsByTagName('Rate');
                foreach ($dailyInfo as $rate) {
                    if (in_array(strtoupper($rate->attributes[0]->value), $this->currencies)) {
                        $result[$day->attributes[0]->value . '_' . $rate->attributes[0]->value] = [
                        'code'       => $rate->attributes[0]->value,
                        'rate'       => floatval($rate->nodeValue),
                        'multiplier' => (isset($rate->attributes[1])  && !empty($rate->attributes[1])) ? intval($rate->attributes[1]->value) : 1,
                        'date'       => $day->attributes[0]->value,
                        ];
                    }
                }
            }
            krsort($result);

            return $result;
        }
        return null;
    }

    /**
     * Delets the exchange rates found in the db for the specified year using __deleteEntries()
     * Fetches new yearly exchange rates from BNR for the specified year using the __fetchXml() and writes them in the db
     * @param string $year Year
     *
     * @return array|null
     **/
    public function fetchYearly($year = null)
    {
        $truncate = false;
        if (!$year) {
            $year     = date('Y');
            $truncate = true;
        }

        $xml = self::__fetchXml('yearly', $year);
        $result = [];
        if ($xml) {
            if (self::__deleteEntries(($truncate ? null : $year))) {
                foreach ($xml as $day) {
                    $dailyInfo = $day->getElementsByTagName('Rate');
                    foreach ($dailyInfo as $rate) {
                        if (in_array(strtoupper($rate->attributes[0]->value), $this->currencies)) {
                            $result[$day->attributes[0]->value . '_' . $rate->attributes[0]->value] = [
                            'code'       => $rate->attributes[0]->value,
                            'rate'       => floatval($rate->nodeValue),
                            'multiplier' => $rate->attributes[1] ? intval($rate->attributes[1]->value) : 1,
                            'date'       => $day->attributes[0]->value,
                            ];
                        }
                    }
                }
            }
            krsort($result);

            return $result;
        }
        return null;
    }

    /**
     * Get official exchange rates from BNR and parse them into usable array
     *
     * @param string $period Period
     * @param string $year   Year
     *
     * @return DOMNodeList|null — A new DOMNodeList object containing all the matched elements
     **/
    private static function __fetchXml($period, $year = null)
    {
        $xml = new \DOMDocument();

        if ($period === 'daily') {
            $url = 'https://bnr.ro/nbrfxrates.xml';
        } elseif ($period === 'tendays') {
            $url = 'https://bnr.ro/nbrfxrates10days.xml';
        } else {
            $url = "https://bnr.ro/files/xml/years/nbrfxrates$year.xml";
        }

        $list = ($xml->load($url) !== false) ? $xml->getElementsByTagName('Cube') : null;

        return $list;
    }

    /**
     * Delete all entries in the database that match the specified year or truncates table if year is null
     *
     * @param string $year Year
     *
     * @return boolean
     */
    private function __deleteEntries($year = null)
    {
        try {
            if ($year) {
                return $this->db->query('DELETE FROM `exchange` WHERE YEAR(`date`) = :year', [
                'year' => $year,
                ]);
            } else {
                return $this->db->query('TRUNCATE TABLE `exchange`', [
                'year' => $year,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        } catch (\Error $e) {
            return false;
        }
    }

    /**
     * Delets the exchange rates found in the db for the specified year using __deleteEntries()
     * Fetches new yearly exchange rates from BNR for the specified year using the __fetchXml() and writes them in the db
     *
     * @param string $code Code
     * @param string $date Date
     *
     * @return array|null
     **/
    public function getRate($code, $date)
    {
        $row = [$code, $date];
        $row = $this->db->row('SELECT * FROM `exchange` WHERE `code` = :code AND `date` = :date', [
            'code'       => $code,
            'date'       => $date,
        ]);

        return $row;
    }

    /**
     * Delets the exchange rates found in the db for the specified year using __deleteEntries()
     * Fetches new yearly exchange rates from BNR for the specified year using the __fetchXml() and writes them in the db
     *
     * @param string $rate Rate
     *
     * @return array|null
     */
    public function insertRate($rate)
    {
        return $this->db->query('INSERT INTO `exchange` (`code`, `rate`, `multiplier`, `date`) VALUES (:code, :rate, :multiplier, :date)', [
            'code'       => $rate['code'],
            'rate'       => $rate['rate'],
            'multiplier' => $rate['multiplier'],
            'date'       => $rate['date'],
        ]);
    }

    /**
     * Delets the exchange rates found in the db for the specified year using __deleteEntries()
     * Fetches new yearly exchange rates from BNR for the specified year using the __fetchXml() and writes them in the db
     *
     * @param string $rate Rate
     * @param string $id   ID
     *
     * @return array|null
     **/
    public function updateRate($rate, $id)
    {
        return $this->db->query('UPDATE `exchange` SET `code` = :code, `rate` = :rate, `multiplier` = :multiplier, `date` = :date WHERE `id` = :id', [
            'id'         => $id,
            'code'       => $rate['code'],
            'rate'       => $rate['rate'],
            'multiplier' => $rate['multiplier'],
            'date'       => $rate['date'],
        ]);
    }
}
