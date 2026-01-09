<?php
require_once(dirname(__FILE__) . '/config.php');
require_once('Common/Lib/CommonLib.php');

CheckTourSession(true);
checkFullACL(AclISKServer, 'iskUser', AclReadOnly);

$PAGE_TITLE = 'ISK Diagnostic Tool';
$IncludeJquery = true;
$IncludeFA = true;

$JS_SCRIPT = array(
    '<style>
        .diagnostic {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .ok {
            color: #4CAF50;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        table.diag-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table.diag-table th,
        table.diag-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table.diag-table th {
            background-color: #4CAF50;
            color: white;
        }
        table.diag-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-active {
            background: #4CAF50;
            color: white;
        }
        .badge-inactive {
            background: #f44336;
            color: white;
        }
        .badge-pending {
            background: #ff9800;
            color: white;
        }
    </style>',
);

include('Common/Templates/head.php');

echo '<div class="diagnostic">';
echo '<h1><i class="fa fa-stethoscope"></i> ISK System Diagnostic</h1>';
echo '<p>Tournament: <strong>' . $_SESSION['TourName'] . '</strong> (ID: ' . $_SESSION['TourId'] . ')</p>';
echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';

// 1. Check Tournament Configuration
echo '<div class="section">';
echo '<h3>1. Tournament Configuration</h3>';
$q = safe_r_sql("SELECT * FROM Tournament WHERE ToId={$_SESSION['TourId']}");
if ($r = safe_fetch($q)) {
    echo '<table class="diag-table">';
    echo '<tr><th>Parameter</th><th>Value</th></tr>';
    echo '<tr><td>Tournament Name</td><td>' . $r->ToName . '</td></tr>';
    echo '<tr><td>Code</td><td>' . $r->ToCode . '</td></tr>';
    echo '<tr><td>Type</td><td>' . $r->ToType . '</td></tr>';
    echo '<tr><td>Category</td><td>' . $r->ToCategory . '</td></tr>';
    echo '<tr><td>Num Ends</td><td>' . $r->ToNumEnds . '</td></tr>';
    echo '</table>';
    echo '<span class="ok">✓ Tournament configuration found</span>';
} else {
    echo '<span class="error">✗ Tournament not found!</span>';
}
echo '</div>';

// 2. Check ISK Devices
echo '<div class="section">';
echo '<h3>2. ISK Devices</h3>';
$q = safe_r_sql("SELECT * FROM IskDevices WHERE IskDvTournament={$_SESSION['TourId']}");
$deviceCount = 0;
echo '<table class="diag-table">';
echo '<tr><th>Device</th><th>Code</th><th>Target</th><th>Group</th><th>Active</th><th>Version</th><th>Setup</th></tr>';
while ($r = safe_fetch($q)) {
    $deviceCount++;
    $activeClass = $r->IskDvProActive ? 'badge-active' : 'badge-inactive';
    echo '<tr>';
    echo '<td>' . $r->IskDvDevice . '</td>';
    echo '<td>' . $r->IskDvCode . '</td>';
    echo '<td>' . $r->IskDvTarget . '</td>';
    echo '<td>' . $r->IskDvGroup . '</td>';
    echo '<td><span class="status-badge ' . $activeClass . '">' . ($r->IskDvProActive ? 'ACTIVE' : 'INACTIVE') . '</span></td>';
    echo '<td>' . $r->IskDvVersion . '</td>';
    echo '<td>' . $r->IskDvSetup . '...</td>';
    echo '</tr>';
}
echo '</table>';
if ($deviceCount > 0) {
    echo '<span class="ok">✓ Found ' . $deviceCount . ' device(s)</span>';
} else {
    echo '<span class="error">✗ No devices found! Please register your devices first.</span>';
}
echo '</div>';

// 3. Check ISK Module Parameters
echo '<div class="section">';
echo '<h3>3. ISK-NG Module Parameters</h3>';
$q = safe_r_sql("SELECT * FROM ModulesParameters WHERE MpModule='ISK-NG' AND (MpTournament={$_SESSION['TourId']} OR MpTournament=0)");
echo '<table class="diag-table">';
echo '<tr><th>Parameter</th><th>Tournament Specific</th><th>Value</th></tr>';
$params = [];
while ($r = safe_fetch($q)) {
    $params[$r->MpParameter] = $r;
    $value = $r->MpValue;
    if (strlen($value) > 100) {
        $value = substr($value, 0, 1000) . '...';
    }
    echo '<tr>';
    echo '<td><strong>' . $r->MpParameter . '</strong></td>';
    echo '<td>' . ($r->MpTournament ? 'Yes' : 'No') . '</td>';
    echo '<td><pre>' . htmlspecialchars($value) . '</pre></td>';
    echo '</tr>';
}
echo '</table>';

// Check critical parameters
$hasSequence = isset($params['Sequence']);
$hasGrouping = isset($params['Grouping']);

if ($hasSequence) {
    echo '<span class="ok">✓ Sequence parameter found</span><br>';
} else {
    echo '<span class="error">✗ Sequence parameter missing! This is required.</span><br>';
}

if ($hasGrouping) {
    echo '<span class="ok">✓ Grouping parameter found</span><br>';
} else {
    echo '<span class="warning">⚠ Grouping parameter not set (optional)</span><br>';
}
echo '</div>';

// 4. Detailed Sequence Analysis
echo '<div class="section">';
echo '<h3>4. Sequence Configuration Analysis</h3>';
$Sequences = getModuleParameter('ISK-NG', 'Sequence');
if (!empty($Sequences)) {
    echo '<table class="diag-table">';
    echo '<tr><th>Group ID</th><th>Group Name</th><th>Type</th><th>Session</th><th>IskKey</th><th>Distances</th></tr>';
    foreach ($Sequences as $gId => $seq) {
        echo '<tr>';
        echo '<td>' . $gId . '</td>';
        echo '<td>' . chr(65 + $gId) . '</td>';
        echo '<td>' . ($seq['type'] ?? 'N/A') . '</td>';
        echo '<td>' . ($seq['session'] ?? 'N/A') . '</td>';
        echo '<td>' . ($seq['IskKey'] ?? 'N/A') . '</td>';
        echo '<td>' . (isset($seq['distance']) ? implode(', ', $seq['distance']) : 'N/A') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<span class="ok">✓ Found ' . count($Sequences) . ' sequence(s)</span><br>';
    
    // Show raw sequence data
    echo '<details><summary>View Raw Sequence Data</summary>';
    echo '<pre>' . print_r($Sequences, true) . '</pre>';
    echo '</details>';
} else {
    echo '<span class="error">✗ No sequences configured! You need to configure sequences in ISK-NG setup.</span>';
}
echo '</div>';

// 5. Check Sessions
echo '<div class="section">';
echo '<h3>5. Tournament Sessions</h3>';
$q = safe_r_sql("SELECT * FROM Session WHERE SesTournament={$_SESSION['TourId']} ORDER BY SesOrder");
$sessionCount = 0;
echo '<table class="diag-table">';
echo '<tr><th>Order</th><th>Type</th><th>Name</th><th>Athletes/Target</th></tr>';
while ($r = safe_fetch($q)) {
    $sessionCount++;
    echo '<tr>';
    echo '<td>' . $r->SesOrder . '</td>';
    echo '<td>' . $r->SesType . '</td>';
    echo '<td>' . $r->SesName . '</td>';
    echo '<td>' . $r->SesAth4Target . '</td>';
    echo '</tr>';
}
echo '</table>';
if ($sessionCount > 0) {
    echo '<span class="ok">✓ Found ' . $sessionCount . ' session(s)</span>';
} else {
    echo '<span class="warning">⚠ No sessions found</span>';
}
echo '</div>';

// 6. Check Qualifications
echo '<div class="section">';
echo '<h3>6. Qualifications Data</h3>';
$q = safe_r_sql("SELECT QuSession, COUNT(*) as cnt FROM Qualifications 
                 INNER JOIN Entries ON EnId=QuId 
                 WHERE EnTournament={$_SESSION['TourId']} 
                 GROUP BY QuSession");
$qualCount = 0;
echo '<table class="diag-table">';
echo '<tr><th>Session</th><th>Archers Count</th></tr>';
while ($r = safe_fetch($q)) {
    $qualCount += $r->cnt;
    echo '<tr>';
    echo '<td>' . $r->QuSession . '</td>';
    echo '<td>' . $r->cnt . '</td>';
    echo '</tr>';
}
echo '</table>';
if ($qualCount > 0) {
    echo '<span class="ok">✓ Found ' . $qualCount . ' qualification entries</span>';
} else {
    echo '<span class="warning">⚠ No qualification data found</span>';
}
echo '</div>';

// 7. Check IskData (temporary scoring data)
echo '<div class="section">';
echo '<h3>7. ISK Temporary Data (IskData)</h3>';
$q = safe_r_sql("SELECT IskDtType, IskDtDistance, COUNT(*) as cnt
                 FROM IskData 
                 WHERE IskDtTournament={$_SESSION['TourId']} 
                 GROUP BY IskDtType, IskDtDistance");
$iskDataCount = 0;
echo '<table class="diag-table">';
echo '<tr><th>Type</th><th>Distance</th><th>Records</th><th>Last Update</th></tr>';
while ($r = safe_fetch($q)) {
    $iskDataCount += $r->cnt;
    echo '<tr>';
    echo '<td>' . $r->IskDtType . '</td>';
    echo '<td>' . $r->IskDtDistance . '</td>';
    echo '<td>' . $r->cnt . '</td>';
    echo '<td>' . $r->LastUpdate . '</td>';
    echo '</tr>';
}
echo '</table>';
if ($iskDataCount > 0) {
    echo '<span class="ok">✓ Found ' . $iskDataCount . ' temporary scoring record(s)</span>';
} else {
    echo '<span class="warning">⚠ No temporary scoring data yet (waiting for devices to send data)</span>';
}
echo '</div>';

// 8. API Configuration Check
echo '<div class="section">';
echo '<h3>8. API Configuration</h3>';
echo '<table class="diag-table">';
echo '<tr><th>Setting</th><th>Value</th></tr>';
echo '<tr><td>Use API</td><td>' . ($_SESSION["UseApi"] ?? 'Not set') . '</td></tr>';
echo '<tr><td>Tournament Code</td><td>' . ($_SESSION["TourCode"] ?? 'Not set') . '</td></tr>';
echo '<tr><td>Socket IP</td><td>' . getModuleParameter('ISK-NG', 'SocketIP', gethostbyname($_SERVER['HTTP_HOST'])) . '</td></tr>';
echo '<tr><td>Socket Port</td><td>' . getModuleParameter('ISK-NG', 'SocketPort', '12346') . '</td></tr>';
echo '</table>';
echo '</div>';

// 9. Recommendations
echo '<div class="section">';
echo '<h3>9. Recommendations</h3>';
echo '<ul>';

if ($deviceCount == 0) {
    echo '<li class="error">❌ <strong>CRITICAL:</strong> No devices registered. Please register your devices first.</li>';
}

if (!$hasSequence || empty($Sequences)) {
    echo '<li class="error">❌ <strong>CRITICAL:</strong> No sequence configuration found. Please configure ISK-NG sequences in the ISK-NG setup page.</li>';
    echo '<li>Go to ISK-NG menu → Configuration → Set up your sessions and groups</li>';
}

if ($qualCount == 0) {
    echo '<li class="warning">⚠ <strong>WARNING:</strong> No qualification data. Make sure archers are registered and assigned to targets.</li>';
}

if ($sessionCount == 0) {
    echo '<li class="warning">⚠ <strong>WARNING:</strong> No sessions configured. Create qualification sessions first.</li>';
}

if ($deviceCount > 0 && !empty($Sequences) && $qualCount > 0) {
    echo '<li class="ok">✓ Basic configuration seems correct. If devices are connected, they should appear in Results page.</li>';
    
    if ($iskDataCount == 0) {
        echo '<li class="warning">⚠ No data from devices yet. Check:</li>';
        echo '<ul>';
        echo '<li>Device is connected to network</li>';
        echo '<li>Device has correct tournament code configured</li>';
        echo '<li>Device group matches configured sequences</li>';
        echo '<li>Archers have started scoring</li>';
        echo '</ul>';
    }
}

echo '</ul>';
echo '</div>';

echo '</div>';

include('Common/Templates/tail.php');
?>