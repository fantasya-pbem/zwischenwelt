// available map-params : gCX,gCY,gLeft,gTop,gSID,gThisUserID,gGFXBase,gBig,gXMid,gYMid,gMapMode,gScroll,gActiveArmyID
// available map-data : gTerrain,gBuildings,gArmies,gItems,gPlans,gOre??
// available globals : gTerrainTypes,gBuildingTypes...
// see also mapnavi_globals.js.php

// TODO : user anzeig : gilde, punktestand, rang...
// TODO : armee - anzeige : gilde,sprechblasen ?
// TODO : portal : maptip : schloss/lock grafik, wenn nicht benutzbar ?
// TODO : localusers : FOF auslesen -> namen einf�rben in maptip
// TODO : geb�ude hp-balken + farbe im maptip
// TODO : schild-text im tooltip ?
// TODO : br�cken an fluss ausrichten !
// TODO : tor nwse bug : connect-to-building : self rausschmeissen
// TODO : tooltip : einheiten reihenfolge stimmt nicht (orderval ?)

// the order in which fields are filled from mapjs7.php
gUsers = new Array(); // filled with function, for string security
gArmies = new Array(); // filled with function, for string security
gBuildingsCache = false;
gTerrainPatchTypeMap = new Array(); // generated from gTerrainPatchType
gTerrainMap = false; // generated from CompileTerrain()
gTerrainMap_raw = false; // generated from CompileTerrain()
gTerrainMap_nwse = false; // generated from CompileTerrain()
gWPMap = false; // generated from CompileWPs
var gXMid,gYMid;
gNWSEDebug = false; // shows typeid and connect-to infos in maptip

kMapTipName = "maptip";
kMapTip_xoff = 29;
kMapTip_yoff = 56;
kJSMapMode_Normal = 0;
kJSMapMode_Plan = 1;
kJSMapMode_Bauzeit = 2;
kJSMapMode_HP = 3;
gLoading = true; // set to true when navi is clicked
gAllLoaded = false; // mouselistener protection
gBigMapWindow = false;
gLastDebugTime = 0;
gProfileLastLine = "";

gBaseLoaded = false; // wether or not the mapjs7.php javascript has finished loading -> when calling MapInit();

function DirToNWSE1 (dx,dy) {
	if (dy < 0 && dx == 0) return "n";
	if (dx < 0 && dy == 0) return "w";
	if (dx > 0 && dy == 0) return "e";
	if (dy > 0 && dx == 0) return "s";
	return false;
}

function CompileWPs () {
	gWPMap = false;
	if (gActiveArmyID == 0) return;
	var movablemask = GetUnitsMovableMask(gArmies[gActiveArmyID].units);
	var i,x,y,dx1,dy1,dx2,dy2,relx,rely;
	var cur,last,step,foot,head,blocked;
	gWPMap = new Array(gCY); // generated from gWPs
	for (y=0;y<gCY;++y) {
		gWPMap[y] = new Array(gCX);
		for (x=0;x<gCX;++x) gWPMap[y][x] = new Array();
	}
	last = false;
	dx1 = 0; // last offset
	dy1 = 0;
	for (i in gWPs) {
		cur = gWPs[i];
		cur.x = parseInt(cur.x);
		cur.y = parseInt(cur.y);
		// wp-source : "1,1;200,1;;1,200;1,1;"
		// ;; means invisible path-parts have been cut out, results in one wp being not an object, but false (see parser)
		// here we process path-parts, (connections) consisting of two waypoints
		if (!last || !cur) { last = cur; continue; }
		for (x=last.x,y=last.y;x!=cur.x||y!=cur.y;) {
			step = GetNextStep(x,y,last.x,last.y,cur.x,cur.y);
			dx2 = step[0] - x; 
			dy2 = step[1] - y;
			// step[0,1] is the next pos,  x,y  is the current pos, write arrow for current pos (here)
			relx = x - gLeft;
			rely = y - gTop;
			//alert("last:"+last.x+","+last.y+"\ncur:"+cur.x+","+cur.y+"\nrel:"+relx+","+rely+"\nd:"+dx2+","+dy2);
			if (dx1 != 0 || dy1 != 0) if (relx >= 0 && rely >= 0 && relx < gCX && rely < gCY) {
				foot = DirToNWSE1(-dx1,-dy1); // direction coming here
				head = DirToNWSE1(dx2,dy2); // direction going from here to next
				// TODO : check if blocked -> red or green
				blocked = (GetPosSpeed(relx,rely,movablemask,gActiveArmyID) == 0) ? "b" : ""; // appended to nwse
				if (foot) gWPMap[relx][rely][gWPMap[relx][rely].length] = (g("mapwp/foot_"+foot+blocked+".png")); else alert("dead foot "+dx1+"."+dy1);
				if (head) gWPMap[relx][rely][gWPMap[relx][rely].length] = (g("mapwp/head_"+head+blocked+".png")); else alert("dead head "+dx2+"."+dy2);
			}
			if (x == step[0] && y == step[1]) { alert("endless!"); break; }
			x = step[0];
			y = step[1];
			dx1 = dx2;
			dy1 = dy2;
		}
		last = cur;
	}
}

// obsolete interface
function getmode() { return gMode;}
function getleft() { return gLeft;}
function gettop() { return gTop;}
function getx() { return gLeft+gXMid;}
function gety() { return gTop+gYMid;}
function getcx() { return gCX;}
function getcy() { return gCY;}


