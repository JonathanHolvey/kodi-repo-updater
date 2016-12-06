<?php
	$payload = json_decode(file_get_contents("php://input"), $assoc=true);

	// Check for pre-release versions
	if ($payload["release"]["prerelease"] === true)
		respond("Skipping pre-release version", 200);
	// Check for correct version number format
	if (!preg_match("/^v?(\d+\.\d+\.\d+)$/", $payload["release"]["tag_name"], $match))
		respond("The release tag format doesn't conform to semantic versioning", 400);

	$version = $match[1];
	$addon_id = $payload["repository"]["name"];
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
	else {
		// Rename the root folder within the zip archive
		for ($i = 0; $i < $addon_zip->numFiles; $i++) {
			$new_name = preg_replace("/^[^\/]+/", $addon_id, $addon_zip->getNameIndex($i));
			$addon_zip->renameIndex($i, $new_name);
		}
	}

	respond("Thanks! $addon_id v$version was cached successfully", 200);

	// Return a HTTP response code and message, and quit
	function respond(str $message, int $code) {
		http_response_code($code);
		echo($message);
		die();
	}
?>
