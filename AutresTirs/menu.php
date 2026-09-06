<?php
if (!empty($on) && isset($ret['PRNT'])) {
    array_splice($ret['PRNT'], 6, 0, 'Impression Autres Tirs' . '|' . $CFG->ROOT_DIR . 'Modules/Custom/AutresTirs/' . 'PrnAutresTirs.php');
}

if (!empty($on) && isset($ret['QUAL'])) {
    array_splice($ret['QUAL'], 12, 0, 'Impression Autres Tirs' . '|' . $CFG->ROOT_DIR . 'Modules/Custom/AutresTirs/' . 'PrnAutresTirs.php');
}