// new interface
function JSGetActiveArmyID() { return gActiveArmyID; }
function JSInsertPlan (x,y,type,priority) {
	var res = new Object();
	res.x = x;
	res.y = y;
	res.type = type;
	res.priority = priority;
	gPlans[gPlans.length] = res;
	RefreshCell(x-gLeft,y-gTop);
}
function JSRemovePlan (x,y) {
	var i;
	for (i in gPlans) if (gPlans[i].x == x && gPlans[i].y == y) {
		gPlans[i] = false;
		RefreshCell(x-gLeft,y-gTop);
		return;
	}
}
function JSInsertItem (x,y,type,amount) {}
function JSInsertArmy (id,x,y,name,type,user,units,items,jsflags) {}
function JSZap (x,y) {}
function JSRuin (x,y) {}
function JSRemoveItems (x,y) {}
function JSAdminClear (x,y) {}
function JSRemoveArmy (x,y) {}
function JSSetTerrain (x,y,type,brushrad) { /* ./infoadmincmd.php:298:  ... more params : line,terraformer-limit.. */ }
function JSSetBuilding (x,y,type,brushrad) { /* ./infoadmincmd.php:352:  ... more params : line,level.. */ }

function JSUpdateNaviPos () {
	//var myarr = new Array(); myarr.pop();
	if (!gBaseLoaded) return;
	if (!gAllLoaded) return;
	if (gBig != null && !gBig && parent.navi != null && parent.navi.updatepos != null) {
		parent.navi.updatepos(gLeft+gXMid,gTop+gYMid);
		parent.navi.SelectArmy(gActiveArmyID);
	}
}

function JSActivateArmy (armyid,wps) {
	gActiveArmyID = armyid;
	gWPs = wps;
	jsWPs();
	ParseArmyData();
	CompileWPs();
	CreateMap();
	JSUpdateNaviPos();
}

// speedup
function jsParseBuildings () {
	var i;
	gBuildings = gBuildings.split(";");	
	gBuildings.length=gBuildings.length-1;
	for (i in gBuildings) {
		gBuildings[i] = gBuildings[i].split(",");
		var res = new Object();
		res.x = gBuildings[i][0];
		res.y = gBuildings[i][1];
		res.type = gBuildings[i][2];
		res.user = gBuildings[i][3];
		res.level = gBuildings[i][4];
		res.hp = gBuildings[i][5];
		res.construction = gBuildings[i][6];
		res.jsflags = gBuildings[i][7];			
		gBuildingsCache[res.y-gTop+1][res.x-gLeft+1] = res;
	}
}

function ParseArmyData () {
	// parse and summarize units in armies (summoned units are added to normal units)
	var i;
	for (i in gArmies) {
		gArmies[i].units = ParseTypeAmountList(gArmies[i].unitstxt);
		gArmies[i].items = ParseTypeAmountList(gArmies[i].itemstxt);
	}
}

// executed onLoad, parse data, CreateMap() when finished
function MapInit() {
	gBaseLoaded = true;
	profiling("starting init");
	
	var i,j,x,y;
	gXMid=Math.floor(gCX/2);
	gYMid=Math.floor(gCY/2);
	
	JSUpdateNaviPos();
	
	profiling("parse terrain");
	// parse data
	gTerrain = gTerrain.split(";");							
	for (i in gTerrain) gTerrain[i] = gTerrain[i].split(",");
	
	profiling("parse buildings");
	// special for speed
	gBuildingsCache = new Array(gCY+2);
	for (y=0;y<gCY+2;++y) {
		gBuildingsCache[y] = new Array(gCX+2);
		for (x=0;x<gCX+2;++x) gBuildingsCache[y][x] = false;
	}
	jsParseBuildings();
	
	
	profiling("parse items");
	jsParseItems();
	profiling("parse plans");
	jsParsePlans();
	profiling("parse BuildSources");
	jsParseBuildSources();
	
	profiling("parse ParseArmyData");
	ParseArmyData();
	profiling("parse Waypoints");
	jsWPs();
	profiling("compile Waypoints");
	CompileWPs();
	
	profiling("parse gTerrainType");
	for (i in gTerrainType) {
		gTerrainType[i].connectto_terrain = gTerrainType[i].connectto_terrain.split(",");
		gTerrainType[i].connectto_building = gTerrainType[i].connectto_building.split(",");
	}
	
	profiling("parse gBuildingType");
	for (i in gBuildingType) {
		gBuildingType[i].connectto_terrain = gBuildingType[i].connectto_terrain.split(",");
		gBuildingType[i].connectto_building = gBuildingType[i].connectto_building.split(",");
	}
	HackCon(); // HACK : hardcoded connections, see mapjs7_globals.js.php
	
	profiling("parse gTerrainPatchType");
	for (i in gTerrainPatchType) {
		var x = gTerrainPatchType[i];
		if (!gTerrainPatchTypeMap[x.here]) // EX UNDEFINED
			gTerrainPatchTypeMap[x.here] = new Array();
		gTerrainPatchTypeMap[x.here][gTerrainPatchTypeMap[x.here].length] = x;	
	}
	
	gTerrainMap = new Array(gCY+2);
	gTerrainMap_raw = new Array(gCY+2);
	gTerrainMap_nwse = new Array(gCY+2);
	for (y=-1;y<gCY+1;++y) {
		gTerrainMap[y+1] = new Array(gCX+2);
		gTerrainMap_raw[y+1] = new Array(gCX+2);
		gTerrainMap_nwse[y+1] = new Array(gCX+2);
	}
	CompileTerrain();
	
	profiling("construct create map");
	CreateMap();
	profiling("done");profiling(""); // double call to finish output
	gLoading = false;
	
	JSUpdateNaviPos();
}

