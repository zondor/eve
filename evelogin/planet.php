﻿<?php
	require_once $_SERVER['DOCUMENT_ROOT'].'/classes/myfunctions.php';
	require_once $_SERVER['DOCUMENT_ROOT'].'/classes/evefunctions.php';
	require_once $_SERVER['DOCUMENT_ROOT'].'/classes/Prices.php';
	$title = "Planetary Infrastructure";

	$characterID = !empty($_SESSION['characterID']) ? (int) $_SESSION['characterID'] : 0;

	if (!empty($characterID)) {
		$result = $mysqli->query("SELECT keyID, vCode, accessMask, planetsCachedUntil FROM eve.api WHERE characterID=".$characterID);
		while ($row = $result->fetch_object()) {
			$keyID = (int) $row->keyID;
			$vCode = $row->vCode;
			$cachedUntil = (int) $row->planetsCachedUntil;
			$accessMask = (int) $row->accessMask;
		}
		$result->close();
	}

	$systemid = 30000142; //Jita

	function mysqlselectquerycolumntoarray($result, $column)
	{
		$a = array();
		for ($i = 0; $i < $result->num_rows; $i++) {
			$a[] = mysql_result($result, $i, $column);
		}
		return $a;
	}
?>
<!DOCTYPE HTML>
<html>
	<head>
<?php echo getHead($title); ?>
<?php		if (!empty($cachedUntil)) echo "\t\t".'<meta http-equiv="expires" content="' . gmdate("D, d M Y H:i:s e", $cachedUntil) . '">'; ?>
	</head>
	<body onload="CCPEVE.requestTrust('<?php echo "http://".$_SERVER['HTTP_HOST']; ?>')">
<?php echo getPageselection($title, "//image.eveonline.com/Type/2014_64.png"); ?>
		<div id="content">
