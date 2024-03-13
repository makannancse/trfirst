<?php

/**
 * uploads
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Tony McCormick <tony@mi-squared.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Roberto Vasquez <robertogagliotta@gmail.com>
 * @author    Robert Down <robertdown@live.com>
 * @copyright Copyright (C) 2011 Tony McCormick <tony@mi-squared.com>
 * @copyright Copyright (C) 2011-2018 Brady Miller   <brady.g.miller@gmail.com>
 * @copyright Copyright (C) 2017 Roberto Vasquez <robertogagliotta@gmail.com>
 * @copyright Copyright (c) 2022-2023 Robert Down <robertdown@live.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE CNU General Public License 3
 *
 */

//Include required scripts/libraries
require_once("../globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\ListService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


if (isset($_SESSION['pid']) && !empty($_SESSION['pid'])) {
    $pid = $_SESSION['pid'];
} 
$patient_data =sqlQuery("SELECT * FROM patient_data where pid ='$pid'");

if(isset($_FILES['fileInput']['name'])){
    $filename = $_FILES['fileInput']['name'];
    $temp = $_FILES['fileInput']['tmp_name'];
    $newfilename = $temp;
    $excel_ecategory_Name = $newfilename;
    $category_spreadsheet = IOFactory::load($excel_ecategory_Name);
    $category_sheet = $category_spreadsheet->getActiveSheet();
    $data = [];

foreach ($category_sheet->getRowIterator() as $crow) {
    $crowData = [];
    $cellIterator = $crow->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $cellCount = 0;
    foreach ($cellIterator as $cell) {
        if ($cellCount >= 2) {
            break; //
        }
        $crowData[] = $cell->getValue();
        $cellCount++;
    }
    if (count($crowData) === 2) {
        $excel_category_data[] = $crowData;
    }
}
array_shift($excel_category_data);



$estGFRValue = null;

foreach ($excel_category_data as $item) {
    if ($item[0] === 'EST GFR Whole Blood') {
        $estGFRValue = $item[1];
        break; 
    }
}
$query =sqlQuery("SELECT * FROM form_vitals where pid ='$pid' order by `id` DESC");
$bmi = $query['BMI'];
$dob =$patient_data['DOB'];
$currentDate = new DateTime();
$dateOfBirth = new DateTime($dob);
$age = $currentDate->diff($dateOfBirth)->y;
if($estGFRValue == null){
    $modalContent = "EST GFR Whole Blood value not found in the Excel sheet";
}
else{
if ($estGFRValue <= 20 && $age <= 80 && $bmi <= 41) {
    $modalContent = "is Eligible";
} else {
    $modalContent = "is Not Eligible";
}
}
header('Content-Type: application/json');
echo json_encode(array('status' => $modalContent, 'uploadFile' => $filename)); 
exit();
}

?>

<html>
<head>
    <?php Header::setupHeader(); ?>
    <title><?php echo xlt('Uploads'); ?></title>
    <script>
    </script>
</head>
<body>
    <br>
   <h3 style='text-align:center;'>Uploads & Review</h3>
  
<div class="container">
<button type="button" id='upload' class="btn btn-primary btn-sm">Upload File</button>
<input type="file" id="fileInput"  name="fileInput" style="display: none;" accept=".xlsx, .xls">
<button type="button" class="btn btn-secondary btn-sm">Review</button>
  <br>
  <label id="uploadFile" style="font-size:bold;text-align:center;"></label>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 350px;margin-top:40px;">
    <div class="modal-content">
      <div class="modal-header">
        <h6 style='font-weight:bold;'>Eligibility Status</h6>
        <!-- Close button -->
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <h5 id='status' style='text-align:center;'><?php echo $patient_data['fname'];?>&nbsp;<span id="modalStatus"></span>!!</h5>
       
        <div id='refer'>
          <p id='refer'>Refer to the nearest center ?</p>
          <button name='yes' id='yescheckbox' class="btn btn-success check" value='yes'> Yes </button>
          <button name='no' id='nocheckbox' class="btn btn-danger check" value='no'> No</button>
        </div>

          <?php
          $sql = sqlQuery("SELECT * FROM `facility` where city !='' order by rand() limit 1");
          
?>
<div id ="near_loc" style = "display:none;text-align:center;font-weight:bold;margin-top:-15px;">
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
<br>
<i class = "fa fa-location"></i><?php echo $sql['name']; ?>
<br><?php echo $sql['street'].' ,'.$sql['city'].' ,'.$sql['state'].' ,'.$sql['country_code']; ?>
<br>
<button id='ok_button' class='btn btn-primary'>Okay</button>
</div>

<div class="d-flex justify-content-center">
  <div id="loader" style="display:none;" class="spinner-border" role="status">
    <span class="sr-only">Loading...</span>
  </div>
</div>

<div id="success_message" style="display:none;text-align:center;font-weight: bold; color:#4caf50;">Nearest Center Location Successfully Referred!</div>
      </div>
    </div>
  </div>
</div>


</body>
<script>
$(document).ready(function() {

    $('#upload').click(function() {

        var pid = <?php echo $pid; ?>;
        if (pid !== 0 && pid !== '') { 
            $('#fileInput').click();
        } else {
            alert('Please select Patient');
          
        }
    });

});


$('#fileInput').change(function() {
       var fileData = $('#fileInput').prop('files')[0];
        var formData = new FormData();
        formData.append('fileInput', fileData);
       
        $.ajax({
            url: 'uploads.php', 
            type: 'POST',
            data: formData,
            contentType: false, // Set content type to false
            processData: false, // Don't process the data
            success: function(response) {
                $('#modalStatus').text(response.status);
                $('#uploadFile').text(response.uploadFile);

                if(response.status == 'EST GFR Whole Blood value not found in the Excel sheet'){
                    $('#exampleModal').modal('show');
                    $('#refer').hide();
                    $("#status").css("color","#e82a1c");
                    $('#exampleModal').modal('show');
                    setTimeout(function() {
                        $('#exampleModal').modal('hide');
                        window.location.reload();
                    }, 4000); 
                    
                }
                else if (response.status !== 'is Eligible') {
                    $('#refer').hide();
                    $("#status").css("color","#e82a1c");
                    $('#exampleModal').modal('show');
                    setTimeout(function() {
                        $('#exampleModal').modal('hide');
                        window.location.reload();
                    }, 4000); 
                 }else{
                    $('#refer').show();
                    $('#exampleModal').modal('show'); 
                    $("#status").css("color","#4caf50");
            }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    });


$('#ok_button').click(function() {
    $('#near_loc').hide();
    $('#loader').show();
    $(this).hide();
    var loaderDuration = 2000; 
    var successDuration = 4000; 
    var totalDuration = loaderDuration + successDuration;
    setTimeout(function() {
        $('#loader').hide();
        $('#success_message').show();
        setTimeout(function() {
            $('#exampleModal').modal('hide');
            // Reload the page after hiding the modal
            window.location.reload();
        }, successDuration);
    }, loaderDuration);
});
$(".close").click(function(){
    window.location.reload();
});
$(".check").click(function() {
    var id = $(this).attr('id');
    if (id == 'yescheckbox') {
        $('#near_loc').show();
        $('#status').hide();
        $('#refer').hide();
        $('.modal-header').hide();
    } else {
        $('.close').trigger('click');
        // $('form')[0].reset();
        window.location.reload(); // Reload the page
    }
});

</script>