function CompileTerrain () {
	// construct terrain map
	
	profiling("construct terrain, pass1");
	// first pass, simple nwse (with connect to building/terrain...)
	for (y=-1;y<gCY+1;++y) {
		for (x=-1;x<gCX+1;++x) {
			var terraintype = GetTerrainType(x,y);
			gTerrainMap_raw[y+1][x+1] = gTerrainType[terraintype].gfx;
			gTerrainMap_nwse[y+1][x+1] = GetNWSE(gTerrainType[terraintype],x,y);
		}
	}
	
	profiling("construct terrain, pass2");
	// second pass : terrain patches
	for (x=-1;x<gCX+1;++x) for (y=-1;y<gCY+1;++y) {
		var type = GetTerrainType(x,y);
		if (!KeyInArray(type,gTerrainPatchTypeMap)) continue;
		var patches = gTerrainPatchTypeMap[type];
		for (i in patches) {
			var o = patches[i];
			if(	(o.left	==0 || (o.left	>0 && GetTerrainType(x-1,y) == o.left)) &&
				(o.right==0 || (o.right	>0 && GetTerrainType(x+1,y) == o.right)) &&
				(o.up	==0 || (o.up	>0 && GetTerrainType(x,y-1) == o.up)) &&
				(o.down	==0 || (o.down	>0 && GetTerrainType(x,y+1) == o.down)) ) {
				gTerrainMap_raw[y+1][x+1] = o.gfx;
				if (o.left	>0 && x >= 0)	gTerrainMap_nwse[y+1][x+1-1] |= kNWSE_E;
				if (o.right	>0 && x < gCX)	gTerrainMap_nwse[y+1][x+1+1] |= kNWSE_W;
				if (o.up	>0 && y >= 0)	gTerrainMap_nwse[y+1-1][x+1] |= kNWSE_S;
				if (o.down	>0 && y < gCY)	gTerrainMap_nwse[y+1+1][x+1] |= kNWSE_N;
			}
		}
	}
	
	profiling("construct terrain, compile");
	// compile to terrainmap
	for (y=-1;y<gCY+1;++y) {
		for (x=-1;x<gCX+1;++x)
			gTerrainMap[y+1][x+1] = g_nwse(gTerrainMap_raw[y+1][x+1],gTerrainMap_nwse[y+1][x+1]);
	}
}

function ParseTypeAmountList (list) {
	var arr = list.split("|");
	arr.length=arr.length-1;
	var i,res = new Array();
	for (i in arr) {
		var type_amount_pair = arr[i].split(":");
		if (!res[type_amount_pair[0]]) // EX UNDEFINED
			res[type_amount_pair[0]] = 0;
		res[type_amount_pair[0]] += parseInt(type_amount_pair[1]);
	}
	return res;
}

function RefreshCell (relx,rely) {
	if (relx < 0 || rely < 0 || relx >= gCX || rely >= gCY) return;
	document.getElementById("cell_"+relx+"_"+rely).innerHTML = GetCellHTML(relx,rely);
}

