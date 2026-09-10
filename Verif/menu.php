<?php
if (!empty($on) && isset($ret['PART'])) {
    require_once('Verification.class.php');
    $Verification = new Verification();
    if ($Verification->partecipantsHaveErrors()) {
        $VerifWarning = '&nbsp;<b class="ShootOffMenu2">⚠️</b>';
    } else {
      $VerifWarning = '';
    }
    array_splice($ret['PART'], 6, 0, 'Vérification des Participants' . $VerifWarning . '|' . $CFG->ROOT_DIR . 'Modules/Custom/Verif/' . 'Verification.php');
}
