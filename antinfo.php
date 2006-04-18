<?php
require_once("lib.main.php");
?>
<?php if (isset($f_xml) && $f_xml) {?>
	<xml>
	  <antinfo>
		<timestamp format="Y-m-d H:i:s"><?=date("Y-m-d H:i:s")?></timestamp>
		<units type="AmeisenTruppenAnzahl"><?=intval(sqlgetone("SELECT COUNT(*) FROM `unit` WHERE `army` > 0 AND `type` = 55"))?></units>
		<units type="AmeisenK�niginnenTruppenAnzahl"><?=intval(sqlgetone("SELECT COUNT(*) FROM `unit` WHERE `army` > 0 AND  `type` = 54"))?></units>
		<units type="AmeisenEinheitenAnzahl"><?=intval(sqlgetone("SELECT SUM(`amount`) FROM `unit` WHERE `type` = 55"))?></units>
		<units type="AmeisenK�niginnenEinheitenAnzahl"><?=intval(sqlgetone("SELECT SUM(`amount`) FROM `unit` WHERE `type` = 54"))?></units>
		<units type="AmeisenH�gel"><?=intval(sqlgetone("SELECT COUNT(*) FROM hellhole WHERE ai_type = 3"))?></units>
	  </antinfo>
	</xml>
<?php } else { // ?>
	<h1>Status der Ameisenplage</h1> 
	<p> 
	  Aktuelle Zeit: <timestamp><?php echo date('Y-m-d H:i:s'); ?></timestamp> 
	</p> 
	<table border="1"> 
	  <tr> 
		<th>Einheit</th> 
		<th>Anzahl</th> 
		<th>in Truppen</th> 
	  </tr> 
	  <tr> 
		<td>Ameisenh�gel</td> 
		<td><units class="Ameisenh�gel"><?=intval(sqlgetone("SELECT COUNT(*) FROM hellhole WHERE ai_type = 3"))?></units></td> 
		<td><troops class="Ameisenh�gel"><?=intval(sqlgetone("SELECT COUNT(*) FROM hellhole WHERE ai_type = 3"))?></troops></td> 
	  </tr> 
	  <tr> 
		<td>Ameisenk�niginnen</td> 
		<td><units class="Ameisenk�niginnen"><?=intval(sqlgetone("SELECT SUM(`amount`) FROM `unit` WHERE `type` = 54"))?></units></td> 
		<td><troops class="Ameisenk�niginnen"><?=intval(sqlgetone("SELECT COUNT(*) FROM `unit` WHERE `army` > 0 AND  `type` = 54"))?></troops></td> 
	  </tr> 
	  <tr> 
		<td>Ameisen</td> 
		<td><units class="Ameisen"><?=intval(sqlgetone("SELECT SUM(`amount`) FROM `unit` WHERE `type` = 55"))?></units></td> 
		<td><troops class="Ameisen"><?=intval(sqlgetone("SELECT COUNT(*) FROM `unit` WHERE `army` > 0 AND `type` = 55"))?></troops></td> 
	  </tr> 
	</table>
<?php } // endif?>