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


Lock();



$info_message = ""; //ausgabevariable fuer z.b. spells


$guildcommander = FALSE;
if ($gUser->guild > 0) {
	$gc = getGuildCommander($gUser->guild);
	if(in_array($gUser->id,$gc))$guildcommander=TRUE;
}

$f_x = intval($f_x);
$f_y = intval($f_y);
RegenSurroundingNWSE($f_x,$f_y);
$gDoReload = true;


if (!isset($f_building) && !isset($f_army) && isset($f_do)) {
	rob_ob_start();
	switch ($f_do) {
		case "mapmark":
			$mark = array("x"=>intval($f_x),"y"=>intval($f_y),"user"=>$gUser->id);
			if (isset($f_new)) { $mark["name"] = ereg_replace("[&<>]","_",$f_mapmarkname); sql("INSERT INTO `mapmark` SET ".arr2sql($mark)); }
			if (isset($f_del)) sql("DELETE FROM `mapmark` WHERE ".arr2sql($mark," AND "));
			?>
			<script language="javascript">
				parent.navi.location.href = parent.navi.location.href;
			</script>
			<?php
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
				$f_count[$spelltype->id] = 1;
				$f_target = $spelltype->target;
			}
			$pastinclude="magic.php";
			//$gSpellType = sqlgettable("SELECT * FROM `spelltype` WHERE `target`=".intval($f_target)." OR `target`=".MTARGET_PLAYER,"id");
			$result = 0;
			$castspelltype = false;
			$tower = sqlgetobject("SELECT * FROM `building` WHERE `id` = ".intval($f_tower));
			if ($tower->user != $gUser->id) break;
			require_once("lib.spells.php");
			foreach ($gSpellType as $spelltype){
				if ($f_target == $spelltype->target || $spelltype->target == MTARGET_PLAYER)
				if (isset($f_count[$spelltype->id]))
				for ($i=0;$i<intval($f_count[$spelltype->id]);++$i) {
					rob_ob_start();
					$newspell = GetSpellInstance($spelltype->id);
					$r = $newspell->Cast($spelltype,$f_x,$f_y,$f_target,$gUser->id,$tower->id);
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
				if (intval($army->flags) & kArmyFlag_SelfLock) break;
				if ($army && $wp->priority > 0 && cArmy::CanControllArmy($army,$gUser)) {
					cArmy::ArmyCancelWaypoint($army,$wp);	
					echo "Wegpunkt gel�scht";
					?>
					<script language="javascript">
						parent.map.location.href = parent.map.location.href;
					</script>
					<?php
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
					?>
					<script language="javascript">
						parent.map.location.href = parent.map.location.href;
					</script>
					<?php
				}
			}
		break;
		case "build":
			$baulist = GetBuildlist($f_x,$f_y,false,true,false);
			foreach ($f_build as $typeid => $val) {
				if(!isset($baulist[$typeid]) || !$baulist[$typeid])continue;
				$terrain = sqlgetone("SELECT `type` FROM `terrain` WHERE `x` = ".$f_x." AND `y` = ".$f_y);
				$terraintype = $gTerrainType[$terrain?$terrain:kTerrain_Grass];
				//if ($typeid != kBuilding_Bridge && $typeid != kBuilding_GB && !
				if (!$terraintype->buildable && $gBuildingType[$typeid]->terrain_needed != $terraintype->id)	continue;
				if (sqlgetone("SELECT 1 FROM `building` WHERE `x` = ".$f_x." AND `y` = ".$f_y)) continue;
				if (OwnConstructionInProcess($f_x,$f_y)) continue;
				if (!InBuildCross($f_x,$f_y,$gUser->id)) continue;

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
					?>
					<script language="javascript">
						parent.map.location.href = parent.map.location.href;
						parent.navi.location.href = parent.navi.location.href;
					</script>
					<?php
				} else if ($typeid != kBuilding_HQ) {
					$mycon = false;
					$mycon->user = $gUser->id;
					$mycon->x = intval($f_x);
					$mycon->y = intval($f_y);
					$mycon->type = $typeid;
					
					//set param if this is a bridge
					if($mycon->type == kBuilding_Bridge)
						$mycon->param = getBridgeParam($mycon->x,$mycon->y);
					if($mycon->type == kBuilding_GB)
						$mycon->param = getBridgeParam($mycon->x,$mycon->y);
					
					$mycon->priority = intval(sqlgetone("SELECT MAX(`priority`) FROM `construction` WHERE `user` = ".$gUser->id)) + 1;
					$r = sql("SELECT `id` FROM `construction` WHERE `x`=".$mycon->x." AND `y`=".$mycon->y." AND `user`=".$mycon->user);
					if(mysql_num_rows($r) == 0)	sql("INSERT INTO `construction` SET ".obj2sql($mycon));
						
						
					?>
					<script language="javascript">
						parent.map.JSInsertPlan(<?=$mycon->x?>,<?=$mycon->y?>,<?=$mycon->type?>,0);
					</script>
					<?php
				}
				sql("UNLOCK TABLES");
			}
		break;
		case "cancel":
			$con = sqlgetobject("SELECT * FROM `construction` WHERE `x` = ".intval($f_x)." AND  `y` = ".intval($f_y)." AND `user` = ".$gUser->id);
			if ($con) {
				$con = CancelConstruction($con->id,$gUser->id);
				if ($con) {
					echo "Bauplan abgebrochen <!-- 2 -->";
					?>
					<script language="javascript">
						parent.map.JSRemovePlan(<?=intval($f_x)?>,<?=intval($f_y)?>);
					</script>
					<?php
				}
			}
		break;
		case "cancelconstructionplan":
			require_once("../lib.construction.php");
			if (isset($f_buildnext))
				BuildNext(intval($f_id),$gUser->id);
			if (isset($f_cancelone)) {
				$con = CancelConstruction(intval($f_id),$gUser->id);
				if ($con) {
					echo "Bauplan abgebrochen <!-- 2 -->";
					?>
					<script language="javascript">
						parent.map.JSRemovePlan(<?=$con->x?>,<?=$con->y?>);
					</script>
					<?php
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
					?>
					<script language="javascript">
						parent.map.location.href = parent.map.location.href;
						parent.navi.location.href = parent.navi.location.href;
					</script>
					<?php
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
foreach ($gBuildingType as $o) {
	if ($o->script && file_exists($o->script.".php")) {
		$gClassName = false;
		require_once($o->script.".php");
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
array_walk($gInfoObjects,"walk_command");

$xylimit = "`x` = ".$f_x." AND `y` = ".$f_y;
$gMapBuilding = sqlgettable("SELECT * FROM `building` WHERE ".$xylimit);
$gMapArmy = sqlgettable("SELECT * FROM `army` WHERE ".$xylimit,"id");
$gMapCons = sqlgettable("SELECT * FROM `construction` WHERE ".$xylimit." AND `user` = ".$gUser->id);
$gMapWaypoints = sqlgettable("SELECT * FROM `waypoint` WHERE ".$xylimit);

// create instances
foreach ($gMapArmy as $o)		
	$gInfoObjects[] = new cInfoArmy($o);
foreach ($gMapBuilding as $o)	{
	if(!empty($gBuildingType[$o->type]->script))$classname = $gInfoBuildingScriptClassNames[$gBuildingType[$o->type]->script];
	else $classname = null;
	if (!empty($classname)) $gInfoObjects[] = new $classname($o);
	else	$gInfoObjects[] = new cInfoBuilding($o);
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 transitional//EN"
   "http://www.w3.org/TR/html4/transitional.dtd">
<html>
<?php if (isset($f_blind)) { // blind modus im dummy frame, fuer schnellere map-click-befehle
	echo "<head></head><body>";
	if($info_message!="") {?><div><?=$info_message?></div><hr><?}
	foreach ($gInfoObjects as $o) $o->display();
	echo "</body></html>";
	exit();
}
?>
<head>
<link rel="stylesheet" type="text/css" href="../styles.css">
<title>Zwischenwelt - info</title>
<SCRIPT LANGUAGE="JavaScript" type="text/javascript">
<!--
	function WPMap (army) {
		var x = parent.map.getx();
		var y = parent.map.gety();
		window.open("../minimap.php?mode=wp&sid=<?=$gSID?>&cx="+x+"&cy="+y+"&army="+army,"WPMap","location=no,menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes");
	}
//-->
</SCRIPT>
</head>
<body>


<?php include("../menu.php");?>

<!--terrain info-->
<?php
$terrain = sqlgetobject("SELECT * FROM `terrain` WHERE ".$xylimit);
$terraintype = sqlgetobject("SELECT * FROM `terraintype` WHERE `id` = ".($terrain?$terrain->type:1));
$gItems = sqlgettable("SELECT * FROM `item` WHERE `army`=0 AND `building`=0 AND ".$xylimit." ORDER BY `type`");
$gArmy = cArmy::getMyArmies(FALSE,$gUser->id);
?>

<?php /* TERRAIN , wegpunkt setzen, magie */ ?>
<table border=0 cellspacing=0 cellpadding=0 width="100%">
<tr><td valign="top" width=30>
		<img src="<?=g($terraintype->gfx,$terrain->nwse)?>" border=1>
	</td>
	<td valign="top" align="left">
		<table border=0 cellspacing=0 cellpadding=0>
		<tr>
			<td nowrap width=60 valign="bottom"><a href="<?=SessionLink("../".kMapScript."?x=".$f_x."&y=".$f_y)?>" target="map">(<?=$f_x?>,<?=$f_y?>)</a></td>
			<td nowrap><img alt="Geschwindigkeit: Wartezeit in s bis der n�chste Schritt m�glich ist" title="Geschwindigkeit: Wartezeit in s bis der n�chste Schritt m�glich ist" src="<?=g("sanduhrklein.gif")?>">:<?=$terraintype->speed?>s Mod:(a*<?=round($terraintype->mod_a,2)?>|v*<?=round($terraintype->mod_v,2)?>|f*<?=round($terraintype->mod_f,2)?>) <?=cText::Wiki("kampf_mod")?> </td>
			<td nowrap align="left" valign="bottom">&nbsp;</td>
		</tr>
		<tr><td colspan=3><?=($terraintype->name)?>, <?=($terraintype->descr)?>
			<?php if($gUser->admin){ ?>
				<a href="<?=query("adminterrain.php?id=$terraintype->id&sid=?")?>"><img alt="terrain" title="terrain" src="<?=g("icon/admin.png")?>" border=0></a>
				<?php if ($hellhole=sqlgetobject("SELECT * FROM `hellhole` WHERE `x` = ".intval($f_x)." AND `y` = ".intval($f_y)." LIMIT 1")) {?>
					<a href="<?=query("adminhellhole.php?id=$hellhole->id&sid=?")?>"><img alt="Hellhole" title="Hellhole" src="<?=g("icon/admin.png")?>" border=0></a>
					<a href="<?=query("?x=?&y=?&do=hellhole_admin_think&hellhole=$hellhole->id&sid=?")?>">(hellhole:think)</a>
				<?php } else { ?>
					<a href="<?=query("?x=?&y=?&do=hellhole_admin_create&sid=?")?>">(create_hellhole)</a>
				<?php } ?>
			<?php } ?>
			</td></tr>
		</table>
	</td>
	<td valign="top" align="right" nowrap>
		
		
		<?php /* magie */ ?>
		<?if (sqlgetone("SELECT COUNT(`id`) FROM `building` WHERE `type`=".$gGlobal['building_runes']." AND `user`=".$gUser->id." GROUP BY `type`")){?>
		<FORM METHOD=POST ACTION="<?Query("?sid=?&x=?&y=?")?>">
			<INPUT TYPE="hidden" NAME="do" VALUE="choose_cast">
			<table>
				<tr>
					<td>Auf </td>
					<td>	
						<SELECT NAME="mtarget">
							<OPTION VALUE="<?=MTARGET_PLAYER?>" SELECTED> Spieler</OPTION>
							<OPTION VALUE="<?=MTARGET_AREA?>"> Gel&auml;nde</OPTION>
							<OPTION VALUE="<?=MTARGET_ARMY?>"> Armee</OPTION>
							<OPTION VALUE="<?=MTARGET_SELF?>"> sich selbst </OPTION>
						</SELECT>
					</td>
					<td>
						<INPUT TYPE="submit" NAME="button_cast" VALUE="zaubern">
					</td>
				</tr>
			</table>
		</FORM>
		<?}?>
	</td>
</tr>
</table>

<table><tr><td nowrap>
	<!-- mapmarks -->
	<?php $mapmark = sqlgetobject("SELECT * FROM `mapmark` WHERE `user` = ".$gUser->id." AND `x` = ".intval($f_x)." AND `y` = ".intval($f_y));?>
	<form method="post" action="<?=Query("?sid=?&x=?&y=?")?>">
		<input type="hidden" name="do" value="mapmark">
		<?=cText::Wiki("MapMark")?>
		<?php if ($mapmark) {?>
		"<?=$mapmark->name?>" <input type="submit" name="del" value="l�schen">
		<?php } else { // ?>
		<input type="text" name="mapmarkname" value="neue Karten-Markierung" style="width:160px"> <input type="submit" name="new" value="eintragen">
		<?php } // endif?>
	</form>
</td><td nowrap align="right">
	<?php /* wegpunkt setzen */ ?>
	<?php $count = 0; foreach($gArmy as $army) if (cArmy::CanControllArmy($army,$gUser)) ++$count;?>
	<?php if ($count > 0) { ?>
	<FORM METHOD=POST name="setwpform" ACTION="<?=Query("?sid=?&x=?&y=?")?>">
	<INPUT TYPE="hidden" NAME="do" VALUE="setwaypoint">
	<INPUT TYPE="hidden" NAME="gfxbuttonmode" VALUE="0">
	<SELECT NAME="army">
		<?php foreach($gArmy as $army) if (cArmy::CanControllArmy($army,$gUser)) {?>
			<OPTION VALUE=<?=$army->id?> <?=($army->id == $gUser->lastusedarmy)?"selected":""?>><?=$army->name?> (<?=$army->owner?>)</OPTION>
		<?php }?>
	</SELECT>
	<?php if (0) {?>
		<INPUT name="button_wp" TYPE="submit" VALUE="WP">
		<INPUT name="button_route" TYPE="submit" VALUE="R">
	<?php } else { // ?>
		<a href="javascript:document.getElementsByName('gfxbuttonmode')[0].value = 'wp'; document.getElementsByName('setwpform')[0].submit();"><img src='<?=g("tool_wp.png")?>' alt="Wegpunkt" title="Wegpunkt" border=0></a>
		<a href="javascript:document.getElementsByName('gfxbuttonmode')[0].value = 'route'; document.getElementsByName('setwpform')[0].submit();"><img src='<?=g("tool_route.png")?>' alt="Route" title="Route" border=0></a>
	<?php } // endif?>
	</FORM>
	<?php }?>
</td></tr></table>
<hr>


<!--info message-->
<?php if($info_message!="") {?><div><?=$info_message?></div><hr><?}?>

<?php if(isset($pastinclude) && is_file($pastinclude))include($pastinclude); ?>

<?php /* gegenst�nde */ ?>
<?php
$armyid = 0;
foreach($gMapArmy as $a) {$armyid = $a->id;$armyowner = $a->user;break;}
$canpickone = false;
?>
<?php if(sizeof($gItems)>0) {?>
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
<?php } // endif?>
<hr>
<?php } // endif armyitems count > 0 ?>



<!--waypoint info-->
<?php foreach ($gMapWaypoints as $wp) if ($wp->priority > 0) {?>
	<?php $army = sqlgetobject("SELECT * FROM `army` WHERE `id` = ".$wp->army);?>
	<?php if (!cArmy::CanControllArmy($army,$gUser)) continue;?>
	<img alt="army" src="<?=g("army.png")?>">
	Wegpunkt <?=$wp->priority?> von <a href="<?=Query("?sid=?&x=".$army->x."&y=".$army->y)?>"><?=$army->name?></a>.
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
<?php }?>
	
<!--construction info-->
<?php foreach($gMapCons as $gObject) { $btype = $gBuildingType[$gObject->type]; ?>
<table cellspacing=0 cellpadding=0>
<tr><td rowspan=2 valign="top"><img src="<?=g(kConstructionPlanPic)?>" border=1></td>
	<td rowspan=2 valign="top">-&gt;</td>
	<td rowspan=2 valign="top" width=30><img src="<?=g($btype->gfx,"we",0,$gUser->race)?>" border=1></td>
	<td><?=$btype->name?> | Nummer : <?=$gObject->priority?></td>
</tr>
<tr><td colspan=2><?=$btype->descr?></td></tr>
</table>
	<?php $dist = GetBuildDistance($gObject->x,$gObject->y,$gObject->user);?>
	<?php $faktor_dist = GetBuildDistFactor($dist);?>
	<?php $faktor_tech = GetBuildTechFactor($gObject->user);?>
	<?php $faktor_newbee = GetBuildNewbeeFactor($gObject);?>
	<table border=1 cellspacing=0 rules="all">
	<tr>
		<?php foreach($gRes as $n=>$f)echo '<td align="center"><img src="'.g('res_'.$f.'.gif').'"></td>'; ?>
		<td align="center"><img src="<?=g("sanduhrklein.gif")?>"></td>
		<td align="center">Entfernung</td>
		<td align="center">Entf.Faktor</td>
		<td align="center">TechFaktor</td>
		<?php if ($faktor_newbee < 1.0) {?>
			<td align="center">NewbeeFaktor</td>
		<?php } // endif?>
	</tr>
	<tr>
		<?php foreach($gRes as $n=>$f) echo '<td align="right">'.round($gBuildingType[$gObject->type]->{"cost_".$f},0).'</td>'; ?>
		<td align="right"><?=Duration2Text(GetBuildTime($gObject))?></td>
		<td align="center"><?=round($dist,2)?></td>
		<td align="center"><?=round($faktor_dist,2)?></td>
		<td align="center"><?=round($faktor_tech,2)?></td>
		<?php if ($faktor_newbee < 1.0) {?>
			<td align="center"><?=round($faktor_newbee,2)?></td>
		<?php } // endif?>
	</tr>
	</table>
	<?php if ($faktor_newbee < 1.0) {?>
		NewbeeFaktor: die Bauzeit der ersten <?=kSpeedyBuildingsLimit?> steigt langsam von 0 bis normal an.<br>
		Dies gilt nur f�r Folgende Geb�udetypen : 
		<?php 
			foreach ($gBuildingType as $o) 
				if (in_array($o->id,$gSpeedyBuildingTypes)) 
					echo "<img src='".g($o->gfx,"we",0,$gUser->race)."' title='".$o->name."' alt='".$o->name."'>"; 
		?>
		<br>
	<?php } // endif?>
	TechFaktor: kann mit der Forschung "Architektur" (in der Werkstatt) gesenkt werden.<br>
<?php include("construction.php");?>
<hr>
<?php }?>







<!-- INFO CLASSES -->

<?php
	// anforderungen
	if (isset($f_infotechtype)) 		cInfoReq::PrintTechnology($f_infotechtype);
	if (isset($f_infobuildingtype)) 	cInfoReq::PrintBuilding($f_infobuildingtype);
	if (isset($f_infounittype)) 		cInfoReq::PrintUnit($f_infounittype);
	if (isset($f_infospelltype)) 		cInfoReq::PrintSpell($f_infospelltype);
?>

<?php foreach ($gInfoObjects as $o) $o->display(); ?>




<!--build info-->
<?php if (count($gMapBuilding) == 0 && !OwnConstructionInProcess($f_x,$f_y)) {?>
	<?php if (InBuildCross($f_x,$f_y,$gUser->id)) {?>
		<?php
			$dist = GetBuildDistance($f_x,$f_y);
			$newcp = arr2obj(array("user"=>$gUser->id,"x"=>$f_x,"y"=>$f_y,"type"=>$gSpeedyBuildingTypes[0],"priority"=>intval(sqlgetone("SELECT MAX(`priority`) FROM `construction` WHERE `user` = ".$gUser->id))+1));
			$faktor_dist = GetBuildDistFactor($dist);
			$faktor_tech = GetBuildTechFactor($gUser->id);
			$faktor_newbee = GetBuildNewbeeFactor($newcp);
//			$baulist = GetBuildlist($f_x,$f_y);
			$tech = GetTechnologyObject($gGlobal["tech_architecture"],$gUser->id);
		?>
		Entfernung zum Haupthaus oder Lager: <?=sprintf("%0.1f",$dist)?><br>
		Entf.Faktor : <?=sprintf("%0.2f",$faktor_dist)?><br>
		Architekturlevel: <?=$tech->level?><br>
		Techfaktor : <?=round($faktor_tech,2)?><br>
		<?php if ($faktor_newbee < 1.0) {?>
			NewbeeFaktor : <?=round($faktor_newbee,2)?><br>
		<?php } // endif?>
		<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
		<INPUT TYPE="hidden" NAME="do" VALUE="build">
		<table border=1 cellspacing=0 rules="all">
		<tr>
			<th></th>
			<th></th>
			<?php foreach($gRes as $n=>$f)echo '<th><img src="'.g('res_'.$f.'.gif').'"></th>'; ?>
			<th><img src="<?=g("sanduhr.gif")?>"></th>
			<th></th>
		</tr>
		<?php 
			if (UserHasBuilding($gUser->id,kBuilding_HQ,0)){
			$baulist = GetBuildlist($f_x,$f_y);
			foreach ($gBuildingType as $o)if(isset($baulist[$o->id]) && $baulist[$o->id]){ 
			   if($o->special == 0 && $o->id!=1){
				if(!HasReq($o->req_geb,$o->req_tech,$gUser->id) || !CanBuildHere($f_x,$f_y,$o->id)) $bb="<a style='color:red' href='".Query("?sid=?&x=?&y=?&infobuildingtype=".$o->id)."'>Anforderungen</a>";
				else $bb="<INPUT TYPE='submit' NAME='build[".$o->id."]' VALUE='bauen'>";
		?>

			<tr>

			<td align=center>
				<?php $infourl = Query("?sid=?&x=?&y=?&infobuildingtype=".$o->id);?>
				<a href="<?=$infourl?>"><img title="<?=strip_tags($o->descr)?>" alt="<?=strip_tags($o->descr)?>" class="picframe" src="<?=GetBuildingPic($o->id,0,"ns")?>"></a>
			</td>
			<td><?=cText::Wiki("building",$o->id)?><a href="<?=$infourl?>"><?=$o->name?></a></td>

			<?php foreach($gRes as $n=>$f)echo '<td align=right>'.$o->{"cost_".$f}.'</td>'; ?>

			<?php $newcp = arr2obj(array("user"=>$gUser->id,"x"=>$f_x,"y"=>$f_y,"type"=>$o->id,"priority"=>intval(sqlgetone("SELECT MAX(`priority`) FROM `construction` WHERE `user` = ".$gUser->id))+1));?>
			<td align=right nowrap><?=Duration2Text(GetBuildTime($newcp))?></td>

			<td><?=$bb?></td>

			</tr>
			<?php }
		}?>
		
		<?php }else{ 
			$o=$gBuildingType[1];
			$bb="<INPUT TYPE='submit' NAME='build[".$o->id."]' VALUE='bauen'>";
		?>
			<tr>

			<td align=center>
				<?php $infourl = Query("?sid=?&x=?&y=?&infobuildingtype=".$o->id);?>
				<a href="<?=$infourl?>"><img title="<?=strip_tags($o->descr)?>" alt="<?=strip_tags($o->descr)?>" class="picframe" src="<?=GetBuildingPic($o->id,0,"ns")?>"></a>
			</td>
			<td><?=cText::Wiki("building",$o->id)?><a href="<?=$infourl?>"><?=$o->name?></a></td>

			<?php foreach($gRes as $n=>$f)echo '<td align=right>'.$o->{"cost_".$f}.'</td>'; ?>

			<td align=right nowrap><?="sofort fertig"?></td>

			<td><?=$bb?></td>

			</tr>
		<?php }?>
		</table>
		</FORM>
	<?php } else {?>
		Feld kann nicht bebaut werden, kein eigenes Geb&auml;ude in der N&auml;he.<br>
		<?php
		$x = intval($f_x); $y = intval($f_y);
		$portal = sqlgetobject("SELECT *,((`x`-($x))*(`x`-($x)) + (`y`-($y))*(`y`-($y))) as `dist` FROM `building` WHERE `type` = ".kBuilding_Portal." AND `user` = 0 ORDER BY `dist` LIMIT 1");
		if ($portal) echo "N�chstes �ffentliches Portal : ".opos2txt($portal)."<br>";
		?>
	<?php }?>
	<hr>
<?php }?>



<?php if (kZWTestMode) {?>
	<FORM METHOD=POST ACTION="<?=Query("?sid=?&x=?&y=?")?>">
	<INPUT TYPE="hidden" NAME="do" VALUE="testmode">
	<INPUT TYPE="submit" NAME="fullres" VALUE="fullres">
	</FORM>
<?php } // endif?>


<!--admin terrain set-->
<?php if ($gUser->admin) {?>
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
	<!--zap-->
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
	
	<!--building-->
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
<?php }?>

</body>
</html>
