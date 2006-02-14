<?php
//verursachte zu viel cpu last
//if ( extension_loaded('zlib') )ob_start('ob_gzhandler');

require_once("defines.mysql.php");

define("MYSQL_ERROR_LOG",BASEPATH."sqlerror.log");
define("PHP_ERROR_LOG",BASEPATH."phperror.log");

define("MSG_BELEIDIGUNG",BASEPATH."beleidigungen.txt");
define("kSessionTimeout",3600*8);
define("kTypeCacheFile",BASEPATH."tmp/tmp_types.php");
define("kTypeCacheFileDisabled",false);

#use this for unix based systems
ini_set('include_path', ".:".BASEPATH);
#and this one for windows based sysmtes
#ini_set('include_path', ".;".BASEPATH);

define("kPathSwitchTesting",$_SERVER["SCRIPT_FILENAME"]." # ".$_SERVER["PATH_TRANSLATED"]." # ".$_SERVER["HTTP_HOST"]);
//define("kZWTestMode",$_SERVER["HTTP_HOST"]=="localhost" || $_SERVER["HTTP_HOST"]=="dev.zwischenwelt.net-play.de");
define("kZWTestMode",false);
define("kZWTestMode2",kZWTestMode);
define("kZWTestMode_ArmySteps",3);
define("kZWTestMode_BuildingActionSteps",10); // units produced
define("ARMY_MOVE",600); // time a army move is performed
define("kConstructionPic","construction.png");
define("kConstructionPlanPic","constructionplan.png");
define("kTransCP","transcp.gif");

define("kStats_dtime",60*60*12);


define("kProfileArmyLoop",true);
define("kMapTileSize",25);
define("kMapScript","mapjs7.php");
define("kMapNaviScript","mapnavi7.php");
define("kZWStyle_Neutral","zwstyle.css");
define("kJSMapVersion","42"); // mapversion, $gGlobal["typecache_version_adder"] wird immer addiert
define("kStyleSheetVersion","1"); // css version, $gGlobal["typecache_version_adder"] wird immer addiert
define("kDummyFrames",10); // soviele dummy-befehls-empfaenger frames gibt es, viele -> schnell aufeinander folgende mapclicks k�nnen besser bearbeitet werden
?>
