<?php

/**
 * dynamic_finder.php
 *
 * Sponsored by David Eschelbacher, MD
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2012-2016 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2019 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__FILE__) . "/../../globals.php");
require_once "$srcdir/user.inc.php";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\Events\UserInterface\PageHeadingRenderEvent;
use OpenEMR\Menu\BaseMenuItem;
use OpenEMR\OeUI\OemrUI;
use Symfony\Component\EventDispatcher\EventDispatcher;
use OpenEMR\Services\PatientService;


if (isset($_GET['ehr'])) {
    $dummy_data = [
        ['id' => '1', 'fname' => 'John', 'lname' => 'Aadhi', 'DOB' => '1999-04-07', 'eligible' => '1'],
        ['id' => '2', 'fname' => 'Jane', 'lname' => 'Doe', 'DOB' => '1997-01-17', 'eligible' => '1'],
        ['id' => '3', 'fname' => 'Tom', 'lname' => 'Smith', 'DOB' => '1999-02-07', 'eligible' => '0'],
        ['id' => '4', 'fname' => 'Alice', 'lname' => 'Johnson', 'DOB' => '1996-06-07', 'eligible' => '0'],
        ['id' => '5', 'fname' => 'Bob', 'lname' => 'Davis', 'DOB' => '1999-04-07', 'eligible' => '0'],
        ['id' => '6', 'fname' => 'Admin', 'lname' => 'Test', 'DOB' => '1999-04-07', 'eligible' => '1'],
        ['id' => '7', 'fname' => 'Mary', 'lname' => 'Brown', 'DOB' => '2000-07-01', 'eligible' => '1'],
        ['id' => '8', 'fname' => 'Chris', 'lname' => 'White', 'DOB' => '1995-09-12', 'eligible' => '1'],
        ['id' => '9', 'fname' => 'Sarah', 'lname' => 'Davis', 'DOB' => '1998-02-25', 'eligible' => '0'],
        ['id' => '10', 'fname' => 'Michael', 'lname' => 'Wilson', 'DOB' => '1997-08-15', 'eligible' => '1'],
        ['id' => '11', 'fname' => 'David', 'lname' => 'Miller', 'DOB' => '1996-10-10', 'eligible' => '0'],
        ['id' => '12', 'fname' => 'Emma', 'lname' => 'Garcia', 'DOB' => '1999-05-20', 'eligible' => '1'],
        ['id' => '13', 'fname' => 'Daniel', 'lname' => 'Martinez', 'DOB' => '1997-04-18', 'eligible' => '0'],
        ['id' => '14', 'fname' => 'Olivia', 'lname' => 'Hernandez', 'DOB' => '1998-11-05', 'eligible' => '1'],
        ['id' => '15', 'fname' => 'Lucas', 'lname' => 'Lopez', 'DOB' => '2000-12-01', 'eligible' => '1'],
        ['id' => '16', 'fname' => 'Sophia', 'lname' => 'Gonzalez', 'DOB' => '1996-03-12', 'eligible' => '0'],
        ['id' => '17', 'fname' => 'James', 'lname' => 'Perez', 'DOB' => '1995-07-24', 'eligible' => '1'],
        ['id' => '18', 'fname' => 'Isabella', 'lname' => 'Jackson', 'DOB' => '1999-02-17', 'eligible' => '1'],
        ['id' => '19', 'fname' => 'Benjamin', 'lname' => 'Sanchez', 'DOB' => '2001-09-09', 'eligible' => '0'],
        ['id' => '20', 'fname' => 'Charlotte', 'lname' => 'Clark', 'DOB' => '1998-12-22', 'eligible' => '0']
    ];
    

    // Assuming sqlQuery is a function to execute the query
    foreach ($dummy_data as $data) {
        $pid = $data['id'];
        $fname = $data['fname'];
        $lname = $data['lname'];
        $dob = $data['DOB'];
        $eligible = $data['eligible'];

        // Correct the SQL query and avoid syntax issues
        $query = "INSERT INTO `patient_data` (
                    `id`, `uuid`, `title`, `fname`, `lname`, `DOB`, `street`, `pubpid`, `pid`, `eligible`
                  ) VALUES (
                    '$pid', NULL, 'Mr', '$fname', '$lname', '$dob', '123,CALIFORNIA', '$pid', '$pid', '$eligible'
                  )";

        // Run the query
        sqlQuery($query);  
    }

    exit;
}
if(isset($_GET['eligible'])){

    $eligiblePatients = [];
    $result = sqlStatement("SELECT id, fname, lname, DOB FROM patient_data WHERE eligible = '1'");

    // Iterate through the result set
    while ($row = sqlFetchArray($result)) {
        $eligiblePatients[] = [
            'id' => $row['id'],
            'fname' => $row['fname'],
            'lname' => $row['lname'],
            'DOB' => $row['DOB']
        ];
    }

    // Return eligible patients as JSON
    header('Content-Type: application/json');
    echo json_encode($eligiblePatients);
    exit;

}



$uspfx = 'patient_finder.'; //substr(__FILE__, strlen($webserver_root)) . '.';
$patient_finder_exact_search = prevSetting($uspfx, 'patient_finder_exact_search', 'patient_finder_exact_search', ' ');

$popup = empty($_REQUEST['popup']) ? 0 : 1;
$searchAny = empty($_GET['search_any']) ? "" : $_GET['search_any'];
unset($_GET['search_any']);
// Generate some code based on the list of columns.
//
$colcount = 0;
$header0 = "";
$header = "";
$coljson = "";
$orderjson = "";
$res = sqlStatement("SELECT option_id, title, toggle_setting_1 FROM list_options WHERE list_id = 'ptlistcols' AND activity = 1 ORDER BY seq, title");
$sort_dir_map = generate_list_map('Sort_Direction');
while ($row = sqlFetchArray($res)) {
    $colname = $row['option_id'];
    $colorder = $sort_dir_map[$row['toggle_setting_1']]; // Get the title 'asc' or 'desc' using the value
    $title = xl_list_label($row['title']);
    $title1 = ($title == xl('Full Name')) ? xl('Name') : $title;
    $header .= "   <th>";
    $header .= text($title);
    $header .= "</th>\n";
    $header0 .= "   <td ><input type='text' size='20' ";
    $header0 .= "value='' class='form-control search_init' placeholder='" . xla("Search by") . " " . $title1 . "'/></td>\n";
    if ($coljson) {
        $coljson .= ", ";
    }

    $coljson .= "{\"sName\": \"" . addcslashes($colname, "\t\r\n\"\\") . "\"";
    if ($title1 == xl('Name')) {
        $coljson .= ", \"mRender\": wrapInLink";
    }
    $coljson .= "}";
    if ($orderjson) {
        $orderjson .= ", ";
    }
    $orderjson .= "[\"$colcount\", \"" . addcslashes($colorder, "\t\r\n\"\\") . "\"]";
    ++$colcount;
}
$loading = "";
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['datatables', 'datatables-colreorder', 'datatables-dt', 'datatables-bs']); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title><?php echo xlt("Patient Finder"); ?></title>
<style>
    /* Finder Processing style */
        .btn-connect:before {
        content: " ➠ ";
        font-size: 20px !important;
        line-height: 20px;
       }
       .elg-patient:before {
        content: " ➠ ";
        font-size: 20px !important;
        line-height: 20px;
       }
    div.dataTables_wrapper div.dataTables_processing {
        width: auto;
        margin: 0;
        color: var(--danger);
        transform: translateX(-50%);
    }
    .card {
        border: 0;
        border-radius: 0;
    }

    @media screen and (max-width: 640px) {
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            float: inherit;
            text-align: justify;
        }
    }

    /* Color Overrides for jQuery-DT */
    table.dataTable thead th,
    table.dataTable thead td {
        border-bottom: 1px solid var(--gray900) !important;
    }

    table.dataTable tfoot th,
    table.dataTable tfoot td {
        border-top: 1px solid var(--gray900) !important;
    }

    table.dataTable tbody tr {
        background-color: var(--white) !important;
        cursor: pointer;
    }

    table.dataTable.row-border tbody th,
    table.dataTable.row-border tbody td,
    table.dataTable.display tbody th,
    table.dataTable.display tbody td {
        border-top: 1px solid var(--gray300) !important;
    }

    table.dataTable.cell-border tbody th,
    table.dataTable.cell-border tbody td {
        border-top: 1px solid var(--gray300) !important;
        border-right: 1px solid var(--gray300) !important;
    }

    table.dataTable.cell-border tbody tr th:first-child,
    table.dataTable.cell-border tbody tr td:first-child {
        border-left: 1px solid var(--gray300) !important;
    }

    table.dataTable.stripe tbody tr.odd,
    table.dataTable.display tbody tr.odd {
        background-color: var(--light) !important;
    }

    table.dataTable.hover tbody tr:hover,
    table.dataTable.display tbody tr:hover {
        background-color: var(--light) !important;
    }

    table.dataTable.order-column tbody tr>.sorting_1,
    table.dataTable.order-column tbody tr>.sorting_2,
    table.dataTable.order-column tbody tr>.sorting_3,
    table.dataTable.display tbody tr>.sorting_1,
    table.dataTable.display tbody tr>.sorting_2,
    table.dataTable.display tbody tr>.sorting_3 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr.odd>.sorting_1,
    table.dataTable.order-column.stripe tbody tr.odd>.sorting_1 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr.odd>.sorting_2,
    table.dataTable.order-column.stripe tbody tr.odd>.sorting_2 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr.odd>.sorting_3,
    table.dataTable.order-column.stripe tbody tr.odd>.sorting_3 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr.even>.sorting_1,
    table.dataTable.order-column.stripe tbody tr.even>.sorting_1 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr.even>.sorting_2,
    table.dataTable.order-column.stripe tbody tr.even>.sorting_2 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr.even>.sorting_3,
    table.dataTable.order-column.stripe tbody tr.even>.sorting_3 {
        background-color: var(--light) !important;
    }

    table.dataTable.display tbody tr:hover>.sorting_1,
    table.dataTable.order-column.hover tbody tr:hover>.sorting_1 {
        background-color: var(--gray200) !important;
    }

    table.dataTable.display tbody tr:hover>.sorting_2,
    table.dataTable.order-column.hover tbody tr:hover>.sorting_2 {
        background-color: var(--gray200) !important;
    }

    table.dataTable.display tbody tr:hover>.sorting_3,
    table.dataTable.order-column.hover tbody tr:hover>.sorting_3 {
        background-color: var(--gray200) !important;
    }

    table.dataTable.display tbody .odd:hover,
    table.dataTable.display tbody .even:hover {
        background-color: var(--gray200) !important;
    }

    table.dataTable.no-footer {
        border-bottom: 1px solid var(--gray900) !important;
    }

    .dataTables_wrapper .dataTables_processing {
        background-color: var(--white) !important;
        background: -webkit-gradient(linear, left top, right top, color-stop(0%, transparent), color-stop(25%, rgba(var(--black), 0.9)), color-stop(75%, rgba(var(--black), 0.9)), color-stop(100%, transparent)) !important;
        background: -webkit-linear-gradient(left, transparent 0%, rgba(var(--black), 0.9) 25%, rgba(var(--black), 0.9) 75%, transparent 100%) !important;
        background: -moz-linear-gradient(left, transparent 0%, rgba(var(--black), 0.9) 25%, rgba(var(--black), 0.9) 75%, transparent 100%) !important;
        background: -ms-linear-gradient(left, transparent 0%, rgba(var(--black), 0.9) 25%, rgba(var(--black), 0.9) 75%, transparent 100%) !important;
        background: -o-linear-gradient(left, transparent 0%, rgba(var(--black), 0.9) 25%, rgba(var(--black), 0.9) 75%, transparent 100%) !important;
        background: linear-gradient(to right, transparent 0%, rgba(var(--black), 0.9) 25%, rgba(var(--black), 0.9) 75%, transparent 100%) !important;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        color: var(--dark) !important;
    }

    div.dataTables_length select {
        width: 50px !important;
    }

    .dataTables_wrapper.no-footer .dataTables_scrollBody {
        border-bottom: 1px solid var(--gray900) !important;
    }

    /* Pagination button Overrides for jQuery-DT */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0 !important;
        margin: 0 !important;
        border: 0 !important;
    }

    /* Sort indicator Overrides for jQuery-DT */
    table thead .sorting::before,
    table thead .sorting_asc::before,
    table thead .sorting_asc::after,
    table thead .sorting_desc::before,
    table thead .sorting_desc::after,
    table thead .sorting::after {
        display: none !important;
    }