// createmap is called as soon as loading is complete
function CreateMap() {
	var row,x,y,i,j;
	
	var maphtml = "<table class=\"map\" border=0 cellpadding=0 cellspacing=0 onMouseout=\"KillTip()\">\n";
	
	
	// maptiles
	for (y=-1;y<gCY+1;++y) {
		profiling("construct map row "+y);
		row = "";
		for (x=-1;x<gCX+1;++x) if (x >= 0 && x < gCX && y >= 0 && y < gCY) {
			// cell
			row += "<td class=\"mapcell\" id=\"cell_"+x+"_"+y+"\">"+GetCellHTML(x,y)+"</td>\n";
		} else {
			// border
			var myclass = "mapborder_" + (y<gYMid ? "n" : (y==gYMid?"":"s")) + (x<gXMid ? "w" : (x==gXMid?"":"e"));
			if ((x < 0 || x >= gCX) && (y < 0 || y >= gCY)) myclass += "_edge";
			
			// arrows for the big and small steps
			var step = 5;
			       if(x+1 == gXMid && y<gYMid){step = 2;myclass = "mapborder_n_small";}
			else if(x-1 == gXMid && y<gYMid){step = 10;myclass = "mapborder_n_big";}
			else if(y+1 == gYMid && x<gXMid){step = 2;myclass = "mapborder_w_small";}
			else if(y-1 == gYMid && x<gXMid){step = 10;myclass = "mapborder_w_big";}
			else if(x+1 == gXMid && y>gYMid){step = 2;myclass = "mapborder_s_small";}
			else if(x-1 == gXMid && y>gYMid){step = 10;myclass = "mapborder_s_big";}
			else if(y+1 == gYMid && x>gXMid){step = 2;myclass = "mapborder_e_small";}
			else if(y-1 == gYMid && x>gXMid){step = 10;myclass = "mapborder_e_big";}
			
			var navx = x<0?-1:(x>=gCX?1:0);
			var navy = y<0?-1:(y>=gCY?1:0);
			var text = (x < 0 || x >= gCX) ? (y+gTop) : (x+gLeft);
			row += "<th class=\"mapborder\"><div class=\""+myclass+"\" onClick=\"nav("+navx+","+navy+","+step+")\"><span>"+text+"</span></div></th>\n";
		}
		maphtml += "\n<tr>"+row+"</tr>\n";
	}
	
	maphtml += "</table>";
	
	profiling("construct map tabs");
	
	var tab_corner = "";
	/*
	tab_corner += "<span class=\"mapscroll\" style='display:none'>";
	tab_corner += "<span class=\"mapscroll_minus\"><a href=\"javascript:mapscroll_minus()\">-</a></span>";
	tab_corner += "<span class=\"mapscroll_plus\"><a href=\"javascript:mapscroll_plus()\">+</a></span>";
	tab_corner += "<input type=\"text\" name=\"mapscroll\" value=\""+gScroll+"\">";
	tab_corner += "</span>";
	*/
	var tab_pre = "";
	tab_pre += "<div class=\"tabs\">";
	tab_pre += "	<div class=\"tabheader\">";
	tab_pre += "		<ul>";
	tab_pre += "			<li class=\""+(gMapMode==kJSMapMode_Normal?	"activetab":"inactivetab")+"\"><a class=\"tabhead\" href=\"javascript:SetMapMode(kJSMapMode_Normal)\">Normal</a></li>";
	tab_pre += "			<li class=\""+(gMapMode==kJSMapMode_Plan?	"activetab":"inactivetab")+"\"><a class=\"tabhead\" href=\"javascript:SetMapMode(kJSMapMode_Plan)\">Pl&auml;ne</a></li>";
	tab_pre += "			<li class=\""+(gMapMode==kJSMapMode_Bauzeit?"activetab":"inactivetab")+"\"><a class=\"tabhead\" href=\"javascript:SetMapMode(kJSMapMode_Bauzeit)\">Bauzeit</a></li>";
	tab_pre += "			<li class=\""+(gMapMode==kJSMapMode_HP?		"activetab":"inactivetab")+"\"><a class=\"tabhead\" href=\"javascript:SetMapMode(kJSMapMode_HP)\">HP</a></li>";
	tab_pre += "		</ul>";
	tab_pre += "		<div class=\"corner\">";
	tab_pre += "			<span>"+gMapModiHelp+"</span>";
	tab_pre += "			<a href=\"javascript:OpenMap(1)\"><img alt=\"bigmap\" title=\"bigmap\" border=0 src=\"gfx/icon/bigmap.png\"></a>";
	tab_pre += "			<a href=\"javascript:OpenMap(2)\"><img alt=\"minimap2\" title=\"minimap2\" border=0 src=\"gfx/icon/minimap2.png\"></a>";
	tab_pre += "			<a href=\"javascript:OpenMap(3)\"><img alt=\"minimap\" title=\"minimap\" border=0 src=\"gfx/icon/minimap.png\"></a>";
	tab_pre += "			<a href=\"javascript:OpenMap(4)\"><img alt=\"creepmap\" title=\"creepmap\" border=0 src=\"gfx/icon/creepmap.png\"></a>";
	tab_pre += "			<a href=\"javascript:OpenMap(5)\"><img alt=\"diplomap\" title=\"diplomap\" border=0 src=\"gfx/icon/diplomap.png\"></a>";
	tab_pre += "			<span>"+tab_corner+"</span>";
	tab_pre += "		</div>";
	tab_pre += "	</div>";
	tab_pre += "	<div class=\"tabpane\">";
		
	var tab_post = "";
	tab_post += "	</div>";
	tab_post += "</div>";

	/*
	tab_pre += "<table class=\"tabs\" cellspacing=0 cellpadding=0><tr>\n";
	tab_pre += "<td "+(gMapMode==kJSMapMode_Normal?	"id=\"current\"":"")+"><a href=\"javascript:SetMapMode(kJSMapMode_Normal)\">Normal</a></td>\n";
	tab_pre += "<td class=\""+(gMapMode==kJSMapMode_Plan?	"":"in"	)+"activetab\"><a href=\"javascript:SetMapMode(kJSMapMode_Plan)\">Pl�ne</a></td>\n";
	tab_pre += "<td class=\""+(gMapMode==kJSMapMode_Bauzeit?"":"in"	)+"activetab\"><a href=\"javascript:SetMapMode(kJSMapMode_Bauzeit)\">Bauzeit</a></td>\n";
	tab_pre += "<td class=\""+(gMapMode==kJSMapMode_HP?"":"in"		)+"activetab\"><a href=\"javascript:SetMapMode(kJSMapMode_HP)\">HP</a></td>\n";
	tab_pre += "<td class=\"tabfillerright\" width=\"100%\" align=\"right\">"+tab_topright+"</td>\n";
	tab_pre += "</tr><tr>\n";
	tab_pre += "<td class=\"tabpane\" colspan=5><div>\n";
	var tab_post = "\n</div></td></tr></table>\n";
	*/
	
	var maptiphtml = "<span class=\"maptip\" onMouseover=\"KillTip()\" name=\""+kMapTipName+"\" style=\"position:absolute;top:0px;left:0px; visibility:hidden;\"></span>";
	
	profiling("sending html to browser");
	
	document.getElementById("mapzone").innerHTML = tab_pre + maphtml + tab_post + maptiphtml;
	return true;
}

function OpenMap (type) {
	var x = gLeft+gXMid;
	var y = gTop+gYMid;
	if (type == 1) { // bigmap
		var army = 0;
		//if (document.getElementsByName("army")[0] != null)
		//	army = document.getElementsByName("army")[0].value;
		// "BigMap"+Math.abs(x)+Math.abs(y)
		gBigMapWindow = window.open("mapjs7.php?sid="+gSID+"&cx=50&cy=50&big=1&army="+gActiveArmyID+"&mode="+gMapMode+"&x="+x+"&y="+y,"BigMap");
	} else if (type == 2) { //minimap2
		window.open("minimap2.php?sid="+gSID+"&crossx="+x+"&crossy="+y,"MiniMap","location=no,menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes");
	} else if (type == 3) { //minimap
		window.open("minimap.php?sid="+gSID+"&cx="+x+"&cy="+y,"MiniMap","location=no,menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes");
	} else if (type == 4) { //creepmap
		window.open("minimap.php?mode=creep&sid="+gSID+"&cx="+x+"&cy="+y,"CreepMap","location=no,menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes");
	} else if (type == 5) { //diplomap
		window.open("minimap.php?mode=guild&diplomap=1&sid="+gSID+"&cx="+x+"&cy="+y,"DiploMap","location=no,menubar=no,toolbar=no,status=no,resizable=yes,scrollbars=yes");
	} else if (type == 100) { //hugemap
		if (!confirm("Sicher ? Die HugeMap ist riesig und hat 200*200 felder, die BigMap 50*50...")) return;
		window.open("<?=kMapScript?>?sid="+gSID+"&cx=200&cy=200&big=1&x="+x+"&y="+y,"HugeMap");
	}
}


