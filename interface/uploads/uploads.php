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


if(isset($_POST['submit'])){
$uploadfile = $_POST['fileInput'];
$excel_ecategory_Name = $uploadfile;
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
        $upload_category_data[] = $crowData;
    }
}
array_shift($upload_category_data);

$excel_ecategory_Name = 'Neuro.xlsx';
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
// print_r($excel_category_data); 
// die();
// echo '<br>';
// print_r($upload_category_data);
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
   <form method='post' action=''>
<div class="container">
<button type="button" id='upload' class="btn btn-primary btn-sm">Upload File</button>
<input type="file" id="fileInput" name="fileInput" style="display: none;" accept=".xlsx, .xls">
<button type="button" class="btn btn-secondary btn-sm">Review</button>
  </div>
  <br>
  <input type='submit' style='margin-left:85px;' name='submit'>
</form>
<?php echo $uploadfile;?>
<?php
function compareData($excel_category_data, $upload_category_data) {
    $comparisonResults = [];

    foreach ($excel_category_data as $index => $category) {
        $categoryName = $category[0];
        $value1 = $category[1];
        $value2 = $upload_category_data[$index][1]; // Assuming both sets have the same categories in the same order

        if ($value2 > $value1) {
            $comparisonResults[$categoryName] = "higher";
        } elseif ($value2 < $value1) {
            $comparisonResults[$categoryName] = "lower";
        } else {
            $comparisonResults[$categoryName] = "equal";
        }
    }

    return $comparisonResults;
}

$comparisonResults = compareData($excel_category_data, $upload_category_data);

foreach ($comparisonResults as $category => $result) {
    // $query=sqlStatement("INSERT INTO `uploads`(category,result) VALUES ('$category','$result')");
    // echo "$category :$result <br>";
}
?>
</body>
<script>
    $(document).ready(function() {
      $('#upload').click(function() {
        $('#fileInput').click();
      });
    });
  </script>