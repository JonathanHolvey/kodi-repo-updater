<?php
	$addon_id = $_GET["id"];

	$addons_xml = simplexml_load_file("addons.xml");
	$version = $addons_xml->xpath("addon[@id='$addon_id']")[0]->attributes()->version;

	header("Location: addons/$addon_id/$addon_id-$version.zip");
?>