<?php
			if (!empty($_SERVER['HTTP_EVE_CHARNAME']) && strpos($_SERVER['HTTP_EVE_SHIPNAME'], $_SERVER['HTTP_EVE_CHARNAME']) !== FALSE)
			{
				echo "\t\t\t".'<font style="color:red;">Your Shipname contains your InGame Name! You are obtainable with the Directional Scanner!</font><br>'."\n";
				echo "\t\t\t".'If you already changed your Shipname ignore this until you switched it.<br><br>'."\n\n";
			}

			if ($characterID == 0) {
				echo "Please log in";
			} elseif (empty($keyID) || empty($vCode)) {
				echo 'Please provide me your API Informations in the <a href="/evelogin/auth.php">Settings</a>.';
			} else {
				$selectpinsquery = "SELECT * FROM eve.planetindustrypins WHERE ownerID=$characterID";
	//			$selectpinsquery .= " AND planetID=40176640";
				$selectpinsquery .= " ORDER BY planetID";

				$pinswithoutroutequery = "SELECT planetName, planetroutesbypins.typeName FROM eve.planetroutesbypins, eve.planets WHERE routeID IS NULL AND planetroutesbypins.ownerID=planets.ownerID AND planetroutesbypins.planetID=planets.planetID AND EXISTS (SELECT * FROM planetindustrypins WHERE pinID=planetroutesbypins.pinID) AND planetroutesbypins.ownerID=$characterID";

				$productionwithstoragequery = "SELECT planetproductions.ownerID, planetproductions.planetID, planetproductions.typeID, planetproductions.typeName, planetproductions.productionPerHour, coalesce(planetstorage.quantity, planetstorage.quantity, 0) as inStorage
				FROM eve.planetproductions
				LEFT JOIN eve.planetstorage ON planetproductions.ownerID=planetstorage.ownerID AND planetproductions.planetID=planetstorage.planetID AND planetproductions.typeID=planetstorage.typeID
				WHERE planetproductions.ownerID=$characterID";

				$planetstoragefillingquery = "SELECT planetName, lastUpdate, planetstoragepins.typeName, planetstoragepins.capacity, planetstoragepins.contentVolume,
				SUM(planetroutesbypins.volumePerHour) as volumeIncomePerHour
				FROM eve.planetstoragepins
				LEFT JOIN eve.planetroutesbypins ON planetroutesbypins.ownerID=planetstoragepins.ownerID AND planetroutesbypins.planetID=planetstoragepins.planetID AND planetroutesbypins.pinID=planetstoragepins.pinID
				JOIN eve.planets ON planetstoragepins.ownerID=planets.ownerID AND planetstoragepins.planetID=planets.planetID
				WHERE planetstoragepins.ownerID=$characterID
				GROUP BY planetstoragepins.ownerID, planetstoragepins.planetID, planetstoragepins.pinID
				";

				$queries = array(
					"Planets" => "SELECT * FROM eve.planets WHERE ownerID=$characterID",
					"Planetpins" => "SELECT * FROM eve.planetpins WHERE ownerID=$characterID",
					"Planetlinks" => "SELECT * FROM eve.planetlinks WHERE ownerID=$characterID",
					"Planetroutes" => "SELECT * FROM eve.planetroutes WHERE ownerID=$characterID",
					"Industry Pins without Route" => $pinswithoutroutequery,
					"Pinroutes" => "SELECT * FROM eve.planetroutesbypins WHERE ownerID=$characterID",
					"planetproductions" => "SELECT * FROM eve.planetproductions WHERE ownerID=$characterID",
					"planetstorage" => "SELECT * FROM eve.planetstorage WHERE ownerID=$characterID",
					"planetproductions with storages" => $productionwithstoragequery,
					"planetstoragefilling" => $planetstoragefillingquery
				);

				$result = $mysqli->query("SELECT * FROM eve.planetindustrypins WHERE ownerID=$characterID");
				$requiredAPIAccessMask = 3;

				if ($characterID != 0 && ($cachedUntil == 0 || $cachedUntil < time())) {
					echo "API Data Update pending... This may take up to a minute.<br><br>\n\n";
				}

				if ($characterID == 0 || $cachedUntil == 0 || ($accessMask & $requiredAPIAccessMask) != $requiredAPIAccessMask) {
					// Be silent
				} elseif ($result->num_rows == 0) {
					echo "You really should get a planetary infrastructure!<br><br>\n\n";
				} else {
					$result = $mysqli->query($pinswithoutroutequery);
					while ($result && $row = $result->fetch_object()) {
						echo '<span style="color:red; font-size:150%">Some Pins are not routed:</span>'."\n";
						printmysqlselectquerytable($result);
					}

					echo "<h2>Extractor Units</h2>\n";
					$result = $mysqli->query("SELECT planetName, typeName, expiryTime FROM eve.planetpins, eve.planets WHERE planetpins.ownerID=$characterID AND planetpins.ownerID=planets.ownerID AND planetpins.planetID=planets.planetID AND typeName LIKE '%Extractor%'");
					echo '<div class="table hoverrow bordered" style="text-align: right;">'."\n";
					echo '<div class="headrow">'."\n";
					echo '<div class="cell">Planet</div>'."\n";
					echo '<div class="cell">Extractor</div>'."\n";
					echo '<div class="cell">Finish Time</div>'."\n";
					echo '</div>'."\n";
					while ($row = $result->fetch_object()) {
						echo '<div class="row">'."\n";
						echo '<div class="cell">'.$row->planetName."</div>";
						echo '<div class="cell">'.$row->typeName."</div>";
						$expiryTime = $row->expiryTime;
						echo '<div class="cell"';
						if ($expiryTime < time()) { echo ' style="color:red;" title="finished!"'; }
						elseif ($expiryTime < time() + 60 * 60 * 24) { echo ' style="color:orange;" title="in under 24h finished"'; }
						echo ">";
						echo gmdate('d.m.Y H:i:s', $expiryTime)."</div>";
						echo "</div>\n";
					}
					echo '</div>'."\n";

					echo "<h2>Storages</h2>\n";
					$result = $mysqli->query($planetstoragefillingquery);
//					printmysqlselectquerytable($result);
					echo '<div class="table hoverrow bordered" style="text-align: right;">'."\n";
					echo '<div class="headrow">'."\n";
					echo '<div class="cell">Planet</div>'."\n";
					echo '<div class="cell">Storage</div>'."\n";
					echo '<div class="cell">Capacity<br>m&sup3;</div>'."\n";
					echo '<div class="cell">Content<br>m&sup3;</div>'."\n";
					echo '<div class="cell">Income Per Hour<br>m&sup3;</div>'."\n";
					echo '<div class="cell">Date/Time when Full</div>'."\n";
					echo '</div>'."\n";
					while ($row = $result->fetch_object()) {
						echo '<div class="row">'."\n";
						echo '<div class="cell">'.$row->planetName."</div>";
						echo '<div class="cell">'.$row->typeName."</div>";
						$capacity = $row->capacity;
						$contentVolume = $row->contentVolume;
						$income = $row->volumeIncomePerHour;
						$lastUpdate = $row->lastUpdate;
						echo '<div class="cell">'.formatvolume($capacity)."</div>";
						echo '<div class="cell">'.formatvolume($contentVolume)."</div>";
						echo '<div class="cell">'.formatvolume($income)."</div>";

						if ($income == 0) {
							echo '<div class="cell">';
							echo "</div>";
						} else {
							$cellstart = '<div class="cell"';
							$fullUntilTime = ($capacity - $contentVolume) / $income;
							$fullTime = $lastUpdate + $fullUntilTime * 60 * 60;
							if ($fullTime < time()) { $cellstart .= ' style="color:red;" title="full!"'; }
							elseif ($fullTime < time() + 60 * 60 * 1) { $cellstart .= ' style="color:red;" title="in under 1h full!"'; }
							elseif ($fullTime < time() + 60 * 60 * 24) { $cellstart .= ' style="color:orange;" title="in under 24h full"'; }
							$cellstart .= ">";

							echo $cellstart.gmdate('d.m.Y H:i:s', $fullTime)."</div>";
						}
						echo "</div>\n";
					}
					echo '</div>'."\n";

					echo "<h2>Planeten</h2>\n";
					$planetresult = $mysqli->query("SELECT * FROM eve.planets WHERE ownerID=".$characterID);
		//			echo "<strong>Planets</strong>";
		//			printmysqlselectquerytable($planetresult);

					while ($planetrow = $planetresult->fetch_object()) {
						$planetID = $planetrow->planetID;
						$planetName = $planetrow->planetName;
						$planetTypeID = $planetrow->planetTypeID;
						$planetTypeName = $planetrow->planetTypeName;
						$lastUpdate = $planetrow->lastUpdate;
						echo '<h3><img src="//image.eveonline.com/Type/'.$planetTypeID.'_32.png">'." $planetName - $planetTypeName</h3>\n";
						echo 'last update: '.gmdate('d.m.Y H:i:s e', $lastUpdate)."<br>\n";
						echo '<div style="display: table;">'."\n";
						$result = $mysqli->query("SELECT * FROM ($productionwithstoragequery) bla WHERE planetID=".$planetID);
						echo mysql_error();
						//printmysqlselectquerytable($result);
						if ($result->num_rows > 0) {
							echo '<div style="display: table-cell; padding: 2px;">'."\n";

							$result = $mysqli->query("SELECT * FROM ($productionwithstoragequery) bla WHERE planetID=$planetID AND productionPerHour>0");
							if ($result->num_rows > 0) {
								echo '<strong style="color:green;">Produces</strong><br>'."\n";
	//							printmysqlselectquerytable($result);
								echo '<div class="table hoverrow bordered" style="text-align: right;">'."\n";
								echo '<div class="headrow">'."\n";
								echo '<div class="cell">Item</div>'."\n";
								echo '<div class="cell" style="min-width: 100px;">Produces per Hour</div>'."\n";
								echo '<div class="cell" style="min-width: 100px;">in Storage</div>'."\n";
								echo "</div>\n";
								while ($row = $result->fetch_object()) {
									$typeID = $row->typeID;
									$typeName = $row->typeName;
									$productionPerHour = $row->productionPerHour;
									$inStorage = $row->inStorage;

									echo '<div class="row">'."\n";
									echo '<div class="cell">';
									echo $typeName;
									echo "</div>\n";
									echo '<div class="cell">';
									echo formatamount($productionPerHour);
									echo Prices::getFromID($typeID, $systemid)->getMouseoverField($productionPerHour);
									echo "</div>\n";
									echo '<div class="cell">';
									echo formatamount($inStorage);
									echo Prices::getFromID($typeID, $systemid)->getMouseoverField($inStorage);
									echo "</div>\n";
									echo "</div>\n";
								}
								echo "</div><br>\n";
							}
							$result = $mysqli->query("SELECT * FROM ($productionwithstoragequery) bla WHERE planetID=$planetID AND productionPerHour<0");
							if ($result->num_rows > 0) {
								echo '<strong style="color:red;">Needs</strong><br>'."\n";
	//							printmysqlselectquerytable($result);
								echo '<div class="table hoverrow bordered" style="text-align: right;">'."\n";
								echo '<div class="headrow">'."\n";
								echo '<div class="cell">Item</div>'."\n";
								echo '<div class="cell" style="min-width: 100px;">Needs per Hour</div>'."\n";
								echo '<div class="cell" style="min-width: 100px;">in Storage</div>'."\n";
								echo '<div class="cell">depletes</div>'."\n";
								echo "</div>\n";
								while ($row = $result->fetch_object()) {
									$typeID = $row->typeID;
									$typeName = $row->typeName;
									$productionPerHour = 0 - $row->productionPerHour;
									$inStorage = $row->inStorage;

									echo '<div class="row">'."\n";
									echo '<div class="cell">';
									echo $typeName;
									echo "</div>\n";
									echo '<div class="cell">';
									echo formatamount($productionPerHour);
									echo Prices::getFromID($typeID, $systemid)->getMouseoverField($productionPerHour);
									echo "</div>\n";
									echo '<div class="cell">';
									echo formatamount($inStorage);
									echo Prices::getFromID($typeID, $systemid)->getMouseoverField($inStorage);
									echo "</div>\n";
									$depletes = $lastUpdate + round(60.0 * 60.0 * $inStorage / $productionPerHour);
									echo '<div class="cell ';
									if ($depletes < time())
										echo ' worstvalue';
									echo '">';
									echo gmdate('d.m.Y H:i e', $depletes);
									echo "</div>\n";
									echo "</div>\n";
								}
								echo "</div><br>\n";
							}

							echo "</div><br>\n";
						}

						$result = $mysqli->query("SELECT typeID, typeName, quantity FROM eve.planetstorage WHERE ownerID=$characterID AND planetID=$planetID ORDER BY typeName");
						if ($result->num_rows > 0) {
							echo '<div style="display: table-cell; padding: 2px;">'."\n";
							echo '<strong>Stuff in Storage</strong><br>'."\n";
//							printmysqlselectquerytable($result);
							echo '<div class="table hoverrow bordered" style="text-align: right;">'."\n";
							echo '<div class="headrow">'."\n";
							echo '<div class="cell">Item</div>'."\n";
							echo '<div class="cell" style="min-width: 100px;">Quantity</div>'."\n";
							echo "</div>\n";
							while ($row = $result->fetch_object()) {
								$typeID = $row->typeID;
								$typeName = $row->typeName;
								$quantity = $row->quantity;

								echo '<div class="row">'."\n";
								echo '<div class="cell">';
								echo $typeName;
								echo "</div>\n";
								echo '<div class="cell">';
								echo formatamount($quantity);
								echo Prices::getFromID($typeID, $systemid)->getMouseoverField($quantity);
								echo "</div>\n";
								echo "</div>\n";
							}
							echo "</div>\n";
							echo "</div>\n";
						}
						echo "</div>\n";
					}
				}

				if (false) {
					echo "<h2>DEBUG</h2>\n";

					foreach ($queries as $key => $value) {
						echo "<strong>$key</strong>";
						$display = $value;
						$display = str_replace("SELECT", "<br>\nSELECT", $display);
						$display = str_replace("FROM", "<br>\nFROM", $display);
						$display = str_replace("ON", "<br>\nON", $display);
						$display = str_replace("WHERE", "<br>\nWHERE", $display);
						$display = str_replace("GROUP BY", "<br>\nGROUP BY", $display);
						$display = str_replace("ORDER BY", "<br>\nORDER BY", $display);
						echo $display."<br>\n";
						printmysqlselectquerytable(mysql_query($value));
					}

					$result = mysql_query("SELECT * FROM api WHERE characterID=".$characterID);
					printmysqlselectquerytable($result);

					$result = mysql_query("SELECT * FROM planets WHERE ownerID=".$characterID);
					printmysqlselectquerytable($result);

					for ($i = 0; $i < $result->num_rows; $i++) {
						$planetID = mysql_result($result, $i, 'planetID');
						$planetName = mysql_result($result, $i, 'planetName');
						echo "<h3>$planetName</h3>\n";

						echo "<strong>Pins</strong>\n";
						printmysqlselectquerytable(mysql_query("SELECT * FROM planetpins WHERE ownerID=".$characterID." AND planetID=".$planetID));
						echo "<strong>Links</strong>\n";
						printmysqlselectquerytable(mysql_query("SELECT * FROM planetlinks WHERE ownerID=".$characterID." AND planetID=".$planetID));
						echo "<strong>Routes</strong>\n";
						printmysqlselectquerytable(mysql_query("SELECT * FROM planetroutes WHERE ownerID=".$characterID." AND planetID=".$planetID));
					}
				}

				$mysqli->close();
			}
?>
			<br><br>
<?php
			if (!empty($characterID)) {
				echo 'cached until: '.gmdate('d.m.Y H:i:s e', $cachedUntil)."<br>\n";
			}
?>
			Fly safe<?php if (!empty($_SERVER['HTTP_EVE_SHIPNAME'])) {echo " <b>".$_SERVER['HTTP_EVE_SHIPNAME']."</b>";} ?> o/
<?php echo getFooter(); ?>
		</div>
	</body>
</html>
