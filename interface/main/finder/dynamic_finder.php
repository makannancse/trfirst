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
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;

if (isset($_GET['ehr'])) {
    $dummy_data = [
        ['id' => '6' ,'fname' => 'admin', 'lname' => 'Test','DOB' => '1999-04-07'],
        ['id' => '1' ,'fname' => 'John', 'lname' => 'Aadhi','DOB' => '1999-04-07'],
        ['id' => '2' ,'fname' => 'Jane', 'lname' => 'Doe','DOB' => '1997-01-17'],
        ['id' => '3' , 'fname' => 'Tom', 'lname' => 'Smith','DOB' => '1999-02-07'],
        ['id' => '4' , 'fname' => 'Alice', 'lname' => 'Johnson','DOB' => '1996-06-07'],
        ['id' => '5' , 'fname' => 'Bob', 'lname' => 'Davis','DOB' => '1999-04-07']
    ];
        foreach ($dummy_data as $data) {
        $pid = $data['id'];
        $fname = $data['fname'];
        $lname = $data['lname'];
        $dob = $data['DOB'];
        $query = "INSERT INTO `patient_data` (
            `id`,`uuid`, `title`,`fname`, `lname`,`DOB`,`street`,`pubpid`,pid
        ) VALUES (
            $pid,NULL,'Mr', '$fname', '$lname','$dob','123,CALIFORNIA',$pid,$pid
        )";
        sqlQuery($query);  
    }
    
    exit;
}




$login_user_id=$_SESSION['authUserID'] ? $_SESSION['authUserID'] : 0;
$admin_query1=sqlQuery("SELECT users.id,users.username,gacl_aro_groups.name FROM users join gacl_aro on gacl_aro.value=users.username join gacl_groups_aro_map on gacl_groups_aro_map.aro_id=gacl_aro.id join gacl_aro_groups on gacl_aro_groups.id=gacl_groups_aro_map.group_id WHERE users.id=".$login_user_id."");
$role=isset($admin_query1['name'])?$admin_query1['name']:'';
$uspfx = 'patient_finder.'; //substr(__FILE__, strlen($webserver_root)) . '.';
$patient_finder_exact_search = prevSetting($uspfx, 'patient_finder_exact_search', 'patient_finder_exact_search', ' ');

$popup = empty($_REQUEST['popup']) ? 0 : 1;
$searchAny = empty($_GET['search_any']) ? "" : $_GET['search_any'];
$patient_type=isset($_GET['patient_type'])?$_GET['patient_type']:'active';
unset($_GET['search_any']);
// Generate some code based on the list of columns.
//
$colcount = 0;
$header0 = "";
$header = "";
$coljson = "";
$orderjson = "";
$res = sqlStatement("SELECT option_id, title, toggle_setting_1 FROM list_options WHERE " .
    "list_id = 'ptlistcols' AND activity = 1 ORDER BY seq, title");
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
$loading = "<div class='spinner-border' role='status'><span class='sr-only'>" . xlt("Loading") . "...</span></div>";
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['datatables', 'datatables-colreorder', 'datatables-dt', 'datatables-bs']); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title><?php echo xlt("Patient Finder"); ?></title>
<style>
    .custom-popup {
        width: 350px; /* Set the desired width */
        height: 290px; /* Adjust height automatically */
      
    }
    /* Finder Processing style */
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
    .active_patient{
        color: black;
        text-decoration: none;
    }
    .active_list{
        width: 85%;
        padding: 20px;
        height: 57px;
        font-weight: 500;
    }
