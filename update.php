<?php
	// Timing attack safe string comparison for PHP < 5.6
	if (!function_exists("hash_equals")) {
		function hash_equals($a, $b) {
		    return substr_count($a ^ $b, "\0") * 2 === strlen($a . $b);
		}
	}

	// Return a HTTP response code and message, and quit
	function respond($message, $code) {
		http_response_code($code);
		echo($message);
		die();
	}

	// Function to append one SimpleXMLElement into another
	function mergeXML(&$base, $add) { 
		if ($add->count() != 0)
			$new = $base->addChild($add->getName());
		else 
			$new = $base->addChild($add->getName(), $add);
		foreach ($add->attributes() as $a => $b)
			$new->addAttribute($a, $b);
		if ($add->count() != 0) { 
			foreach ($add->children() as $child) 
			    mergeXML($new, $child);
		} 
	}

	// Recursively delete directories and files
	function delete($path) {
		if (is_dir($path)) {
			foreach (array_diff(scandir($path), [".", ".."]) as $object) {
				delete($path . "/" . $object);
			}
			rmdir($path);
		}
		else
			unlink($path);
	}

 
	$body = file_get_contents("php://input");
	$payload = json_decode($body, $assoc=true);
	$addon_id = $payload["repository"]["name"];
	
	// Verify webhook secret
	$secret = json_decode(file_get_contents("secrets.json"), $assoc=true)[$addon_id];
	$signature = $_SERVER["HTTP_X_HUB_SIGNATURE"];
	if (!hash_equals("sha1=" . hash_hmac("sha1", $body, $secret), $signature))
		respond("The webhook secret could not be verified", 403);

	// Check for pre-release versions
	if ($payload["release"]["prerelease"] === true)
		respond("Skipping pre-release version", 200);
	// Check for correct version number format
	if (!preg_match("/^v?(\d+\.\d+\.\d+)$/", $payload["release"]["tag_name"], $match))
		respond("The release tag format doesn't conform to semantic versioning", 400);

	$version = $match[1];
	$zip_url = $payload["release"]["zipball_url"];

	// Create addon directory
	$addon_path = "addons/$addon_id";
	if (!file_exists($addon_path))
		mkdir($addon_path, $mode=0777, $recursive=true);

	// Fetch zip file from GitHub
	$zip_path = "$addon_path/$addon_id-$version.zip";
	$context = stream_context_create(["http" => ["header" => ["User-Agent: PHP-" . phpversion()]]]);
	if (!file_put_contents($zip_path, fopen($zip_url, "r", false, $context)) !== False)
		respond("The release could not be retrieved", 400);

	$addon_zip = new ZipArchive;
	if ($addon_zip->open($zip_path) !== true)
		respond("The zip file could not be opened", 400);
	// Rename the root folder within the zip archive
	for ($i = 0; $i < $addon_zip->numFiles; $i++) {
		$new_name = preg_replace("/^[^\/]+/", $addon_id, $addon_zip->getNameIndex($i));
		$addon_zip->renameIndex($i, $new_name);
	}

	$addon_xml = simplexml_load_string($addon_zip->getFromName("$addon_id/addon.xml"));

	// Check addon ID and version in XML file
	if ($addon_xml->attributes()->version != $version)
		respond("Addon version doesn't match release tag", 400);
	if ($addon_xml->attributes()->id != $addon_id)
		respond("Addon ID doesn't match repository name", 400);

	// Delete old asset files
	foreach(array_diff(scandir("addons/$addon_id"), [".", ".."]) as $path) {
		if (!preg_match("/\.zip$/", $path))
			delete("addons/$addon_id/$path");
	}

	// Create list of asset files to extract
	$assets = ["icon.png", "fanart.jpg", "changelog-$version.txt"];
	foreach ($addon_xml->xpath("/addon/extension[@point='xbmc.addon.metadata']/assets/*") as $asset)
		$assets[] = (string)$asset;

	// Extract addon asset files from zip
	foreach ($assets as $asset) {
		$contents = $addon_zip->getFromName("$addon_id/$asset");
		if ($contents !== false) {
			$asset_path = "addons/$addon_id/$asset";
			if (!file_exists(dirname($asset_path)))
				mkdir(dirname($asset_path), $mode=0777, $recursive=true);
			file_put_contents($asset_path, $contents);
		}
	}

	$repo_xml = is_file("addons.xml") ? simplexml_load_file("addons.xml"): new SimpleXMLElement("<addons/>");

	// Remove old XML node from repository and append new one
	unset($repo_xml->xpath("addon[@id='$addon_id']")[0][0]);
	mergeXML($repo_xml, $addon_xml);
	$repo_xml->asXML("addons.xml");

	$name = $addon_xml->attributes()->name;
	respond("Thanks! $name v$version was cached successfully", 200);
?>