</style>
<script>
    var uspfx = '<?php echo attr($uspfx); ?>';

    $(function () {
        // Initializing the DataTable.
        //
        let serverUrl = "dynamic_finder_ajax.php";
        let srcAny = <?php echo js_url($searchAny); ?>;
        if (srcAny) {
            serverUrl += "?search_any=" + srcAny;
        }
        var oTable = $('#pt_table').dataTable({
            "processing": true,
            // next 2 lines invoke server side processing
            "serverSide": true,
            // NOTE kept the legacy command 'sAjaxSource' here for now since was unable to get
            // the new 'ajax' command to work.
            "sAjaxSource": serverUrl,
            "fnServerParams": function (aoData) {
                var searchType = $("#setting_search_type:checked").length > 0;
                aoData.push({"name": "searchType", "value": searchType});
            },
            // dom invokes ColReorderWithResize and allows inclusion of a custom div
            "dom": 'Rlfrt<"mytopdiv">ip',
            // These column names come over as $_GET['sColumns'], a comma-separated list of the names.
            // See: http://datatables.net/usage/columns and
            // http://datatables.net/release-datatables/extras/ColReorder/server_side.html
            "columns": [ <?php echo $coljson; ?> ],
            "order": [ <?php echo $orderjson; ?> ],
            "lengthMenu": [10, 25, 50, 100],
            "pageLength": <?php echo empty($GLOBALS['gbl_pt_list_page_size']) ? '10' : $GLOBALS['gbl_pt_list_page_size']; ?>,
            <?php // Bring in the translations ?>
            <?php $translationsDatatablesOverride = array('search' => (xla('Search all columns') . ':')); ?>
            <?php $translationsDatatablesOverride = array('processing' => $loading); ?>
            <?php require($GLOBALS['srcdir'] . '/js/xl/datatables-net.js.php'); ?>
        });


        <?php
        $checked = (!empty($GLOBALS['gbl_pt_list_new_window'])) ? 'checked' : '';
        ?>
        $("div.mytopdiv").html("<form name='myform'><div class='form-check form-check-inline'><label for='form_new_window' class='form-check-label' id='form_new_window_label'><input type='checkbox' class='form-check-input' id='form_new_window' name='form_new_window' value='1' <?php echo $checked; ?> /><?php echo xlt('Open in New Browser Tab'); ?></label></div><div class='form-check form-check-inline'><label for='setting_search_type' id='setting_search_type_label' class='form-check-label'><input type='checkbox' name='setting_search_type' class='form-check-input' id='setting_search_type' onchange='persistCriteria(this, event)' value='<?php echo attr($patient_finder_exact_search); ?>'<?php echo text($patient_finder_exact_search); ?>/><?php echo xlt('Search with exact method'); ?></label></div></form>");

        // This is to support column-specific search fields.
        // Borrowed from the multi_filter.html example.
        $("thead input").keyup(function () {
            // Filter on the column (the index) of this element
            oTable.fnFilter(this.value, $("thead input").index(this));
        });

        $('#pt_table').on('mouseenter', 'tbody tr', function() {
            $(this).find('a').css('text-decoration', 'underline');
        });
        $('#pt_table').on('mouseleave', 'tbody tr', function() {
            $(this).find('a').css('text-decoration', '');
        });
        // OnClick handler for the rows
        $('#pt_table').on('click', 'tbody tr', function () {
            // ID of a row element is pid_{value}
            var newpid = this.id.substring(4);
            // If the pid is invalid, then don't attempt to set
            // The row display for "No matching records found" has no valid ID, but is
            // otherwise clickable. (Matches this CSS selector).  This prevents an invalid
            // state for the PID to be set.
            if (newpid.length === 0) {
                return;
            }
            if (document.myform.form_new_window.checked) {
                openNewTopWindow(newpid);
            }
            else {
                top.restoreSession();
                top.RTop.location = "../../patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(newpid);
            }
        });
    });

    function wrapInLink(data, type, full) {
        if (type == 'display') {
            return '<a href="">' + data + "</a>";
        } else {
            return data;
        }
    }

    function openNewTopWindow(pid) {
        document.fnew.patientID.value = pid;
        top.restoreSession();
        document.fnew.submit();
    }

    function persistCriteria(el, e) {
        e.preventDefault();
        let target = uspfx + "patient_finder_exact_search";
        let val = el.checked ? ' checked' : ' ';
        top.restoreSession();
        $.post("../../../library/ajax/user_settings.php",
            {
                target: target,
                setting: val,
                csrf_token_form: "<?php echo attr(CsrfUtils::collectCsrfToken()); ?>"
            }
        );
    }