function mapscroll_plus() {
	document.getElementsByName('mapscroll')[0].value *= 2;
}
function mapscroll_minus() {
	document.getElementsByName('mapscroll')[0].value = Math.floor(document.getElementsByName('mapscroll')[0].value / 2);
}
	
function SetMapMode (newmode) {
	if (gMapMode == newmode) return;
	gMapMode = newmode;
	CreateMap();
}

function GetCellHTML (relx,rely) {
	if (relx < 0 || rely < 0 || relx >= gCX || rely >= gCY) return "x";
	var i;
	var layers = new Array();
	var celltext = "";
	
	// terrain
	layers[layers.length] = GetTerrainPic(relx,rely);
	var backgroundcolor = HackBackgroundColor(relx,rely);
	
	// building
	var building = GetBuilding(relx,rely);
	if (building) {
		if (building.construction > 0) {
			layers[layers.length] = g(kConstructionPic);
		} else {
			layers[layers.length] = GetBuildingPic(building,relx,rely);
			if (building.user > 0 && gBuildingType[building.type].border && gMapMode!=kJSMapMode_Plan && gMapMode!=kJSMapMode_Bauzeit) 
				backgroundcolor = gUsers[building.user].color;
		}
		if (gMapMode==kJSMapMode_HP) {
			backgroundcolor = GradientRYG(GetFraction(building.hp,calcMaxBuildingHp(building.type,building.level)));
		}
	}
	
	// plan
	var plan = SearchPos(gPlans,relx,rely);
	if (plan) {
		if (gMapMode==kJSMapMode_Plan) {
			backgroundcolor = "red";
			layers[layers.length] = g3(gBuildingType[plan.type].gfx,0,0);
		} else {
			layers[layers.length] = g(kTransCP);
		}
	}
	
	// bauzeit farbe + text
	if (gMapMode==kJSMapMode_Bauzeit && !building) {
		var builddistfactor = GetBuildDistFactor(GetBuildDist(relx,rely));
		//celltext = builddistfactor.toPrecision(2);
		backgroundcolor = GradientRYG(1.0-GetFraction(builddistfactor-1.0,1.0));
	}
	
	// item
	var item = SearchPos(gItems,relx,rely);
	var items = SearchPosArr(gItems,relx,rely);
	for (i in items) layers[layers.length] = g(gItemType[items[i].type].gfx);
	
	// army
	var army = SearchPos(gArmies,relx,rely);
	if (army) {
		nwsecode = 0;
		var unittype = GetArmyUnitType(relx,rely);
		if (UnitTypeHasNWSE(unittype)) { // hyperblob hack
			if (unittype == GetArmyUnitType(relx,rely-1)) nwsecode += kNWSE_N;
			if (unittype == GetArmyUnitType(relx-1,rely)) nwsecode += kNWSE_W;
			if (unittype == GetArmyUnitType(relx,rely+1)) nwsecode += kNWSE_S;
			if (unittype == GetArmyUnitType(relx+1,rely)) nwsecode += kNWSE_E;
		}
		layers[layers.length] = g2(gUnitType[unittype].gfx,nwsecode);
		if (army.user > 0) backgroundcolor = gUsers[army.user].color;
	}
	
	// wps
	var wp = SearchPos(gWPs,relx,rely);
	if (wp) {
		var movablemask = GetUnitsMovableMask(gArmies[gActiveArmyID].units);
		var blocked = (GetPosSpeed(relx,rely,movablemask,gActiveArmyID) == 0) ? "b" : ""; // appended to nwse
		layers[layers.length] = g("mapwp/dot"+blocked+".png");
		var army = GetActiveArmy();
		if (army && army.user > 0) backgroundcolor = gUsers[army.user].color;
	}
	if (gWPMap) for (i in gWPMap[relx][rely]) layers[layers.length] = gWPMap[relx][rely][i];
	
	var i,res = "";
	if (backgroundcolor) res += "<div style=\"\">";
	for (i in layers) {
		var bg = (i==0)?("background-color:"+backgroundcolor+";"):"";
		res += "<div style=\"background-image:url("+layers[i]+"); "+bg+"\">";
	}
	res += "<div name=\"mouselistener\" ><div onClick=\"mapclick("+relx+","+rely+")\" onMouseover=\"if (!gLoading) mapover("+relx+","+rely+")\">";
	if (relx == gXMid && rely == gYMid) res += "<img src='gfx/crosshair.png'>"; res += celltext;
	res += '</div></div>';
	for (i in layers) res += '</div>';
	if (backgroundcolor) res += '</div>';
	
	//if (relx == 4 && rely == 5) alert(g3(gBuildingType[buildingtype].gfx,nwsecode,level));
	// background-color:$b
	// onClick="nav(-1,-1)"
	//res = "<div onClick='m("+relx+","+rely+")'>"+res+"</div>";
	//res = "<div onClick='m("+relx+","+rely+")'>"+res+"</div>";
	//if (relx == 0 && rely == 0) alert(res);
	return res;
}

