<?php

/**
 * external_data.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 */

require_once("../globals.php");
require_once("$srcdir/patient.inc.php");
require_once "$srcdir/options.inc.php";

use OpenEMR\Core\Header;
use OpenEMR\Menu\PatientMenuRole;
use OpenEMR\OeUI\OemrUI;

// Mock values for the additional data
$patientData = [
    'GAMMA GT' => 84,
    'BILIRUBIN DIRECT' => 14,
    'BILIRUBIN TOTAL' => 578,
    'ALK PHOSPHATASE BLD' => 14,
    'LD(LDHG)' => 25,
    'Protein Total' => 85,
    'EST GFR Whole Blood' => 22
];

?>
<html>
    <head>
        <?php Header::setupHeader();?>
        <title><?php echo xlt('Test Results'); ?></title>
        <style>
            .result-table {
                border-collapse: collapse;
                width: 80%;
                margin: auto;
                background-color: #f8f9fa;
            }
            .result-table th, .result-table td {
                border: 1px solid #dee2e6;
                padding: 10px;
                text-align: left;
            }
            .result-table th {
                background-color: #007bff;
                color: white;
                font-size: 1.1rem;
            }
            .result-table td {
                font-size: 1rem;
                color: #343a40;
            }
        </style>
    </head>
    <body>
        <div id="container_div" class="mt-3 text-center">
            <h2><?php echo xlt('Test Results'); ?></h2>
            <table class="result-table mt-3" style='text-align:center;'>
                <thead>
                    <tr>
                        <th><?php echo xlt('Name'); ?></th>
                        <th><?php echo xlt('Result'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patientData as $testName => $testResult): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($testName); ?></td>
                            <td><?php echo htmlspecialchars($testResult); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>
</html>
