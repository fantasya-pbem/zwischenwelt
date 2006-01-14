<?php
require_once("../lib.main.php");
require_once("../lib.building.php");
require_once("../lib.map.php");
require_once("../lib.construction.php");
require_once("../lib.quest.php");
require_once("../lib.trade.php");
require_once("../lib.transfer.php");
require_once("class.infobase.php");
require_once("class.infobuilding.php");
require_once("class.inforeq.php");

$infostarttime = microtime_float();

Lock();

$gJSCommands = array();
$gInfoTabs = array();
$gInfoTabsSelected = -1; // -1 is replaced by the last one
$gInfoTabsPriority = 0; // -1 is replaced by the last one
$gInfoTabsCorner = "";
$info_message = ""; //ausgabevariable fuer z.b. spells


function JSAddInfoMessage ($html) {
	global $gJSCommands;
	$gJSCommands[] = "if (parent.info.AddInfoMessage) parent.info.AddInfoMessage(\"".addslashes($html)."\");";
}


function RegisterInfoTab ($head,$content,$select_priority=false) {
	global $gInfoTabs,$gInfoTabsSelected,$gInfoTabsPriority;
	$gInfoTabs[] = array($head,$content);
	if ($select_priority && $select_priority > $gInfoTabsPriority) {
		$gInfoTabsSelected = count($gInfoTabs)-1;
		$gInfoTabsPriority = $select_priority;
	}
}

function JSRefreshArmy ($army) {
	global $gJSCommands;
	if (!is_object($army)) $army = sqlgetobject("SELECT * FROM `army` WHERE `id` = ".intval($army));
	$gJSCommands[] = "parent.map.JSArmyUpdate(".cArmy::GetJavaScriptArmyData($army).");";
	$gJSCommands[] = "parent.map.JSActivateArmy(".$army->id.",\"".cArmy::GetJavaScriptWPs($army->id)."\");";
}

// collect data for a single cell, and send to js map
// surrounding : also refresh adjacted cells
// noupdate : send only data, not update command (used for surrounding recursion)
function JSRefreshCell ($x,$y,$surrounding=false,$noupdate=false) {
	global $gJSCommands;
	$x = intval($x); $y = intval($y);
	$gJSCommands[] = "parent.map.JSClear($x,$y);";
	$localusers = array(); // ignored for now // TODO : implement me
	$building = sqlgetobject("SELECT * FROM `building` WHERE `x` = ".$x." AND `y` = ".$y." LIMIT 1");
	if ($building) $gJSCommands[] = "parent.map.JSBuildingUpdate(".cBuilding::GetJavaScriptBuildingData($building).");";
	if ($building) $localusers[] = $building->user;
	$army = sqlgetobject("SELECT * FROM `army` WHERE `x` = ".$x." AND `y` = ".$y." LIMIT 1");
	if ($army) $gJSCommands[] = "parent.map.JSArmyUpdate(".cArmy::GetJavaScriptArmyData($army).");";
	if ($army) $localusers[] = $army->user;
	$items = sqlgettable("SELECT * FROM `item` WHERE `army` = 0 AND `building` = 0 AND `x` = ".$x." AND `y` = ".$y);
	foreach ($items as $item) $gJSCommands[] = "parent.map.JSInsertItem($item->x,$item->y,$item->type,$item->amount);";
	if ($surrounding) {
		JSRefreshCell($x-1,$y,false,true);
		JSRefreshCell($x+1,$y,false,true);
		JSRefreshCell($x,$y-1,false,true);
		JSRefreshCell($x,$y+1,false,true);
	}
	$terraintype = cMap::StaticGetTerrainAtPos($x,$y);
	$gJSCommands[] = "parent.map.JSSetTerrain($x,$y,$terraintype,1);";
	if (!$noupdate) {
		$gJSCommands[] = "parent.map.JSRefreshCell($x,$y);";
		if ($surrounding) {
			$gJSCommands[] = "parent.map.JSRefreshCell(".($x-1).",".($y).");";
			$gJSCommands[] = "parent.map.JSRefreshCell(".($x+1).",".($y).");";
			$gJSCommands[] = "parent.map.JSRefreshCell(".($x).",".($y-1).");";
			$gJSCommands[] = "parent.map.JSRefreshCell(".($x).",".($y+1).");";
		}
	}
}

$guildcommander = FALSE;
if ($gUser->guild > 0) {
	$gc = getGuildCommander($gUser->guild);
	if(in_array($gUser->id,$gc))$guildcommander=TRUE;
}

$f_x = intval($f_x);
$f_y = intval($f_y);
$gDoReload = true;

