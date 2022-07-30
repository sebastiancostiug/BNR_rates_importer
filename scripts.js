/**
 * @package     BNR Currency rates importer
 *
 * @subpackage  <JavaScript file>
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2022 Sebastian Costiug, <sebastian@overbyte.dev>
 * @license     https://opensource.org/licenses/GPL-3.0
 *
 * @description The purpose of this file is to instantiate custom JavaScript functions.
 *
 * @since       2022.07.30
 *
 */

function showYearInput() {
    let select = document.getElementById('period');
    let textInput = document.getElementById('year-input');
    if (select.options[select.selectedIndex].value == 'year') {
        textInput.classList.remove('d-none');
        textInput.classList.add('d-block');
    } else {
        textInput.classList.remove('d-block');
        textInput.classList.add('d-none');
    }
}
document.addEventListener('DOMContentLoaded', function () {
    showYearInput();
});