function mapclick (relx,rely) {
	if (gLoading) return;
	//debuglog("c"+relx+","+rely);
	KillTip();
	if (gBig) {
		//opener.parent.info.location.href = "info/info.php?x="+(x+gLeft)+"&y="+(y+gTop)+"&sid="+gSID;
		if (opener != null && opener.parent != null && opener.parent.navi != null)
			opener.parent.navi.map(relx+gLeft,rely+gTop);
	} else {
		if (parent.navi != null)
			parent.navi.map(relx+gLeft,rely+gTop);
	}
}

function KillTip () {
	if (gLoading) return;
	//alert();
	//document.getElementsByName(kMapTipName)[0].style.visibility = "hidden";
}

function mapover (relx,rely) {
	if (gLoading) return;
	// todo : if (GetTool() != lupe) { KillTip(); return; }

	// generate tip text
	var i;
	var tiptext = "<table>";

	// terrain
	var terraintype = GetTerrainType(relx,rely);
	tiptext += "<tr><td nowrap align=\"left\"><img src=\""+GetTerrainPic(relx,rely)+"\"></td><td nowrap colspan=2>";
	tiptext += "<span>"+((relx+gLeft)+","+(rely+gTop))+"</span> : ";
	tiptext += "<span>"+gTerrainType[terraintype].name+"</span><br>";
	var builddistfactor = GetBuildDistFactor(GetBuildDist(relx,rely));
	//tiptext += "<span>BuildDist "+GetBuildDist(relx,rely).toPrecision(2)+"</span>";
	tiptext += "<span>Bauzeit * "+builddistfactor.toPrecision(3)+"</span>";
	if (gNWSEDebug) tiptext += "<br><span>type="+terraintype+",nwse="+gTerrainMap_nwse[rely+1][relx+1]+"</span>";
	if (gNWSEDebug) tiptext += "<br><span>tc="+gTerrainType[terraintype].connectto_terrain.join(",")+"</span>";
	if (gNWSEDebug) tiptext += "<br><span>bc="+gTerrainType[terraintype].connectto_building.join(",")+"</span>";
	tiptext += "</td></tr>";
	
	// building
	var building = GetBuilding(relx,rely);
	if (building) {
		tiptext += "<tr><td nowrap><img src=\""+GetBuildingPic(building,relx,rely)+"\"></td><td nowrap colspan=2 align=\"left\">";
		tiptext += "<span>"+gBuildingType[building.type].name + " Stufe "+building.level + "</span><br>";
		tiptext += "<span>"+"HP : "+building.hp+"/"+calcMaxBuildingHp(building.type,building.level) + "</span><br>";
		if (building.user > 0) tiptext += "<span>"+gUsers[building.user].name + "</span>";
		if (gNWSEDebug) tiptext += "<br><span>type="+building.type+"flags="+building.jsflags+"</span>";
		if (gNWSEDebug) tiptext += "<br><span>tc="+gBuildingType[building.type].connectto_terrain.join(",")+"</span>";
		if (gNWSEDebug) tiptext += "<br><span>bc="+gBuildingType[building.type].connectto_building.join(",")+"</span>";
		// backgroundcolor = GradientRYG(GetFraction(hp,calcMaxBuildingHp(type,level)))
		tiptext += "</td></tr>";
	}
	
	// plan
	var plan = SearchPos(gPlans,relx,rely);
	if (plan) {
		tiptext += "<tr><td nowrap>";
		tiptext += "<img src=\""+g(kTransCP)+"\"></td><td nowrap>";
		tiptext += "<img src=\""+g3(gBuildingType[plan.type].gfx,HackNWSE(plan.type,0,relx,rely),0)+"\"></td><td nowrap>";
		tiptext += "<span>Bauplan</span><br>";
		tiptext += "<span>"+gBuildingType[plan.type].name+"</span>";
		tiptext += "</td></tr>";
	}
	
	// active army, wps
	if (gActiveArmyID) {
		var wp = SearchPos(gWPs,relx,rely);
		var army = GetActiveArmy();
		var user = (army && army.user > 0)?gUsers[army.user]:false;
		if (wp || (gWPMap && gWPMap[relx][rely].length > 1)) {
			tiptext += "<tr><td nowrap>";
			if (!wp) {
				tiptext += "<div style=\"background-image:url("+gWPMap[relx][rely][gWPMap[relx][rely].length-2]+");\">";
				tiptext += "<img src=\""+gWPMap[relx][rely][gWPMap[relx][rely].length-1]+"\">";
				tiptext += "</div>";
			} else tiptext += "<img src=\""+g("mapwp/dot.png")+"\">";
			tiptext += "</td><td nowrap>";
			if (wp) 
					tiptext += "<span>Wegpunkt</span><br>";
			else 	tiptext += "<span>geplanter weg</span><br>";
			if (army) tiptext += "<span>"+army.name+"</span><br>";
			if (user) tiptext += "<span>"+user.name+"</span><br>";
			tiptext += "</td></tr>";
		}
	}
	
	// item
	var items = SearchPosArr(gItems,relx,rely);
	for (i in items) {
		var item = items[i];
		tiptext += "<tr><td nowrap>";
		tiptext += "<img class=\"maptippic\" src=\""+g(gItemType[item.type].gfx)+"\"></td><td nowrap colspan=2>";
		tiptext += "<span>"+gItemType[item.type].name+":"+TausenderTrenner(item.amount)+"</span>";
		tiptext += "</td></tr>";
	}
	
	// army
	var army = SearchPos(gArmies,relx,rely);
	if (army) {
		tiptext += "<tr><td nowrap colspan=3>";
		tiptext += "<span>"+army.name+"</span><br>";
		if (army.user > 0) tiptext += "<span>"+gUsers[army.user].name+"</span><br>";
		tiptext += "<span>";
		if (army.units.length > 0) for (i in army.units)
			tiptext += "<img src=\""+g(gUnitType[i].gfx)+"\">"+TausenderTrenner(army.units[i]);
		tiptext += "</span><br>";
		tiptext += "<span>";
		if (army.items.length > 0) for (i in army.items)
			tiptext += "<img src=\""+g(gItemType[i].gfx)+"\">"+TausenderTrenner(army.items[i]);
		tiptext += "</span>";
		tiptext += "</td></tr>";
	}
	tiptext += "</table>";
	
	// find a suitable position
	var x,y;
	x = kMapTip_xoff + kJSMapTileSize*relx;
	y = kMapTip_yoff + kJSMapTileSize*rely;
	// spawn tip
	var maptipnode = document.getElementsByName(kMapTipName)[0];
	//alert("maptipnode"+maptipnode+","+kMapTipName+","+document.getElementsByName(kMapTipName));
	maptipnode.innerHTML = tiptext;
	maptipnode.style.visibility = "visible";
	maptipnode.style.position = "absolute";
	if (gBig) {
		maptipnode.style.left = (kMapTip_xoff + kJSMapTileSize*(relx+1))+"px";
		maptipnode.style.top = (kMapTip_yoff + kJSMapTileSize*(rely+1))+"px";
	} else {
		if (relx >= gXMid)
				maptipnode.style.left = (kMapTip_xoff)+"px";
		else	maptipnode.style.left = (kMapTip_xoff+gXMid*kJSMapTileSize)+"px";
		if (rely >= gYMid)
				maptipnode.style.top = (kMapTip_yoff)+"px";
		else	maptipnode.style.top = (kMapTip_yoff+gYMid*kJSMapTileSize)+"px";
	}
}