if (!isset($f_building) && !isset($f_army) && isset($f_do)) {
	rob_ob_start();
	switch ($f_do) {
		case "mapmark":
			$mark = array("x"=>intval($f_x),"y"=>intval($f_y),"user"=>$gUser->id);
			if (isset($f_new)) { $mark["name"] = ereg_replace("[&<>]","_",$f_mapmarkname); sql("INSERT INTO `mapmark` SET ".arr2sql($mark)); }
			if (isset($f_del)) sql("DELETE FROM `mapmark` WHERE ".arr2sql($mark," AND "));
			$gJSCommands[] = "parent.navi.location.href = parent.navi.location.href;";
		break;
		case "armytransfer":
			cTransfer::TryArmyTransfer($f_transfer,$f_sourcebuilding,$f_sourcearmy,$f_armyname,$f_armyunits,$gUser);
		break;
		case "setfof": 
			if (isset($f_delfriend)) SetFOF($gUser->id,intval($f_other),kFOF_Neutral); 
			if (isset($f_addfriend)) SetFOF($gUser->id,intval($f_other),kFOF_Friend); 
			if (isset($f_delenemy)) SetFOF($gUser->id,intval($f_other),kFOF_Neutral); 
			if (isset($f_addenemy)) SetFOF($gUser->id,intval($f_other),kFOF_Enemy); 
		break;
		case "choose_cast":
			$pastinclude="magic.php";
		break;
		case "quickmagic":
		case "cast_spell":
			if ($f_do == "quickmagic") {
				// $f_tower = MagicAutoChooseTower($gUser->id); // TODO : implement me...
				$f_tower = sqlgetone("SELECT `id` FROM `building` WHERE `user` = ".intval($gUser->id)." ORDER BY `mana` DESC");
				// $spelltypeid = intval($f_spellid);
				$spelltype = $gSpellType[intval($f_spellid)];
				$f_count = array($spelltype->id=>1);
			}
			$pastinclude="magic.php";
			//$gSpellType = sqlgettable("SELECT * FROM `spelltype`","id");
			$result = 0;
			$castspelltype = false;
			$tower = sqlgetobject("SELECT * FROM `building` WHERE `id` = ".intval($f_tower));
			if ($tower->user != $gUser->id) break;
			require_once("lib.spells.php");
			foreach ($gSpellType as $spelltype){
				if (isset($f_count[$spelltype->id]))
				for ($i=0;$i<intval($f_count[$spelltype->id]);++$i) {
					rob_ob_start();
					$newspell = GetSpellInstance($spelltype->id);
					$r = $newspell->Cast($spelltype,$f_x,$f_y,$gUser->id,$tower->id);
					$result = rob_ob_end();
					echo "<span style='color:blue'>Ihr habt ".$spelltype->name." sprechen lassen ....</span><br>";
					$col = $r?"green":"red";
					echo "<span style='color:$col'>$result</span><br>";
				}
			}
			// TODO : terrain feedback
		break;
		case "delwaypoint":
			require_once("../lib.army.php");
			$wp = sqlgetobject("SELECT * FROM `waypoint` WHERE `id` = ".intval($f_id));
			if ($wp) {
				$army = sqlgetobject("SELECT * FROM `army` WHERE `id` = ".$wp->army);
				if ($army && (intval($army->flags) & kArmyFlag_SelfLock) && $army->user == $gUser->id) break;
				if ($army && $wp->priority > 0 && cArmy::CanControllArmy($army,$gUser)) {
					cArmy::ArmyCancelWaypoint($army,$wp);	
					echo "Wegpunkt gel�scht";
					JSRefreshArmy($army);
				}
			}
		break;
		case "delallwaypointafter":
			require_once("../lib.army.php");
			$wp = sqlgetobject("SELECT * FROM `waypoint` WHERE `id` = ".intval($f_id));
			if ($wp) {
				$army = sqlgetobject("SELECT * FROM `army` WHERE `id` = ".$wp->army);
				if (intval($army->flags) & kArmyFlag_SelfLock) break;
				if ($army && $wp->priority > 0 && cArmy::CanControllArmy($army,$gUser)) {
					$allwpafter = sqlgettable("SELECT * FROM `waypoint` WHERE 
						`army` = ".$wp->army." AND `priority` >= ".$wp->priority." ORDER BY `priority` DESC");
					foreach ($allwpafter as $o)
						cArmy::ArmyCancelWaypoint($army,$o);
					echo "Wegpunkte gel�scht";
					JSRefreshArmy($army);
				}
			}
		break;
		case "build":
			function JSAddInfoMessage_BuildError($btype,$x,$y,$reason) {
				JSAddInfoMessage(GetBuildingTypeLink($btype,$x,$y)." konnte bei ".pos2txt($x,$y)." nicht gebaut werden : ".$reason."<br>");
			}
			
			foreach ($f_build as $typeid => $val) { // this array won't have more than one entry
				$btype = $gBuildingType[intval($typeid)]; 
				
				$f_brushrad = 0; // building brushsize forced to 0
				$fields = GetBrushFields($f_x,$f_y,$f_brushrad,100,$f_brush,$f_brushline,$f_brushlastx,$f_brushlasty);
				foreach ($fields as $posarr) {
					list($f_x,$f_y) = $posarr;
					$x = $f_x;
					$y = $f_y;
					
					// check other plans
					if ($typeid == kBuilding_HQ && !isPositionInBuildableRange($f_x,$f_y)) {
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"momentan gesperrter Bereich, bitte nur zwischen ".$gGlobal["hq_min_x"].",".$gGlobal["hq_min_y"]." und ".$gGlobal["hq_max_x"].",".$gGlobal["hq_max_y"]." bauen");
						continue;
					} 
					
					// check other plans
					if (OwnConstructionInProcess($f_x,$f_y)) {
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"hier ist bereits ein Bauplan");
						continue;
					}
					// check build cross
					if (!InBuildCross($f_x,$f_y,$gUser->id)) {
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"kein eigenes Geb�ude in der N�he");
						continue;
					}
					// check req
					if (!HasReq($btype->req_geb,$btype->req_tech,$gUser->id)) {
						$anforderungen = GetBuildingTypeLink($btype,$f_x,$f_y,"<font color=\"red\"><b>Anforderungen</b></font>");
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$anforderungen sind nicht erf�llt");
						continue;
					}
					
					// check nearby terrain
					$terrain = sqlgetone("SELECT `type` FROM `terrain` WHERE `x` = ".$f_x." AND `y` = ".$f_y);
					if ($btype->terrain_needed > 0 && $btype->terrain_needed != $terrain) {
						$reqpic = "<img border=0 src=\"".g($gTerrainType[$btype->terrain_needed]->gfx)."\">";
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"kann nur auf $reqpic gebaut werden");
						continue;
					}
					if ($gTerrainType[$terrain]->buildable == 0 && $btype->terrain_needed != $terrain) {
						$reqpic = "<img border=0 src=\"".g($gTerrainType[$terrain]->gfx)."\">";
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"Gel�nde $reqpic ist nicht bebaubar");
						continue;
					}
					
					// check for blocking building
					$blockerbuilding = sqlgetobject("SELECT * FROM `building` WHERE `x` = ".$f_x." AND `y` = ".$f_y);
					if ($blockerbuilding) {
						$reqpic = GetBuildingTypeLink($blockerbuilding->type,$f_x,$f_y,false,$blockerbuilding->user,$blockerbuilding->level);
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"hier befindet sich bereits ein Geb�ude : ".$reqpic."");
						continue;
					}
					
					// check building requirements
					if (sizeof($btype->exclude_building)>0 && 
						GetNearBuilding($x,$y,$gUser->id,kBuildingRequirenment_ExcludeRadius,$btype->exclude_building)) {
						$piclist = "";
						foreach ($btype->exclude_building as $b) $piclist .= GetBuildingTypeLink($b,$f_x,$f_y);
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$piclist darf nicht direkt daneben sein");
						continue;
					}
					if (sizeof($btype->neednear_building)>0 && 
						!GetNearBuilding($x,$y,$gUser->id,kBuildingRequirenment_NearRadius,$btype->neednear_building)) {
						$piclist = "";
						foreach ($btype->neednear_building as $b) $piclist .= GetBuildingTypeLink($b,$f_x,$f_y);
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$piclist muss in der N�he sein");
						continue;
					}
					if (sizeof($btype->require_building)>0 && 
						!GetNearBuilding($x,$y,$gUser->id,kBuildingRequirenment_NextToRadius,$btype->require_building)) {
						$piclist = "";
						foreach ($btype->require_building as $b) $piclist .= GetBuildingTypeLink($b,$f_x,$f_y);
						JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$piclist muss direkt daneben sein");
						continue;
					}
					
					// final check, CanBuildHere() is main function also used by cron
					if (!CanBuildHere($x,$y,$btype->id,$gUser->id)) {
						switch ($btype->id) {
							case kBuilding_Steg:
								if (sizeof($btype->require_building) == 0) $btype->require_building = array(0=>kBuilding_Harbor,kBuilding_Steg);
								$piclist = "";
								foreach ($btype->require_building as $b) $piclist .= GetBuildingTypeLink($b,$f_x,$f_y);
								JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$piclist muss direkt daneben sein");
							break;
							case kBuilding_SeaWall:
							case kBuilding_SeaGate:
								if (sizeof($btype->require_building) == 0) $btype->require_building = array(0=>kBuilding_SeaWall,kBuilding_Wall);
								$piclist = "";
								foreach ($btype->require_building as $b) $piclist .= GetBuildingTypeLink($b,$f_x,$f_y);
								JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$piclist muss direkt daneben sein");
							break;
							case kBuilding_Harbor:
								$reqpic = "<img border=0 src=\"".g($gTerrainType[kTerrain_Sea]->gfx)."\">";
								JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"$reqpic muss in der n�he sein");
							break;
							default:
								JSAddInfoMessage_BuildError($btype,$f_x,$f_y,"kann hier nicht gebaut werden");
						}
						continue;
					}

					sql("LOCK TABLES `phperror` WRITE, `terrain` READ,`construction` WRITE,`sqlerror` WRITE,  `building` WRITE");
				
					//if this player has no building, then the hq is build imediatly
					if(!UserHasBuilding($gUser->id,kBuilding_HQ) && $typeid == kBuilding_HQ && isPositionInBuildableRange(intval($f_x),intval($f_y))) {
						$o = null;
						$o->user = $gUser->id;
						$o->x = intval($f_x);
						$o->y = intval($f_y);
						$o->hp = $gBuildingType[kBuilding_HQ]->maxhp;
						$o->type = kBuilding_HQ;
						
						sql("INSERT INTO `building` SET ".obj2sql($o));
						$gJSCommands[] = "parent.map.location.href = parent.map.location.href;";
						$gJSCommands[] = "parent.navi.location.href = parent.navi.location.href;";
					} else if ($typeid != kBuilding_HQ) {
						$mycon = false;
						$mycon->user = $gUser->id;
						$mycon->x = intval($f_x);
						$mycon->y = intval($f_y);
						$mycon->type = $typeid;
						
						$mycon->priority = intval(sqlgetone("SELECT MAX(`priority`) FROM `construction` WHERE `user` = ".$gUser->id)) + 1;
						$r = sql("SELECT `id` FROM `construction` WHERE `x`=".$mycon->x." AND `y`=".$mycon->y." AND `user`=".$mycon->user);
						if(mysql_num_rows($r) == 0)	sql("INSERT INTO `construction` SET ".obj2sql($mycon));
						$gJSCommands[] = "parent.map.JSInsertPlan(".$mycon->x.",".$mycon->y.",".$mycon->type.",0);";
					}
					sql("UNLOCK TABLES");
				}
			}
		break;
		case "cancel":
			
			require_once("../lib.army.php");
			$wp = sqlgetobject("SELECT * FROM `waypoint` WHERE `x` = ".intval($f_x)." AND  `y` = ".intval($f_y)." AND `army` = ".intval($f_cancel_wp_armyid));
			if ($wp) {
				$army = sqlgetobject("SELECT * FROM `army` WHERE `id` = ".$wp->army);
				if ($army && (intval($army->flags) & kArmyFlag_SelfLock) && $army->user == $gUser->id) break;
				if ($army && $wp->priority > 0 && cArmy::CanControllArmy($army,$gUser)) {
					cArmy::ArmyCancelWaypoint($army,$wp);	
					echo "Wegpunkt gel�scht";
					JSRefreshArmy($army);
				}
			} else {
				$f_brushrad = 0; // building brushsize forced to 0
				$fields = GetBrushFields($f_x,$f_y,$f_brushrad,100,$f_brush,$f_brushline,$f_brushlastx,$f_brushlasty);
				foreach ($fields as $posarr) {
					list($f_x,$f_y) = $posarr;
				
					$con = sqlgetobject("SELECT * FROM `construction` WHERE `x` = ".intval($f_x)." AND  `y` = ".intval($f_y)." AND `user` = ".$gUser->id);
					if ($con) {
						$con = CancelConstruction($con->id,$gUser->id);
						if ($con) {
							echo "Bauplan abgebrochen <!-- 2 -->";
							$gJSCommands[] = "parent.map.JSRemovePlan(".intval($f_x).",".intval($f_y).");";
						}
					}
				}
			}
		break;
		case "cancelconstructionplan":
			if (isset($f_buildnext)) {
				// move construction to the front of the building-queue and adjust all priorities
				$con = sqlgetobject("SELECT * FROM `construction` WHERE `id` = ".intval($f_id)." LIMIT 1");
				if ($con && $gUser->id == $con->user) {
					sql("UPDATE `construction` SET `priority` = `priority`+1 WHERE `user`=".$con->user." AND `priority`<".$con->priority);
					sql("UPDATE `construction` SET `priority` = 1 WHERE `id`=".$con->id);
				}
			}
			if (isset($f_cancelone)) {
				$con = CancelConstruction(intval($f_id),$gUser->id);
				if ($con) {
					echo "Bauplan abgebrochen <!-- 2 -->";
					$gJSCommands[] = "parent.map.JSRemovePlan(".$con->x.",".$con->y.");";
				}
			}
			if (isset($f_sure) && isset($f_cancelall))
				sql("DELETE FROM `construction` WHERE `user` = ".$gUser->id);
		break;
		case "removebuilding": // unfinished building and completed building !
			if (isset($f_sure)) {
				require_once("../lib.building.php");
				$building = sqlgetobject("SELECT * FROM `building` WHERE `id` = ".intval($f_id));
				if ($building && $building->user == $gUser->id) {
					cBuilding::removeBuilding($f_id,$gUser->id,true);
					echo "Geb�ude gel�scht";
					$gJSCommands[] = "parent.map.location.href = parent.map.location.href;";
					$gJSCommands[] = "parent.navi.location.href = parent.navi.location.href;";
					if ($building->type == kBuilding_HQ)
						Redirect(Query("../info.php?sid=?"));
				}
			}
		break;
		case "planupgrades":
			require_once("../lib.building.php");
			$building = sqlgetobject("SELECT * FROM `building` WHERE `id` = ".intval($f_id));
			if ($building && $building->user == $gUser->id) {
				cBuilding::SetBuildingUpgrades($f_id,$f_upcount);
			}
		break;
		case "testmode":
			if (!kZWTestMode) break;
			if (isset($f_fullres)) {
				$arr = array();
				foreach ($gRes as $n=>$f) $arr[] = "`$f` = `max_$f`";
				$arr[] = "`pop` = `max_pop`";
				sql("UPDATE `user` SET ".implode(" , ",$arr)." WHERE `id` = ".$gUser->id);
				$gUser = sqlgetobject("SELECT * FROM `user` WHERE `id` = ".$gUser->id);
			} // endif isset
		break;
		default:
			if ($gUser->admin || (intval($gUser->flags) & kUserFlags_TerraFormer)) include("infoadmincmd.php");
			else echo "infocommand : unknown non-building command $f_do<br>";
		break;
	}
	$info_message = rob_ob_end();
}

