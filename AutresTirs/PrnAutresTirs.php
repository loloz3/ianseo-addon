<?php

/**
 * @license Libre - Copyright (c) 2025 Auteur Original
 * Libre d'utilisation, modification et distribution sous conditions:
 * 1. Garder cette notice et la liste des contributeurs
 * 2. Partager toute modification
 * 3. Citer les contributeurs
 *
 * Contributeurs:
 * - Auteur Original
 * - Laurent Petroff - Les Archers de Perols - (modif: 2025-12-11)
 * - Guillaume Roques (modif: 2026-09-05)
 *
 * Dernière modification: 2026-09-05 par Guillaume Roques
 *
 * Utilisation directement de la fonction getDivClasIndividual de Ianseo pour récupérer les données
 */

require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Lib/Obj_RankFactory.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');

function getDivClasIndividualAutresTirs($Div='', $Clas='', $Options=array()) {
    $Data = getDivClasIndividual($Div, $Clas, $Options);
    $Data->Description=get_text('ResultIndClass','Tournament') . ' - Autres Tirs';
    $Data->IndexName=get_text('ResultIndClass','Tournament') . ' - Autres Tirs';

    // Filtrage simple : ne garder que les archers avec EnIndFEvent == 0
    if(isset($Data->rankData['sections'])) {
        foreach($Data->rankData['sections'] as $sectionKey => $section) {
            if(isset($section['items'])) {
                $filteredItems = [];

                foreach($section['items'] as $item) {
                    // Vérifier EnIndFEvent dans la base de données
                    $query = "SELECT EnIndFEvent
                                FROM Entries
                                WHERE EnId=" . intval($item['id']) . "
                                    AND EnTournament = " . intval($_SESSION['TourId']) . "
                                    AND EnIndFEvent = 0";
                    $rs = safe_r_sql($query);

                    if($row = safe_fetch($rs)) {
                        // Masquer le rang
                        $item['rank'] = '';
                        $filteredItems[] = $item;
                    }
                }

                // Remplacer les items
                $section['items'] = $filteredItems;

                // Ajouter "- Autres Tirs" s'il n'y est pas déjà
                if(!str_contains($section['meta']['descr'], 'Autres Tirs')) {
                    $section['meta']['descr'] .= ' - Autres Tirs';
                }
            }
            $Data->rankData['sections'][$sectionKey] = $section;
        }

        // Supprimer les sections vides (catégories sans archers)
        foreach($Data->rankData['sections'] as $sectionKey => $section) {
            if(empty($section['items'])) {
                unset($Data->rankData['sections'][$sectionKey]);
            }
        }
    }

    return $Data;
}

if (!isset($_SESSION['TourId']) && isset($_REQUEST['TourId'])) {
    CreateTourSession($_REQUEST['TourId']);
}
checkFullACL(AclQualification, '', AclReadOnly);

// ATTENTION!
// MUST BE called $PdfData
$PdfData = getDivClasIndividualAutresTirs();

if(!isset($isCompleteResultBook)) {
    $pdf = new ResultPDF($PdfData->Description);
}

require_once(PdfChunkLoader('DivClasIndividual.inc.php'));

if (isset($_REQUEST['TourId'])) {
    EraseTourSession();
}

if(isset($__ExportPDF)) {
    $__ExportPDF = $pdf->Output('','S');
}
else if(!isset($isCompleteResultBook)) {
    if(isset($_REQUEST['ToFitarco'])) {
        $Dest='D';
        if (isset($_REQUEST['Dest']))
            $Dest=$_REQUEST['Dest'];

        if ($Dest=='S')
            print $pdf->Output($_REQUEST['ToFitarco'],$Dest);
        else
            $pdf->Output($_REQUEST['ToFitarco'],$Dest);
    }
    else
        $pdf->Output();
}