</script>
<?php
    /** @var EventDispatcher */
    $eventDispatcher = $GLOBALS['kernel']->getEventDispatcher();
    $arrOeUiSettings = array(
    'heading_title' => xl('Patient Finder'),
    'include_patient_name' => false,
    'expandable' => true,
    'expandable_files' => array('dynamic_finder_xpd'),//all file names need suffix _xpd
    'action' => "search",//conceal, reveal, search, reset, link or back
    'action_title' => "",//only for action link, leave empty for conceal, reveal, search
    'action_href' => "",//only for actions - reset, link or back
    'show_help_icon' => false,
    'help_file_name' => "",
    'page_id' => 'dynamic_finder',
    );
    $oemr_ui = new OemrUI($arrOeUiSettings);

    $eventDispatcher->addListener(PageHeadingRenderEvent::EVENT_PAGE_HEADING_RENDER, function ($event) {
        if ($event->getPageId() !== 'dynamic_finder') {
            return;
        }

        $event->setPrimaryMenuItem(new BaseMenuItem([
            'displayText' => xl('Add New Patient'),
            'linkClassList' => ['btn-add'],
            'id' => $GLOBALS['webroot'] . '/interface/new/new.php',
            'acl' => ['patients', 'demo', ['write', 'addonly']]
        ]));
        $event->setPrimaryMenuItem(new BaseMenuItem([
            'displayText' => xl('Connect EHR'),
            'linkClassList' => ['btn-connect'],
            'id' => '#',
            'acl' => ['patients', 'demo', ['write', 'addonly']]
        ]));
        $event->setPrimaryMenuItem(new BaseMenuItem([
            'displayText' => xl('Transmit Patient'),
            'linkClassList' => ['elg-patient'],
            'id' => '#',
            'acl' => ['patients', 'demo', ['write', 'addonly']]
        ]));
    });
    ?>
