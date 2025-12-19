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
 * 
 * Dernière modification: 2025-12-15 par Laurent Petroff
 *
 * Fonction pour obtenir uniquement les archers avec EnIndFEvent == 0
 * (Autres Tirs - archers qui ont tiré après leur 1er départ)
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/pdf/ResultPDF.inc.php');
require_once('Common/Lib/Obj_RankFactory.php');
require_once('Common/Fun_FormatText.inc.php');
require_once('Common/OrisFunctions.php');
require_once('Common/pdf/PdfChunkLoader.php');


function getDivClasIndividualAutresTirs($Div='', $Clas='', $Options=array()) {
    foreach($Options as $k => $v) $_REQUEST[$k]=$v;

    $Data=new StdClass();

    $Data->Order='1';
    $Data->HideCols = GetParameter("IntEvent");
    $Data->hideGolds = (getTournamentType()==14 or getTournamentType()==32);
    $Data->NumberThousandsSeparator = get_text('NumberThousandsSeparator');
    $Data->Description=get_text('ResultIndClass','Tournament') . ' - Autres Tirs';
    $Data->Continue=get_text('Continue');
    $Data->TotalShort=get_text('TotalShort','Tournament');
    $Data->IndexName=get_text('ResultIndClass','Tournament') . ' - Autres Tirs';

    $options=array('dist'=>0);
    
    if(isset($_REQUEST["Event"]))
        $options['events'] = $_REQUEST["Event"];
    if(isset($_REQUEST["MaxNum"]) && is_numeric($_REQUEST["MaxNum"]))
        $options['cutRank'] = $_REQUEST["MaxNum"];
    if(isset($_REQUEST["ScoreCutoff"]) && is_numeric($_REQUEST["ScoreCutoff"]))
        $options['cutScore'] = $_REQUEST["ScoreCutoff"];
    if(isset($_REQUEST["Classes"]))
    {
        if(is_array($_REQUEST["Classes"]))
            $options['cls'] = $_REQUEST["Classes"];
        else
            $options['cls'] = array($_REQUEST["Classes"]);
    }
    if(isset($_REQUEST["Divisions"]))
    {
        if(is_array($_REQUEST["Divisions"]))
            $options['divs'] = $_REQUEST["Divisions"];
        else
            $options['divs'] = array($_REQUEST["Divisions"]);
    }
    if($Div) $options['divs'] = array($Div);
    if($Clas) $options['cls'] = array($Clas);

    $family='DivClass';
    if(!empty($_REQUEST["distEnable"]) && isset($_REQUEST["atDist"]) && intval($_REQUEST["atDist"]))
        $options['dist'] = $_REQUEST["atDist"];
    elseif(!empty($_REQUEST["distEnable"]) && isset($_REQUEST["runningDist"]) && intval($_REQUEST["runningDist"]))
        $options['runningDist'] = $_REQUEST["runningDist"];
    elseif(!empty($_REQUEST["Snapshot"]))
    {
        $options['subFamily'] = $family;
        $family = 'Snapshot';
        if(!empty($_REQUEST["SnapshotArrNo"]))
            $options['arrNo'] = $_REQUEST["SnapshotArrNo"];
        else
            $options['arrNo'] = 0;
    }
    elseif(!empty($_REQUEST["SubClassRank"]))
    {
        $family='SubClass';
        if(!empty($_REQUEST["sc"])) $options['sc']=$_REQUEST['sc'];
        if(!empty($_REQUEST["SubClassDivRank"])) $options['joinDivs']=true;
        if(!empty($_REQUEST["SubClassClassRank"])) $options['joinCls']=true;
        if(!empty($_REQUEST["SubClassGenderRank"])) $options['joinGender']=true;
        if(!empty($_REQUEST["ShowAwards"])) $options['showAwards'] = true;
    }

    $Data->family=$family;

    $rank=Obj_RankFactory::create($family,$options);
    $rank->read();
    $rankData=$rank->getData();
    
	/*
    // Supprimer TOUS les textes "After 60 Arrows" au niveau global
    if(isset($rankData['meta']['printHeader'])) {
        $rankData['meta']['printHeader'] = '';
    }
    */
	
    // Filtrage simple : ne garder que les archers avec EnIndFEvent == 0
    if(isset($rankData['sections'])) {
        foreach($rankData['sections'] as $sectionKey => &$section) {
            if(isset($section['items'])) {
                $filteredItems = array();
                
                foreach($section['items'] as $item) {
                    // Vérifier EnIndFEvent dans la base de données
                    $query = "SELECT EnIndFEvent 
                              FROM Entries 
                              WHERE EnId=" . intval($item['id']) . " 
                              AND EnTournament=" . intval($_SESSION['TourId']);
                    $rs = safe_r_sql($query);
                    
                    if($row = safe_fetch($rs)) {
                        // Ne garder que si EnIndFEvent == 0
                        if($row->EnIndFEvent == 0) {
                            // Masquer le rang
                            $item['rank'] = '';
                            $filteredItems[] = $item;
                        }
                    }
                }
                
                // Remplacer les items
                $section['items'] = $filteredItems;
				
				// Ajouter "- Autres Tirs" s'il n'y est pas déjà
                if(stripos($section['meta']['descr'], 'Autres Tirs') === false) {
                $section['meta']['descr'] .= ' - Autres Tirs';
				}
                
				
				/*
                // Vider le champ rank dans les fields aussi
                if(isset($section['fields']['rank'])) {
                    $section['fields']['rank'] = '';
                }
                
                // Nettoyer ABSOLUMENT TOUS les champs possibles
                $section['printHeader'] = '';
                $section['sesArrows'] = array();
                
                // Nettoyer le titre de section
                if(isset($section['descr'])) {
                    $section['descr'] = preg_replace('/After \d+ Arrows.*$/i', '', $section['descr']);
                    $section['descr'] = trim($section['descr']);
                    if(stripos($section['descr'], 'Autres Tirs') === false) {
                        $section['descr'] .= ' - Autres Tirs';
                    }
                }
                
                if(isset($section['meta']['descr'])) {
                    $section['meta']['descr'] = preg_replace('/After \d+ Arrows.*$/i', '', $section['meta']['descr']);
                    $section['meta']['descr'] = trim($section['meta']['descr']);
                }
				*/
            }
        }
        
        // Supprimer les sections vides (catégories sans archers)
        foreach($rankData['sections'] as $sectionKey => $section) {
            if(empty($section['items'])) {
                unset($rankData['sections'][$sectionKey]);
            }
        }
    }
    
    $Data->rankData=$rankData;

    return $Data;
}

// ATTENTION!
// MUST BE called $PdfData
$PdfData = getDivClasIndividualAutresTirs();

if (!isset($_SESSION['TourId']) && isset($_REQUEST['TourId'])) {
    CreateTourSession($_REQUEST['TourId']);
}
checkFullACL(AclQualification, '', AclReadOnly);

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

?>