</style>
<script>
    var uspfx = '<?php echo attr($uspfx); ?>';

    $(function () {
        // Initializing the DataTable.
        //
        let patient_type='<?php echo $patient_type;?>';
        let serverUrl = "dynamic_finder_ajax.php?patient_type="+patient_type+"&";
        let srcAny = <?php echo js_url($searchAny); ?>;
        if (srcAny) {
            serverUrl += "search_any=" + srcAny;
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
    $arrOeUiSettings = array(
    'heading_title' => xl('Patient Finder'),
    'include_patient_name' => false,
    'expandable' => true,
    'expandable_files' => array('dynamic_finder_xpd'),//all file names need suffix _xpd
    'action' => "search",//conceal, reveal, search, reset, link or back
    'action_title' => "",//only for action link, leave empty for conceal, reveal, search
    'action_href' => "",//only for actions - reset, link or back
    'show_help_icon' => false,
    'help_file_name' => ""
    );
    $oemr_ui = new OemrUI($arrOeUiSettings);
    $sql1="SELECT COUNT(id) AS count FROM patient_data WHERE patient_status='active'";
    if($role!='Administrators')
    {
        $sql1.=" AND providerID=".$login_user_id."";
    }
    $active_pat_count_arr=sqlQuery($sql1);
    $active_pat_count=isset($active_pat_count_arr['count'])?$active_pat_count_arr['count']:0;

    $sql2="SELECT COUNT(id) AS count FROM patient_data WHERE patient_status='inactive'";
    if($role!='Administrators')
    {
        $sql2.=" AND providerID=".$login_user_id."";
    }    
    $inactive_pat_count_arr=sqlQuery($sql2);
    $inactive_pat_count=isset($inactive_pat_count_arr['count'])?$inactive_pat_count_arr['count']:0;

    ?>
</head>
<body>
    <div id="container_div" class="<?php echo attr($oemr_ui->oeContainer()); ?> mt-3">
         <div class="w-100">
            <div class='row'>
                <div>
                <?php echo $oemr_ui->pageHeading() . "\r\n"; ?>   
                </div>
                <div class="row active_list bg-light">
                    <div><a href="?patient_type=active" class="active_patient" style='<?php if($patient_type=='active'){echo 'color:blue !important;';} ?>'><i class="fa fa-exclamation-circle" aria-hidden="true"></i>&nbsp;Active (<?php echo $active_pat_count;?>)</a></div>
                    <div><a href="?patient_type=inactive" class="ml-3 active_patient" style='<?php if($patient_type=='inactive'){echo 'color:blue !important;';} ?>'><i class="fa fa-exclamation-circle" aria-hidden="true"></i>&nbsp;Inactive (<?php echo $inactive_pat_count;?>)</a></div>
                </div>
            </div>
                    
            <?php if (AclMain::aclCheckCore('patients', 'demo', '', array('write','addonly'))) { ?>
                <button id="create_patient_btn1" class="btn btn-primary btn-add" onclick="top.restoreSession();top.RTop.location = '<?php echo $web_root ?>/interface/new/new.php'"><?php echo xlt('Add New Patient'); ?></button>
                <button  class="btn btn-primary" id="connect"><?php echo xlt('Connect EHR'); ?></button>
            <?php } ?>
            
            <div>
                <div id="dynamic"><!-- TBD: id seems unused, is this div required? -->
                    <!-- Class "display" is defined in demo_table.css -->
                    <div class="table-responsive">
                        <table class="table" class="border-0 display" id="pt_table">
                            <thead class="thead-dark">
                                <tr id="advanced_search" class="hideaway d-none">
                                    <?php echo $header0; ?>
                                </tr>
                                <tr class="">
                                    <?php echo $header; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <!-- Class "dataTables_empty" is defined in jquery.dataTables.css -->
                                    <td class="dataTables_empty" colspan="<?php echo attr($colcount); ?>">...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
          </div>
        </div>
        <!-- form used to open a new top level window when a patient row is clicked -->
        <form name='fnew' method='post' target='_blank' action='../main_screen.php?auth=login&site=<?php echo attr_url($_SESSION['site_id']); ?>'>
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
            <input type='hidden' name='patientID' value='0'/>
        </form>
    </div> <!--End of Container div-->
    <?php $oemr_ui->oeBelowContainerDiv();?>

    <script>
        $(function () {
            $("#exp_cont_icon").click(function () {
                $("#pt_table").removeAttr("style");
            });
        });

        $(window).on("resize", function() { //portrait vs landscape
           $("#pt_table").removeAttr("style");
        });
    </script>
    <script>
      $(function() {
        $("#pt_table_filter").addClass("d-md-initial");
        $("#pt_table_length").addClass("d-md-initial");
        $("#show_hide").addClass("d-md-initial");
        $("#search_hide").addClass("d-md-initial");
        $("#pt_table_length").addClass("d-none");
        $("#show_hide").addClass("d-none");
        $("#search_hide").addClass("d-none");
      });
    </script>

    <script>
        document.addEventListener('touchstart', {});
    </script>

    <script>
        $(function() {
            $('div.dataTables_filter input').focus();
        });
    </script>
  <script>
$(document).ready(function() {
    $('#connect').click(function() {
        Swal.fire({
            text: 'Five patient records found',
            icon: 'info',
            confirmButtonText: 'OK',
            customClass: {
            confirmButton: 'btn btn-primary' 
            },
            allowOutsideClick: false, 
            allowEscapeKey: false,   
        }).then(() => {
            $.ajax({
                url: 'dynamic_finder.php?ehr',
                type: 'POST',
                success: function(response) {
                    // Show SweetAlert2 popup for success
                    Swal.fire({
                        title: 'Success',
                        text: 'Patient data inserted successfully',
                        icon: 'success',
                        customClass: {
                        confirmButton: 'btn btn-primary' 
                        },
                         allowOutsideClick: false, 
                         allowEscapeKey: false, 
                    }).then(() => {
                        location.reload(); // Reload the page after success
                    });
                },
                error: function(xhr, status, error) {
                    // Show SweetAlert2 popup for error
                    Swal.fire({
                        title: 'Error',
                        text: 'Error inserting patient data: ' + error,
                        icon: 'error',
                        customClass: {
                        confirmButton: 'btn btn-primary' 
                        }
                    });
                }
            });
        });
    });
});


</script>

</body>
</html>