</head>
<body>

<?php

function rp()
{
    $sql = "SELECT option_id, title FROM list_options WHERE list_id = 'recent_patient_columns' AND activity = '1' ORDER BY seq ASC";
    $res = sqlStatement($sql);
    $headers = [];
    while ($row = sqlFetchArray($res)) {
        $headers[] = $row;
    }
    $patientService = new PatientService();
    $rp = $patientService->getRecentPatientList();
    return ['headers' => $headers, 'rp' => $rp];
}

$rp = rp();

$templateVars = [
    'oeContainer' => $oemr_ui->oeContainer(),
    'oeBelowContainerDiv' => $oemr_ui->oeBelowContainerDiv(),
    'pageHeading' => $oemr_ui->pageHeading(),
    'header0' => $header0,
    'header' => $header,
    'colcount' => $colcount,
    'headers' => $rp['headers'],
    'rp' => $rp['rp'],
];

$twig = new TwigContainer(null, $GLOBALS['kernel']);
$t = $twig->getTwig();
echo $t->render('patient_finder/finder.html.twig', $templateVars);

?>
</body>
<script>
$(document).ready(function() {
    $('.btn-connect').click(function() {
        Swal.fire({
            title: 'Patient records found',
            text: 'Please select the practices you want to proceed with:',
            icon: 'info',
            html: `
                <input type="checkbox" id="optionA" name="options" value="A">
                <label for="optionA">Practice A</label><br>
                <input type="checkbox" id="optionB" name="options" value="B">
                <label for="optionB">Practice B</label>
            `,
            showCancelButton: true,
            confirmButtonText: 'Proceed',
            cancelButtonText: 'Cancel',
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-secondary'
            },
            preConfirm: () => {
                // Get the selected options (checkboxes)
                const selectedOptions = [];
                $("input[name='options']:checked").each(function() {
                    selectedOptions.push($(this).val());
                });
                return { selectedOptions: selectedOptions };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const selectedOptions = result.value.selectedOptions;

                // Check if both Practice A and Practice B are selected
                if (selectedOptions.includes("A") && selectedOptions.includes("B")) {
                    Swal.fire({
                        title: 'Both Practices Selected',
                        text: 'Proceeding with both Practice A and Practice B...',
                        icon: 'info',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    }).then(() => {
                        // Perform Ajax for both Practice A and Practice B
                        $.ajax({
                            url: 'dynamic_finder.php?ehr',
                            type: 'POST',
                            success: function(response) {
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Patient data inserted successfully for both practices',
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                }).then(() => {
                                    location.reload(); // Reload the page after success
                                });
                            },
                            error: function(xhr, status, error) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Error inserting patient data for both practices: ' + error,
                                    icon: 'error',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        });
                    });
                }

                // Check if Practice A is selected
                else if (selectedOptions.includes("A")) {
                    Swal.fire({
                        title: 'Practice A Selected',
                        text: 'Proceeding with Practice A...',
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    }).then(() => {
                        // Perform Ajax for Practice A
                        $.ajax({
                            url: 'dynamic_finder.php?ehr',
                            type: 'POST',
                            success: function(response) {
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Patient data inserted successfully for Practice A',
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                }).then(() => {
                                    location.reload(); // Reload the page after success
                                });
                            },
                            error: function(xhr, status, error) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Error inserting patient data for Practice A: ' + error,
                                    icon: 'error',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        });
                    });
                }

                // Check if Practice B is selected
                else if (selectedOptions.includes("B")) {
                    Swal.fire({
                        title: 'Practice B Selected',
                        text: 'Proceeding with Practice B...',
                        icon: 'warning',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    }).then(() => {
                        // Perform Ajax for Practice B
                        $.ajax({
                            url: 'dynamic_finder.php?ehr',
                            type: 'POST',
                            success: function(response) {
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Patient data inserted successfully for Practice B',
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                }).then(() => {
                                    location.reload(); // Reload the page after success
                                });
                            },
                            error: function(xhr, status, error) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Error inserting patient data for Practice B: ' + error,
                                    icon: 'error',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        });
                    });
                }

                // If no options selected
                if (selectedOptions.length === 0) {
                    Swal.fire({
                        title: 'No Option Selected',
                        text: 'Please select at least one option to proceed.',
                        icon: 'info',
                        customClass: {
                            confirmButton: 'btn btn-primary'
                        }
                    });
                }
            } else {
                Swal.fire({
                    title: 'Action Canceled',
                    text: 'You canceled the action.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        });
    });
});



