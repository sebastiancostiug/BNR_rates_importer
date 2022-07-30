<?php
/**
 *
 * @package     BNR Currency rates importer
 *
 * @subpackage  GUI
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


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BNR Currency rates importer</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="scripts.js"></script>
</head>
<body>
    <div class="mt-2 container">
        <form action="<?php $_PHP_SELF ?>" method="POST">
            <h5 class="alert alert-info">Selecteaza perioada importului:</h5>
            <div class="input-group">
                    <div class="input-group-prepend">
                        <label for="xlsFile" class="input-group-text">Perioada</label>
                    </div>
                    <select class="form-control" type="select" name="period" id="period"  onchange="showYearInput('year-input', this)">
                        <option value="day" <?php echo  (isset($_POST['period']) && $_POST['period'] == 'day') ? 'selected ' : ''?>>Azi</option>
                        <option value="10days" <?php echo  (isset($_POST['period']) && $_POST['period'] == '10days') ? 'selected ' : ''?>>Ultimele 10 zile</option>
                        <option value="year" <?php echo  (isset($_POST['period']) && $_POST['period'] == 'year') ? 'selected ' : ''?>>Un an</option>
                    </select>
                    <input id="year-input" class="mx-5 form-control d-none" type="text" name="year" placeholder="Specifica anul: YYYY" value="<?php echo (isset($_POST['year']) && !empty($_POST['year'])) ? $_POST['year'] : ''?>"/>
                <button class="btn btn-info px-5" type="submit" value="Submit" name="submit">Trimite</button>
            </div>
        </form>
        <hr/>
<?php
$rates = [];

if (isset($_POST['period']) && !empty($_POST['period'])) {
    switch ($_POST['period']) {
        case 'day':
            $rates  = $helper->fetchDaily();
            break;
        case '10days':
            $rates  = $helper->fetchLastTenDays();
            break;
        case 'year':
            if (isset($_POST['year']) && !empty($_POST['year'])) {
                $rates  = $helper->fetchYearly($_POST['year']);
            } else {
                echo '<p class="alert alert-danger lead">Anul TREBUIE sa fie specificat in formatul YYYY (EX: 2022)!</p>';
            }
            break;
        default:
            break;
    }
    if (count($rates) > 0) {
        echo '<form method="post">';
        echo '<button class="btn btn-lg btn-danger w-100" type="submit" name="Update" value="' . htmlentities(serialize($rates)) . '">Importa noile rate in baza de date locala</button>';
        echo '</form>';
        echo '<hr/>';
    // Data table
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped" style="table-layout: fixed;">';
        echo '<thead>';
        echo '<tr class="d-flex">';
        echo '<th scope="col" class="col-md-3 table-info">Valuta</th>';
        echo '<th scope="col" class="col-md-3 table-info">Rata</th>';
        echo '<th scope="col" class="col-md-3 table-info">Multiplicator</th>';
        echo '<th scope="col" class="col-md-3 table-info">Data</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="col-12">';
        foreach ($rates as $rate) {
            echo '<tr class="d-flex flex-row flex-wrap">';
            foreach ($rate as $cell) {
                echo '<td class="col-md-3">' . $cell . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}

if (isset($_POST['Update'])) {
    $rates = unserialize($_POST['Update']);
}
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
?>
    </div>
</body>