// utilities
gOutPutOnce = false;

function GetFraction (cur,max) { return (cur <= 0.0 || max == 0)?0.0:((cur >= max)?1.0:(cur / max)); }
function GradientRYG (factor) { // red-yellow-green
	factor = Math.min(1.0,Math.max(0.0,factor));
	var dist = Math.abs(factor - 0.5)*2.0;
	factor = 0.5 + 0.5*((factor>0.5)?1.0:(-1.0))*dist*dist;
	var r = Math.round(255.0*Math.min(1.0,2.0-factor*2.0));
	var g = Math.round(255.0*Math.min(1.0,factor*2.0));
	// if (!gOutPutOnce) { gOutPutOnce = true; alert(factor+"\n"+r+"\n"+g); }
	r = r.toString(16); if (r.length == 0) r = "00"; if (r.length == 1) r = "0"+r; if (r.length > 2) r = "ff";
	g = g.toString(16); if (g.length == 0) g = "00"; if (g.length == 1) g = "0"+g; if (g.length > 2) g = "ff";
	return "#"+(""+r)+(""+g)+"00";
}
function calcMaxBuildingHp(type,level) {
	var maxhp = gBuildingType[type].maxhp;
	return Math.ceil(maxhp + maxhp/100*1.5*level);
}
function GetArmyUnitType (relx,rely) {
	var army = SearchPos(gArmies,relx,rely);
	if (!army) return 0;
	var i,maxtype=0,maxamount=0;
	for (i in army.units)
		if (maxamount < army.units[i]) {
			maxamount < army.units[i];
			maxtype = i;
		}
	return maxtype;
}
function NWSECodeToStr (code) {
	var out = "";
	if(code & kNWSE_N) out += "n";
	if(code & kNWSE_W) out += "w";
	if(code & kNWSE_S) out += "s";
	if(code & kNWSE_E) out += "e";
	return out;
}
function InArray(needle,haystack) {
	// != ""  :   javascript : "".split(",") liefert ein array mit EINEM ELEMENT : dem leeren string  -> rausfiltern
	var i;for (i in haystack) if (haystack[i] == needle && haystack[i] != "") return true;
	return false;
}
function KeyInArray(needle,haystack) {
	var i;for (i in haystack) if (i == needle) return true;
	return false;
}
function TausenderTrenner (nummertext) {
	nummertext = ""+nummertext;
	var blocks = Math.floor((nummertext.length+2)/3);
	var i,j,res = "";
	for (i=0;i<blocks;++i) {
		if (3*i+0 < nummertext.length) res = nummertext[nummertext.length-1 - (3*i+0)]+res;
		if (3*i+1 < nummertext.length) res = nummertext[nummertext.length-1 - (3*i+1)]+res;
		if (3*i+2 < nummertext.length) res = nummertext[nummertext.length-1 - (3*i+2)]+res;
		if (3*i+3 < nummertext.length) res = "."+res;
	}
	return res;
}

// type at position

function SearchPos (arr,relx,rely) {
	var i; for (i in arr) {
		if (arr[i].x == relx + gLeft && 
			arr[i].y == rely + gTop) return arr[i];
	}
	return false;
}
function SearchPosArr (arr,relx,rely) {
	var res = new Array();
	var i; for (i in arr) {
		if (arr[i].x == relx + gLeft && 
			arr[i].y == rely + gTop) res[res.length] = arr[i];
	}
	return res;
}
function GetBuildingType (relx,rely) {
	var building = GetBuilding(relx,rely);
	if (building == false) return 0;
	return building.type;
}
function GetTerrainType (relx,rely) {
	if (relx < -1 || rely < -1 || relx >= gCX+1 || rely >= gCY+1) return kDefaultTerrainID;
	var terraintype = gTerrain[rely+1][relx+1];
	if (terraintype == 0) return kDefaultTerrainID;
	return terraintype;
}
function GetBuilding (relx,rely) {
	if (relx < -1 || rely < -1 || relx >= gCX+1 || rely >= gCY+1) return false;
	return gBuildingsCache[rely+1][relx+1];
}
function GetBuildDist (relx,rely) {
	var mindist = -1;
	var curdist,dx,dy;
	for (i in gBuildSources) {
		dx = gBuildSources[i].x - (relx+gLeft);
		dy = gBuildSources[i].y - (rely+gTop);
		curdist = dx*dx + dy*dy;
		if (mindist == -1 || mindist > curdist) mindist = curdist;
	}
	if (mindist < 0) return 0;
	return Math.sqrt(mindist);
}
function GetActiveArmy () { 
	for (i in gArmies) 
		if (gArmies[i].id == gActiveArmyID) 
			return gArmies[i];
	return false;
}
function ArmyGetMovableMask (army) {
	return 0;
}