$(document).ready(function () {
    // Event for "Eligible Patient" button
    $('.elg-patient').click(function () {
        $.ajax({
            url: 'dynamic_finder.php?eligible', // Endpoint to fetch eligible patients
            type: 'GET',
            dataType: 'json', // Expecting JSON response
            success: function (response) {
                let patientTable = '<table class="table table-bordered"><thead><tr><th>Name</th><th>DOB</th><th>External ID</th></tr></thead><tbody>';
                response.forEach(function(patient) {
                    patientTable += `<tr><td>${patient.lname},${patient.fname}</td><td>${patient.DOB}</td><td>${patient.id}</td></tr>`;
                });
                patientTable += '</tbody></table>';

                // Show SweetAlert2 popup with the patient table
                Swal.fire({
                    title: 'Transmit Patients',
                    html: patientTable, // Display the table as HTML
                    confirmButtonText: 'Transmit',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'dynamic_finder.php?transmit', // Endpoint to handle transmission
                            type: 'POST',
                            success: function (response) {
                                // Show success message for transmission
                                Swal.fire({
                                    title: 'Success',
                                    text: 'Patient Data successfully transmitted to the transplant center!',
                                    icon: 'success',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            },
                            error: function (xhr, status, error) {
                                // Handle error during transmission
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Failed to transmit data: ' + error,
                                    icon: 'error',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        });
                    }
                });
            },
            error: function () {
                // Handle error case
                Swal.fire({
                    title: 'Error',
                    text: 'Unable to fetch eligible patients.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        });
    });
});

</script>
</html>
