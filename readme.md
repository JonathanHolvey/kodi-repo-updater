# Kodi Repository Updater

This project allows you to automatically generate a Kodi repository by simply releasing an addon through GitHub. All you have to do is tag the release, publish it from your GitHub account, and the repository will do the rest.

It relies on a webhook triggered by the *Release* event, which allows GitHub to push the addon source code to the repository server, where it is processed and published via a Kodi repository.

*Note: This readme assumes that you are familiar with Git, GitHub, JSON files and the workings of Kodi addon repositories.*

## Repository installation

1. Copy the three files `update.php`, `latest.php` and `.htaccess` to the web server which will host your repository.

2. Configure your repository addon to point to the server. The extension point of the `addon.xml` file should look like this, where `http://example.com/kodi-repo/` is the location you installed the repository updater:

	```xml
	<extension point="xbmc.addon.repository" name="Example Repository">
		<info>http://example.com/kodi-repo/addons.xml</info>
		<checksum>http://example.com/kodi-repo/addons.xml.md5</checksum>
		<datadir zip="true">http://example.com/kodi-repo/addons</datadir>
	</extension>
	```

### Requirements:

- An Apache web server
- PHP version 5.4 or higher

## Addon setup

*Note: Try not to confuse your GitHub repository with your Kodi repository*

The following steps will need to be performed for every addon that will be published to the Kodi repository:

1. Ensure your addon is hosted on GitHub, with the repository named the same as your addon ID.
2. Add a GitHub webhook to your addon repository, choosing only the *Release* event to trigger it. Set the payload URL to point at the file `update.php`, for example `http://example.com/kodi-repo/update.php`.
3. Set a secret for your webhook, ensuring you choose a suitably long, random string. This will ensure that only you are able to publish code to your Kodi repository.
4. Create (or edit) the file `secrets.json` on your web server, in the installation directory,. Add an entry for your addon using the addon ID as a key. The file should look something like this, with one line per addon:

	```json
	{
		"plugin.example-plugin": "QdS0nyimPbEPuleCFGkQcGJA8Oo0cAVbmLIVKYqGPGfG1kmtQu",
		"script.example-script": "Npw4J2QBQa0uh4FzHXPvWvJM9dELjQDkk6maW2GHimczz7ACIy"
	}
	```

## Publishing a release

When you're ready to publish or update your addon, tag it with the new version number and push it to GitHub. Draft a new release on GitHub and release it.

The addon will be automatically copied to your repository web server, and recorded in the file `addons.xml`.

### Publishing a link

The file `latest.php` can be used as a static link to the latest version of a particular addon zip file. This will save you having to update links every time you publish a new version of an addon. Just provide the addon ID as a parameter in the URL:

    http://example.com/kodi-repo/latest.php?id=plugin.example-plugin