// terrain/building pic + nwse

function GetNWSE (typeobj,relx,rely) {
	var nwsecode = 0;
	var ct = typeobj.connectto_terrain;
	var cb = typeobj.connectto_building;
	if (InArray(GetTerrainType(relx,rely-1),ct) || InArray(GetBuildingType(relx,rely-1),cb)) nwsecode += kNWSE_N;
	if (InArray(GetTerrainType(relx-1,rely),ct) || InArray(GetBuildingType(relx-1,rely),cb)) nwsecode += kNWSE_W;
	if (InArray(GetTerrainType(relx,rely+1),ct) || InArray(GetBuildingType(relx,rely+1),cb)) nwsecode += kNWSE_S;
	if (InArray(GetTerrainType(relx+1,rely),ct) || InArray(GetBuildingType(relx+1,rely),cb)) nwsecode += kNWSE_E;
	return nwsecode;
}
function GetTerrainPic (relx,rely) {
	if (gTerrainMap) return gTerrainMap[rely+1][relx+1];
	var terraintype = GetTerrainType(relx,rely);
	var nwsecode = GetNWSE(gTerrainType[terraintype],relx,rely);
	return g_nwse(gTerrainType[terraintype].gfx,nwsecode);
}
function GetBuildingPic (building,relx,rely) {
	var type = building.type;
	var level = building.level;
	if (level < 10) level = 0; else level = 1;
	var nwsecode = GetNWSE(gBuildingType[type],relx,rely);
	var gfx = gBuildingType[type].gfx;
	
	// TODO: FIXME: HACK: (gates&portal)  also in mapstyle_buildings.php and GetBuildingCSS()
	if (building.jsflags & kJSMapBuildingFlag_Open) 
		gfx = gfx.split("-zu-").join("-offen-"); 
		
	// HACK: special nwse for path,gates,bridge...  also in UpdateBuildingNWSE()
	nwsecode = HackNWSE(type,nwsecode,relx,rely); // see mapjs7_globals.js.php
		
	return g3(gfx,nwsecode,level);
}

// simple,fast versions of g
function g (path) { return gGFXBase+path; }
function g_nwse (path,nwse_code) { return gGFXBase+path.split("%NWSE%").join(NWSECodeToStr(nwse_code)); }

// the original g function reimplemented (SLOW!!!)
function g1 (path) { return g2(path,"ns"); }
function g2 (path,nwse) { return g3(path,nwse,0); }
function g3 (path,nwse,level) { return g4(path,nwse,level,0); }
function g4 (path,nwse,level,race) { return g5(path,nwse,level,race,100); }
function g5 (path,nwse,level,race,moral) {
	moral = Math.max(0,Math.min(200,moral));
	//moral range from 0 - 4
	moral = Math.round(moral/200*4);
	if (race == 0) race = 1;
	return gGFXBase+path.split("%M%").join(moral).split("%R%").join(race).split("%NWSE%").join(NWSECodeToStr(nwse)).split("%L%").join(level);
	// return str_replace("%M%",$moral,str_replace("%R%",$race,str_replace("%NWSE%",$nwse,str_replace("%L%",$level,$base.$path))));
}

// interaction

function nav(x,y,scroll) {
	// alle elemente mit javascript-mouseover deaktivieren, um javascript fehler beim laden zu verhindern
	var i,mouselistener = document.getElementsByName("mouselistener"); // killemall (anti death race condition)
	for (i in mouselistener) mouselistener[i].innerHTML = "";
	gLoading = true;
	gAllLoaded = false;
	if (x < 0) x = -1; else if (x > 0) x = 1;
	if (y < 0) y = -1; else if (y > 0) y = 1;
	x = gLeft + gXMid + x * scroll;
	y = gTop + gYMid + y * scroll;
	navabs(x,y,0);
}

function navabs (x,y,cancelmode) {
	var mode = cancelmode?kJSMapMode_Normal:gMapMode;
	location.href = location.pathname + "?sid="+gSID+"&x="+x+"&big="+gBig+"&y="+y+"&cx="+gCX+"&cy="+gCY+"&mode="+mode+"&scroll="+gScroll+"&army="+gActiveArmyID;
}


function profiling (text) {
	return; // no more profiling needed, building-speedup did a fine job !
	var curdate = new Date();
	var curtime = curdate.getTime();
	var timediff = (gLastDebugTime==0)?0:(curtime - gLastDebugTime);
	gLastDebugTime = curtime;
	if (timediff > 0)
		gProfileLastLine += "...took "+(Math.ceil(timediff/10)/100)+" seconds.<br>";
	debuglog(gProfileLastLine);
	gProfileLastLine = text;
}

function debuglog (text) {
	document.getElementsByName("mapdebug")[0].innerHTML = text+document.getElementsByName("mapdebug")[0].innerHTML;
}

gAllLoaded = true; 
// scheiss race conditions beim navigieren im mouseover, krieg ich sogar hiermit nicht ganz weg...