$gInfoObjects = array();
$gInfoBuildingScriptClassNames = array();

// register building classes
foreach ($gBuildingType as $o) if (!array_key_exists($o->script,$gInfoBuildingScriptClassNames)) {
	if (!$o->script) continue;
	$loadscript = $o->script;
	if (!file_exists($o->script)) $loadscript = false;
	if (!$loadscript && file_exists($o->script.".php")) $loadscript = $o->script.".php";
	if ($loadscript) {
		$gClassName = false;
		require_once($loadscript);
		if ($gClassName) {
			$gInfoBuildingScriptClassNames[$o->script] = $gClassName;
			$gInfoObjects[] = new $gClassName(null);
		}
	}
}

// register other classes
require_once("army.php");
$gInfoObjects[] = new cInfoArmy();
$gInfoObjects[] = new cInfoBuilding();


// execute commands
function walk_command (&$item, $key) { $item->command(); }
rob_ob_start();
array_walk($gInfoObjects,"walk_command");
$content = rob_ob_end();
if (!empty($content)) RegisterInfoTab("info",$content,200);


// now that the commands have had their chance to update stuff, get object data
$xylimit = "`x` = ".$f_x." AND `y` = ".$f_y;
$gMapBuilding = sqlgettable("SELECT * FROM `building` WHERE ".$xylimit);
$gMapArmy = sqlgettable("SELECT * FROM `army` WHERE ".$xylimit,"id");
$gMapCons = sqlgettable("SELECT * FROM `construction` WHERE ".$xylimit." AND `user` = ".$gUser->id);
$gMapWaypoints = sqlgettable("SELECT * FROM `waypoint` WHERE ".$xylimit);
$terrain = cMap::StaticGetTerrainAtPos($f_x,$f_y);
$terraintype = sqlgetobject("SELECT * FROM `terraintype` WHERE `id` = ".($terrain?$terrain:kTerrain_Grass));
$gItems = sqlgettable("SELECT * FROM `item` WHERE `army`=0 AND `building`=0 AND ".$xylimit." ORDER BY `type`");
$gArmy = cArmy::getMyArmies(FALSE,$gUser->id);

