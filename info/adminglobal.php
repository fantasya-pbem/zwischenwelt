<?php

require_once("../lib.main.php");
require_once("../lib.building.php");
require_once("../lib.army.php");
require_once("../lib.map.php");
require_once("../lib.tableedit.php");
require_once("../lib.weather.php");

AdminLock();

$terrains = array(0=>"keines");
foreach($gTerrainType as $x)
	$terrains[$x->id] = $x->name;
	
$buildings = array(0=>"keines");
foreach($gBuildingType as $x)
	$buildings[$x->id] = $x->name;

$techs = array(0=>"keines");
foreach($gTechnologyType as $x)
	$techs[$x->id] = $x->name;

$weather = array(
	kRace_Mensch => "Mensch",
	kRace_Gnome => "Gnome"
);

$b = array(
	"building_hq"=>"Hauptgeb�ude",
	"building_lumber"=>"Geb�ude: Holzproduktion",
	"building_stone"=>"Geb�ude: Steinproduktion",
	"building_food"=>"Geb�ude: Nahrungproduktion",
	"building_metal"=>"Geb�ude: Metalproduktion",
	"building_runes"=>"Geb�ude: Runenproduktion",
	"building_house"=>"Geb�ude: Wohnhaus",
	"building_store"=>"Geb�ude: Lager",
	"building_gate"=>"Geb�ude: Tor",
	"building_bridge"=>"Geb�ude: Br�cke"
);

$ro = array(
	"lasttick"=>"Timestamp des letzten Ticks",
	"crontime"=>"Mittlere Laufzeit der Cron",
	"ticks"=>"Tickz�hler",
	"stats_nexttime"=>"n�chster Berechnungszeitpunkt f�r Statistiken",
	"minimap_left"=>"Minimap: Links",
	"minimap_right"=>"Minimap: Rechts",
	"minimap_top"=>"Minimap: Oben",
	"minimap_bottom"=>"Minimap: Unten",
	"testminimap"=>"testminimap",
);

$checkbox = array(
	
);

$l = array(
	"prod_slots_lumber"=>"Produktionsslots Holz",
	"prod_slots_stone"=>"Produktionsslots Stein",
	"prod_slots_food"=>"Produktionsslots Essen",
	"prod_slots_metal"=>"Produktionsslots Metall",
	"prod_slots_runes"=>"Produktionsslots Runen",
	"prod_faktor_slotless"=>"Produktionsfaktor ohne Slots",
	"prod_faktor"=>"Produktionsfaktor",
	"store"=>"Lagerkapazit�t",
	"pop_slots_hq"=>"Einwohner Hauptgeb�udes",
	"pop_slots_house"=>"Einwohner H�user",

	"fc_prod_runes"=>"runenkosten:food",
	"mc_prod_runes"=>"runenkosten:metal",
	"lc_prod_runes"=>"runenkosten:lumber",
	"sc_prod_runes"=>"runenkosten:stone",

	"unitresratio"=>"weight of one transported unit", // the real weight in unittype is only used for armylimit...

	"kArmyRecalcBlockedRoute_Timeout"=>"Timeout: Armee berechnet Umweg",
	"kArmyAutoAttackRangeMonster_Timeout"=>"Timeout: Armee schie�t automatisch auf Monster",
	"kArmy_BigArmyGoSlowLimit"=>"Armee-Einheiten-Langsam-Limit",
	"kArmy_BigArmyGoSlowFactorPer1000Units"=>"Armee-Einheiten-Langsam-Faktor-pro1000",
	"kBaracksDestMaxDist"=>"maximal-abstand f�r kasernen-stationierung",
	"kPortalTaxUnitNum"=>"portalsteuer pro x einheiten",
	"kTerraFormer_SicherheitsAbstand"=>"Sicherheitsabstand der Terraformer zu Spielern",
	"kArmy_AW_for_one_exp"=>"soviel a+v gibt einen frag",
	"wb_paybacklimit"=>"&uuml;ber welcher Punktegrenze werden Weltbankschulden abgezahlt",
	"wb_payback_perc"=>"wieviel Prozent der Eigenproduktion werden gezahlt",
	"wb_max_gp"=>"Maximale Punkte f&uuml;r Weltbank",
	"gp_pts_ratio"=>"Gildepunkte zu Punkten Ratio",
	
	"hq_min_x"=>"Minimales X des HQs",
	"hq_max_x"=>"Maximales X des HQs",
	"hq_min_y"=>"Minimales Y des HQs",
	"hq_max_y"=>"Maximales Y des HQs",
	/*
	"fc_prod_metal"=>"unused",
	"fc_prod_food"=>"unused",
	"fc_prod_lumber"=>"unused",
	"fc_prod_stone"=>"unused",
	"kArmy_ExpBonus"=>"unused",
	*/
);

$list_l = array();
foreach($l as $field=>$text){
	$list_l[] = new cTableEditTextField("global","name",$field,$text,"value");
}
$list_l[] = new cTableEditCheckedField("global","name","liveupdate","Liveupdate l�uft gerade","value");
$list_l[] = new cTableEditDropDown("global","name","tech_architecture","Technologie: Architektur","value",$techs);
$list_l[] = new cTableEditDropDown("global","name","weather","Wetter","value",$gWeatherType);

$list_b = array();
foreach($b as $field=>$text){
	$list_b[] = new cTableEditDropDown("global","name",$field,$text,"value",$buildings);
}

// global-tabelle mit default-werten f�llen
function global_default ($name,$default) {
	if (!sqlgetone("SELECT 1 FROM `global` WHERE `name` = '".addslashes($name)."'"))
		sql("REPLACE INTO `global` SET `value`='".addslashes($val)."' , `name`='".addslashes($name)."'");
}
global_default("randomspawnmonsters","");
global_default("liveupdate","0");
global_default("tech_architecture","0");
global_default("weather","0");
foreach($l as $field=>$text) global_default($field,"0");
foreach($b as $field=>$text) global_default($field,"0");

// randomspawnmonsters
$monsters = array();
foreach ($gUnitType as $o) if (intval($o->flags) & kUnitFlag_Monster) $monsters[$o->id] = "<img src='".g($o->gfx)."'>";
$list_b[] = new cTableEditListFlagField("global","name","randomspawnmonsters","RandomSpawnMonsters","value",$monsters);



$form = new cTableEditForm("?sid=?","Globale Einstellungen",
	new cTableEditCols(array(
		new cTableEditRows($list_l),
		new cTableEditRows($list_b)
	))
);

$form->HandleInput();

require_once("header.php"); 
$form->Show();
require_once("footer.php"); 

?>
