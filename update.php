<?php
	$payload = json_decode(file_get_contents("php://input"), $assoc=true);

	// Check for correct version number format
	if (!preg_match("/^v?(\d+\.\d+\.\d+)$/", $payload["release"]["tag_name"], $match))
		echo("The release tag format doesn't conform to semantic versioning");
	else {
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
		if (file_put_contents($zip_path, fopen($zip_url, "r", false, $context)) !== False) {
			echo("Thanks! The release was cached successfully");
		}
		else
			echo("The release could not be retrieved");
	}
?>