// create instances
foreach ($gMapBuilding as $o) {
	if(!empty($gBuildingType[$o->type]->script))$classname = $gInfoBuildingScriptClassNames[$gBuildingType[$o->type]->script];
	else $classname = null;
	if (!empty($classname)) $gInfoObjects[] = new $classname($o);
	else	$gInfoObjects[] = new cInfoBuilding($o);
}
foreach ($gMapArmy as $o)		
	$gInfoObjects[] = new cInfoArmy($o);


/* tab corner (right-top) */ 
if (!isset($f_blind)) {
	// coordinates
	$gClickCoords = "<a href=\"".Query("../".kMapScript."?x=".$f_x."&y=".$f_y."&sid=?")."\" target=\"map\">(".$f_x.",".$f_y.")</a>";
	$gInfoTabsCorner .= $gClickCoords;
}

/* terrain info*/ 
if (!isset($f_blind)) {
	/* TERRAIN , wegpunkt setzen, magie */ 
	rob_ob_start();
	$terrainpic = "<img class=\"info_terrainpic\" alt=\"".($terraintype->name)."\" title=\"".($terraintype->name)."\" src=\"".g($terraintype->gfx,"we")."\">";
	?>
	
	<?=$terrainpic?>
	<?=$gClickCoords?> <?=($terraintype->name)?>  <?=($terraintype->descr)?>
	<?php $uhrtip = "Geschwindigkeit: Wartezeit in s bis der n�chste Schritt m�glich ist";?>
	<img alt="<?=$uhrtip?>"  title="<?=$uhrtip?>" src="<?=g("sanduhrklein.gif")?>">:<?=$terraintype->speed?>s <br>
	
	<?php if (0) { // terrain mod currently unused, so hide ?>	
	Mod:(a*<?=round($terraintype->mod_a,2)?>|v*<?=round($terraintype->mod_v,2)?>|f*<?=round($terraintype->mod_f,2)?>) <?=cText::Wiki("kampf_mod")?> 
	<?php }?>
	
	<?php if($gUser->admin){ ?>
		<a href="<?=query("adminterrain.php?id=$terraintype->id&sid=?")?>"><img alt="terrain" title="terrain" src="<?=g("icon/admin.png")?>" border=0></a>
		<?php if ($hellhole=sqlgetobject("SELECT * FROM `hellhole` WHERE `x` = ".intval($f_x)." AND `y` = ".intval($f_y)." LIMIT 1")) {?>
			<a href="<?=query("adminhellhole.php?id=$hellhole->id&sid=?")?>"><img alt="Hellhole" title="Hellhole" src="<?=g("icon/admin.png")?>" border=0></a>
			<a href="<?=query("?x=?&y=?&do=hellhole_admin_think&hellhole=$hellhole->id&sid=?")?>">(hellhole:think)</a>
		<?php } else { ?>
			<a href="<?=query("?x=?&y=?&do=hellhole_admin_create&sid=?")?>">(create_hellhole)</a>
		<?php } ?>
	<?php } ?>

	<br>
	
	<?php /* mapmarks */ ?>
	<?php $mapmark = sqlgetobject("SELECT * FROM `mapmark` WHERE `user` = ".$gUser->id." AND `x` = ".intval($f_x)." AND `y` = ".intval($f_y));?>
	<form method="post" action="<?=Query("?sid=?&x=?&y=?")?>">
		<input type="hidden" name="do" value="mapmark">
		<?php if ($mapmark) {?>
		"<?=$mapmark->name?>" <input type="submit" name="del" value="l�schen">
		<?php } else { // ?>
		<input type="text" name="mapmarkname" value="neue Karten-Markierung" style="width:160px"> <input type="submit" name="new" value="eintragen">
		<?php } // endif?>
		<?=cText::Wiki("MapMark")?>
	</form>

	<br>
	
	<?php /* wegpunkte */ ?>
	<?php $count = 0; foreach($gArmy as $army) if (cArmy::CanControllArmy($army,$gUser)) ++$count;?>
	<?php if ($count > 0) { ?>
	Wegpunkte setzen : 
	<FORM METHOD=POST name="setwpform" ACTION="<?=Query("?sid=?&x=?&y=?")?>">
	<INPUT TYPE="hidden" NAME="do" VALUE="setwaypoint">
	<INPUT TYPE="hidden" NAME="gfxbuttonmode" VALUE="0">
	<SELECT NAME="army">
		<?php foreach($gArmy as $army) if (cArmy::CanControllArmy($army,$gUser)) {?>
			<OPTION VALUE=<?=$army->id?> <?=($army->id == $gUser->lastusedarmy)?"selected":""?>><?=$army->name?> (<?=$army->owner?>)</OPTION>
		<?php }?>
	</SELECT>
	<a href="javascript:document.getElementsByName('gfxbuttonmode')[0].value = 'wp'; document.getElementsByName('setwpform')[0].submit();"><img src='<?=g("tool_wp.png")?>' alt="Wegpunkt" title="Wegpunkt" border=0></a>
	<a href="javascript:document.getElementsByName('gfxbuttonmode')[0].value = 'route'; document.getElementsByName('setwpform')[0].submit();"><img src='<?=g("tool_route.png")?>' alt="Route" title="Route" border=0></a>
	</FORM>
	<?php }?>
	
	<br>
	
	<?php /* oeffentliches portal */ ?>
	<?php
	if (count($gMapArmy) == 0 && count($gMapBuilding) == 0 && count($gItems) == 0 && count($gMapCons) == 0 && count($gMapWaypoints) == 0) {
		$x = intval($f_x); $y = intval($f_y);
		$portal = sqlgetobject("SELECT *,((`x`-($x))*(`x`-($x)) + (`y`-($y))*(`y`-($y))) as `dist` FROM `building` WHERE `type` = ".kBuilding_Portal." AND `user` = 0 ORDER BY `dist` LIMIT 1");
		if ($portal) echo "N�chstes �ffentliches Portal : ".opos2txt($portal)."<br>";
	}
	?>
	
	<?php /* testmode */ ?>
	<?php if (kZWTestMode) { ?>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="testmode">
		<INPUT TYPE="submit" NAME="fullres" VALUE="fullres">
		</FORM>
	<?php } // endif ?>
	
	<?php 
	RegisterInfoTab($terrainpic."",rob_ob_end());
}

/*waypoint info*/
if (!isset($f_blind)) {
	$c = 0; foreach ($gMapWaypoints as $wp) if ($wp->priority > 0) ++$c;
	if ($c > 0) {
		rob_ob_start();
		foreach ($gMapWaypoints as $wp) if ($wp->priority > 0) {
			$army = sqlgetobject("SELECT * FROM `army` WHERE `id` = ".$wp->army);
			if (!cArmy::CanControllArmy($army,$gUser)) continue;
			$wpnumber = sqlgetone("SELECT COUNT(*) FROM `waypoint` WHERE `army` = ".$wp->army." AND `priority` < ".$wp->priority);
			?>
			<img alt="army" src="<?=g("army.png")?>">
			Wegpunkt <?=$wpnumber?> von <a href="<?=Query("?sid=?&x=".$army->x."&y=".$army->y)?>"><?=$army->name?></a>.
			<a href="<?=Query("?sid=?&x=?&y=?&do=delwaypoint&id=".$wp->id)?>">(l&ouml;schen)</a>
			<a href="<?=Query("?sid=?&x=?&y=?&do=delallwaypointafter&id=".$wp->id)?>">(alle ab diesem l&ouml;schen)</a>
			<?php /* ***** Warten ***** */ ?>
			<form method="post" action="<?=Query("?sid=?&x=?&y=?&do=wpaction&target=".$wp->id."&army=".$army->id)?>">
			<input type="text" name="warten_dauer" value="0" style="width:30px">Minuten
			<input type="submit" name="warten" value="warten">
			</form>
			<?php $wait = sqlgetobject("SELECT * FROM `armyaction` WHERE 
				`cmd` = ".ARMY_ACTION_WAIT." AND `army` = ".$wp->army." AND `param1` = ".$wp->id);
			?>
			<?php if ($wait) {?>
			Hier soll <?=floor($wait->param2/60)?> Minuten gewartet werden.<br>
			<?php } // endif?>
			<hr>
			<?php 
		}
		$content = rob_ob_end();
		if (!empty($content)) RegisterInfoTab("Wegpunkte",$content);
	}
}
	
	
$planpic = "<img class=\"info_planpic\" src=\"".g("constructionplan.png")."\">"; 
	

/* construction info */
if (!isset($f_blind)) foreach($gMapCons as $gObject) {
	rob_ob_start();
	$btype = $gBuildingType[$gObject->type];
	?>
	
	<table cellspacing=0 cellpadding=0>
	<tr><td rowspan=2 valign="top"><img src="<?=g(kConstructionPlanPic)?>" border=1></td>
		<td rowspan=2 valign="top">-&gt;</td>
		<td rowspan=2 valign="top" width=30><img src="<?=GetBuildingPic($btype,$gObject->user,0)?>" border=1></td>
		<td><?=$btype->name?></td>
	</tr>
	<tr><td colspan=2><?=$btype->descr?></td></tr>
	</table>
	
	<?php if ($gObject->user == $gUser->id) {?>
		<?php PrintBuildTimeHelp($gObject->x,$gObject->y,$gObject->type,$gObject->priority); ?>
		<table border=1 cellspacing=0 rules="all">
		<tr>
			<?php foreach($gRes as $n=>$f)echo '<td align="center"><img src="'.g('res_'.$f.'.gif').'"></td>'; ?>
			<td align="center"><img src="<?=g("sanduhrklein.gif")?>"></td>
		</tr>
		<tr>
			<?php foreach($gRes as $n=>$f) echo '<td align="right">'.round($gBuildingType[$gObject->type]->{"cost_".$f},0).'</td>'; ?>
			<td align="right"><?=Duration2Text(GetBuildTime($gObject->x,$gObject->y,$gObject->type,$gObject->priority,$gObject->user))?></td>
		</tr>
		</table>
		<?php if (GetBuildNewbeeFactor($gObject->type,$gObject->priority,$gObject->user) < 1.0) {?>
			NewbeeFaktor: die Bauzeit der ersten <?=kSpeedyBuildingsLimit?> steigt langsam von 0 bis normal an.<br>
			Dies gilt nur f�r folgende Geb�udetypen : 
			<?php 
				foreach ($gBuildingType as $o) 
					if (in_array($o->id,$gSpeedyBuildingTypes)) 
						echo "<img src='".GetBuildingPic($o->id,$gUser,0)."' title='".$o->name."' alt='".$o->name."'>"; 
			?>
			<br>
		<?php } // endif?>
		TechFaktor: kann mit der Forschung "Architektur" (in der Werkstatt) gesenkt werden.<br>

		<?php 		
		$buildingtype = $gBuildingType[$gObject->type];
		$minprio = sqlgetone("SELECT MIN(`priority`) FROM `construction` WHERE `user` = ".$gUser->id);

		if ($gObject->priority > $minprio) {	
			$prevcon = sqlgetobject("SELECT * FROM `construction` WHERE `user` = ".$gUser->id." AND `priority` < ".$gObject->priority." ORDER BY `priority` DESC LIMIT 1");
			$firstcon = sqlgetobject("SELECT * FROM `construction` WHERE `user` = ".$gUser->id." AND `priority` = ".$minprio);
		}
		$nextcon = sqlgetobject("SELECT * FROM `construction` WHERE `user` = ".$gUser->id." AND `priority` > ".$gObject->priority." ORDER BY `priority` LIMIT 1");
		?>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="cancelconstructionplan">
		<INPUT TYPE="hidden" NAME="id" VALUE="<?=$gObject->id?>">
		<INPUT TYPE="submit" NAME="buildnext" VALUE="als n&auml;chstes bauen">
		<INPUT TYPE="submit" NAME="cancelone" VALUE="abbrechen">
		</FORM>
		<a href="<?=Query("bauplan.php?sid=?")?>"><b>Baupl&auml;ne</b></a>&nbsp;
		<a href="<?=Query("kosten.php?sid=?")?>"><b>Kosten</b></a><br>
		<?php if (isset($prevcon)) {?>
			<a href="<?=Query("?sid=?&x=".$firstcon->x."&y=".$firstcon->y)?>">zum ersten Plan</a><br>
			<a href="<?=Query("?sid=?&x=".$prevcon->x."&y=".$prevcon->y)?>">zum vorherigen Plan</a><br>
		<?php }?>
		<?php if (isset($nextcon)) {?>
			<a href="<?=Query("?sid=?&x=".$nextcon->x."&y=".$nextcon->y)?>">zum n&auml;chsten Plan</a><br>
		<?php }?>
	<?php }?>
	<hr>
	<?php 

	RegisterInfoTab($planpic."Bauplan",rob_ob_end(),1);
}




/* build list */
if (!isset($f_blind)) {
	if (count($gMapBuilding) == 0 && !OwnConstructionInProcess($f_x,$f_y) && InBuildCross($f_x,$f_y,$gUser->id)) {
		$userhashq = UserHasBuilding($gUser->id,kBuilding_HQ);
		rob_ob_start();
		$timetip = "Effektive Bauzeit";
		?>
		<?php PrintBuildTimeHelp($f_x,$f_y); ?>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="build">
		<table border=1 cellspacing=0 rules="all">
		<tr>
			<th><?=$planpic?></th>
			<th></th>
			<?php foreach($gRes as $n=>$f)echo '<th><img src="'.g('res_'.$f.'.gif').'"></th>'; ?>
			<th><img src="<?=g("sanduhr.gif")?>" alt="<?=$timetip?>" alt="<?=$timetip?>"></th>
			<th></th>
		</tr>
		<?php
		foreach ($gBuildingType as $o) if (CanBuildHere($f_x,$f_y,$o->id,$gUser->id)) {
			if ($userhashq && $o->id == kBuilding_HQ) continue;
			if (!$userhashq && $o->id != kBuilding_HQ) continue;
			$hasreq = HasReq($o->req_geb,$o->req_tech,$gUser->id);
			if (!$hasreq)
					$bb=GetBuildingTypeLink($o->id,$f_x,$f_y,"<font color='red'>Anforderungen</font>");
			else	$bb="<INPUT TYPE='submit' NAME='build[".$o->id."]' VALUE='bauen'>";
			$buildtime = GetBuildTime($f_x,$f_y,$o->id);
			?>
			<tr>
				<td align=center><?=GetBuildingTypeLink($o->id,$f_x,$f_y)?></td>
				<td><?=cText::Wiki("building",$o->id)?><?=GetBuildingTypeLink($o->id,$f_x,$f_y,$o->name)?></td>
				<?php foreach($gRes as $n=>$f) echo ($o->id == kBuilding_HQ)?0:('<td align=right>'.$o->{"cost_".$f}.'</td>'); ?>
				<td align=right nowrap><?=($buildtime>0)?Duration2Text($buildtime):"sofort fertig"?></td>
				<td><?=$bb?></td>
				</tr>
			<tr>
			<?php
		}
		?>
		</table>
		</FORM>
			
		<?php 
		RegisterInfoTab($planpic."Bauen",rob_ob_end(),1);
	}
}



if (!isset($f_blind)) if ($gUser->admin) {
	/* admin terrain set */
	$adminprio = false;
	
	rob_ob_start();
	?>
	<?php if (count($gPHP_Errors) > 0) { echo "<h3>PHP-ERRORS</h3>";vardump2($gPHP_Errors); }?>
	<br><br><hr>
	<?php /* see infoadmincmd.php for execution */?>
	<?php $maptemplates = sqlgettable("SELECT `id`,CONCAT('(',`cx`,',',`cy`,')',`name`) as `name` FROM `maptemplate` ORDER BY `name`");?>
	<form method="post" action="<?=Query("?sid=?&x=?&y=?")?>">
		<input type="hidden" name="do" value="admin_maptemplate">
		MapTemplate
		<select name="maptemplate">
			<?=PrintObjOptions($maptemplates,"id","name")?>
		</select>
		<input type="submit" name="use" value="anwenden">
		<input type="submit" name="del" value="l�schen"><br>
		x<input type="text" name="x1" value="<?=$f_x-5?>" style="width:30px">
		y<input type="text" name="y1" value="<?=$f_y-5?>" style="width:30px">
		x<input type="text" name="x2" value="<?=$f_x+5?>" style="width:30px">
		y<input type="text" name="y2" value="<?=$f_y+5?>" style="width:30px">
		<input type="text" name="name" value="neu">
		<input type="submit" name="new" value="speichern">
	</form>
	<?php /* zap*/ ?>
	<table><tr><td>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="adminzap">
		<INPUT TYPE="submit" VALUE="zap">
		</FORM>
	</td><td>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="adminruin">
		<INPUT TYPE="submit" VALUE="ruin">
		</FORM>
	</td><td>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="adminremovearmy">
		<INPUT TYPE="submit" VALUE="rm_army">
		</FORM>
	</td><td>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="adminremoveitems">
		<INPUT TYPE="submit" VALUE="rm_items">
		</FORM>
	</td><td>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="adminclear">
		<INPUT TYPE="submit" VALUE="clear">
		</FORM>
	</td><td nowrap>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		 | 
		<INPUT TYPE="hidden" NAME="do" VALUE="adminteleportarmy">
		<INPUT TYPE="text" NAME="dx" VALUE="<?=$f_x?>" style="width:30px">
		<INPUT TYPE="text" NAME="dy" VALUE="<?=$f_y?>" style="width:30px">
		<INPUT TYPE="submit" VALUE="teleportarmy">
		</FORM>
	</td></tr></table>
	
	<?php /* building*/ ?>
	<?php if (count($gMapBuilding) > 0) {?>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="admineditbuilding">
		<INPUT TYPE="hidden" NAME="id" VALUE="<?=$gMapBuilding[0]->id?>">
		Level=<?=IText($gMapBuilding[0],"level",'style="width:30px"')?>,
		HP=<?=IText($gMapBuilding[0],"hp",'style="width:30px"')?>,
		Mana=<?=IText($gMapBuilding[0],"mana",'style="width:30px"')?>,
		Param=<?=IText($gMapBuilding[0],"param",'style="width:60px"')?>
		<INPUT TYPE="submit" VALUE="editbuilding">
		<INPUT TYPE="submit" NAME="adminuserfullres" VALUE="userfullres">
		</FORM>
	<?php }?>
	
	<table><tr><td nowrap>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="gotobuildingid">
		b.id: <input name="id" type="text" size="4" value="0">
		<input type="submit" value="goto">
		|
		</FORM>
	</td><td nowrap>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="gotoarmyid">
		armyid: <input name="id" type="text" size="4" value="0">
		<input type="submit" value="goto">
		|
		</FORM>
	</td><td nowrap>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="genriver">
		L:<input name="steps" type="text" size="4" value="30">
		<input type="submit" value="erstelle fluss">
		</FORM>
	</td></tr></table>
	
	<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
	<INPUT TYPE="hidden" NAME="do" VALUE="terraingen">
	<?php $terraintypes = sqlgettable("SELECT * FROM `terraintype`");?>
	$type=<SELECT name="type"><?php PrintObjOptions($terraintypes,"id","name",isset($f_type)?$f_type:4)?></SELECT>
	$dur=<input name="dur" type="text" size="4" value="<?=isset($f_dur)?$f_dur:60?>">
	$split=<input name="split" type="text" size="4" value="<?=isset($f_split)?$f_split:0?>">
	$ang=<input name="ang" type="text" size="4" value="<?=isset($f_ang)?$f_ang:180?>">
	<input type="submit" value="terraingen"><br>
	f&uuml;r fl&uuml;sse am besten $ang=20-40 und $steps=$dur/3 oder sowas<br>
	</FORM>
	
	
	<?php if (kAdminCanAccessMysql) {?>
		<form method="post" action="<?=Query("?sid=?&x=?&y=?")?>">
			<INPUT TYPE="hidden" NAME="do" VALUE="adminsql">
			<textarea name="sqlcommand" cols=60 rows=5><?=isset($f_sqlcommand)?htmlspecialchars($f_sqlcommand):""?></textarea>
			<input type="submit" value="mysql_query">
		</form>
		<?php if (isset($gAdminSQLResult)) { $adminprio = 200; $first = true; ?>
			<table border=1>
			<?php foreach ($gAdminSQLResult as $o) {  $arr = obj2arr($o); ?>
			<?php if ($first) { $first = false;?>
			<tr>
				<?php foreach ($arr as $n=>$v) {?>
				<th><?=htmlspecialchars($n)?></th>
				<?php } // endforeach?>
			</tr>
			<?php } // endif?>
			<tr>
				<?php foreach ($arr as $n=>$v) {?>
				<td><?=htmlspecialchars($v)?></td>
				<?php } // endforeach?>
			</tr>
			<?php } // endforeach?>
			</table>
		<?php } // endif?>
	<?php } // endif?>
	
	<?php 
	RegisterInfoTab("Admin",rob_ob_end(),$adminprio);
}


/*  INFO CLASSES */ 
if (!isset($f_blind)) {
	// direct output should always be empty ! buildings register new tabs instead
	rob_ob_start();
	foreach ($gInfoObjects as $o) if ($o) {
		$o->generate_tabs(); 
	}
	$content = rob_ob_end();
	if (!empty($content)) RegisterInfoTab("Infos",$content,100);
}
	
/* gegenst�nde */
if (!isset($f_blind)) if(sizeof($gItems)>0) {
	$armyid = 0;
	foreach($gMapArmy as $a) {$armyid = $a->id;$armyowner = $a->user;break;}
	$canpickone = false;
	rob_ob_start();
	?>
	<table border=1 cellspacing=0>
	<?php foreach($gItems as $i) if ($i->amount >= 1.0) {?>
		<tr>
		<?php if($armyid && cArmy::CanControllArmy($armyid,$gUser))  if (!(intval($gItemType[$i->type]->flags) & kItemFlag_NoPickup)) { $canpickone = true;?>
		<td><a href="<?=query("?sid=?&x=?&y=?&do=itempick&item=$i->id&army=$armyid")?>">
			<img src="<?=g("pick.png")?>" border=0 alt="einsammeln" title="einsammeln">
			</a></td>
		<?php }?>
		<td align="right"><?=ktrenner(floor($i->amount))?></td>
		<td><img title="<?=$gItemType[$i->type]->name?>" alt="<?=$gItemType[$i->type]->name?>" src="<?=g($gItemType[$i->type]->gfx)?>"></td>
		<td><?=$gItemType[$i->type]->name?></td>
		<td><?=$gItemType[$i->type]->descr?></td>
		</tr>
	<?php }?>
	</table>
	<?php if ($canpickone) {?>
		<a href="<?=query("?sid=?&x=?&y=?&do=itempickall&army=$armyid")?>">
			<img src="<?=g("pick.png")?>" border=0 alt="einsammeln" title="einsammeln">alles einsammeln
		</a>
	<?php } // endif
	RegisterInfoTab("Gegenst�nde",rob_ob_end(),4);
} // endif armyitems count > 0 


// magic button
if (!isset($f_blind)) if (sqlgetone("SELECT COUNT(`id`) FROM `building` WHERE `type`=".$gGlobal['building_runes']." AND `user`=".$gUser->id." GROUP BY `type`")) {
	$head = "";
	$head .= "<span class=\"info_magic_cast_button\">";
	$head .= "<img alt=\"zaubern\" title=\"zaubern\" border=0 src=\"".g("tool_mana.png")."\">zaubern";
	$head .= "</span>";
	$content = "";
	$content .= "<a href=\"".Query("?sid=?&x=?&y=?&do=choose_cast&button_cast=zaubern")."\">";
	$content .= "(Einen Zauber auf ($f_x,$f_y) wirken...)</a>";
	RegisterInfoTab($head,$content);
}

// anforderungen
if (!isset($f_blind)) {
	rob_ob_start();
	if (isset($f_infotechtype)) 		cInfoReq::PrintTechnology($f_infotechtype);
	if (isset($f_infobuildingtype)) 	cInfoReq::PrintBuilding($f_infobuildingtype);
	if (isset($f_infounittype)) 		cInfoReq::PrintUnit($f_infounittype);
	if (isset($f_infospelltype)) 		cInfoReq::PrintSpell($f_infospelltype);
	$content = rob_ob_end();
	if (!empty($content)) RegisterInfoTab("Information",$content,100);
}


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 transitional//EN"
   "http://www.w3.org/TR/html4/transitional.dtd">
<html>
<head>
<link rel="stylesheet" type="text/css" href="../styles.css">
<link rel="stylesheet" type="text/css" href="<?=GetZWStylePath()?>">
<title>Zwischenwelt - info</title>
<SCRIPT LANGUAGE="JavaScript" type="text/javascript">
<!--
	function AddInfoMessage (html) {
		document.getElementById('dynamicinfomessage').innerHTML = html + document.getElementById('dynamicinfomessage').innerHTML;
	}
	function OnInfoLoad () {
		<?php foreach ($gJSCommands as $cmd) echo $cmd."\n";?>
	}
	function WPMap (army) {
		var x = parent.map.getx();
		var y = parent.map.gety();
		window.open("../minimap.php?mode=wp&sid=<?=$gSID?>&cx="+x+"&cy="+y+"&army="+army,"WPMap","location=no,menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes");
	}
	function setallchecks (name,check) {
		for (var i in document.getElementsByName(name))
			document.getElementsByName(name)[i].checked = check;
	}
	function ActivateInfoTab (tabum) {
		var verfall = 1000 * 60 * 60 * 24 * 365;
		var jetzt = new Date();
		var Auszeit = new Date(jetzt.getTime() + verfall);
		document.cookie = "activeinfotab" + "=" + tabum + "; expires=" + Auszeit.toGMTString() + ";";
		document.cookie = "activeinfotabx" + "=" + <?=intval($f_x)?> + "; expires=" + Auszeit.toGMTString() + ";";
		document.cookie = "activeinfotaby" + "=" + <?=intval($f_y)?> + "; expires=" + Auszeit.toGMTString() + ";";
	}
//-->
</SCRIPT>
</head>
<body onLoad="OnInfoLoad()">

<?php 
if (isset($f_blind)) { // blind modus im dummy frame, fuer schnellere map-click-befehle
	if($info_message!="") {?><div><?=$info_message?></div><hr><?}
	foreach ($gInfoObjects as $o) $o->display();
	echo "</body></html>";
	exit();
}
?>

<?php if (!isset($f_blind)) include("../menu.php");?>

<div id="dynamicinfomessage"></div>
<?php /* info message */ ?>
<?php if($info_message!="") {?><div><?=$info_message?></div><hr><?}?>
<?php if(isset($pastinclude) && is_file($pastinclude))include($pastinclude); ?>

<?php
if ($gInfoTabsSelected == -1) $gInfoTabsSelected = count($gInfoTabs)-1;
if ($gInfoTabsPriority < 100 && isset($_COOKIE["activeinfotab"]) && $_COOKIE["activeinfotabx"] == $f_x && $_COOKIE["activeinfotaby"] == $f_y) {
	$gInfoTabsSelected = intval($_COOKIE["activeinfotab"]);
}
foreach($gInfoTabs as $i=>$v)$gInfoTabs[$i][0] = "<img border=0 src=\"".g("1px.gif")."\" width=1 height=18>".$gInfoTabs[$i][0];
echo GenerateTabs("infotabs",$gInfoTabs,$gInfoTabsCorner,"ActivateInfoTab",$gInfoTabsSelected); // echo "<div class=\"tabpane\">";

$diff = microtime_float()-$infostarttime;
if ($gUser->admin) echo "took ".sprintf("%0.3f",$diff)." seconds";
?>

</body>
</html>
