# moodle-townsquareexpansion_moodleoverflow

A moodle subplugin that expands the functionality of the townsquare block. Documentation of the subplugin: https://github.com/learnweb/moodle-townsquareexpansion_moodleoverflow

## Description

The townsquare block is a Moodle block plugin that shows notifications from different plugins to the user. As Townsquare only 
supports Moodle core plugins, this subplugin extends the functionality by managing notifications from the moodleoverflow activity
and making them available for the townsquare block.

Documentation of the moodleoverflow activity plugin: https://github.com/learnweb/moodle-mod_moodleoverflow

## Important information:
To install this subplugin, your Moodle system needs to have the townsquare block and the townsquaresupport local plugin
installed.

Documentation of the townsquare block: https://github.com/learnweb/moodle-block_townsquare
Documentation of the townsquaresupport local plugin: https://github.com/learnweb/moodle-local_townsquaresupport

After installing both plugins, you can install this subplugin.

## Installation:
Installing via uploaded ZIP file
Log in to your Moodle site as an admin and go to Site administration > Plugins > Install plugins.
Upload the ZIP file with the plugin code. You should only be prompted to add extra details if your plugin type is not automatically detected.
Check the plugin validation report and finish the installation.
Installing manually
The plugin can be also installed by putting the contents of this directory to

{your/moodle/dirroot}/local/townsquaresupport/townsquareexpansion/moodleoverflow
Afterwards, log in to your Moodle site as an admin and go to Site administration > Notifications to complete the installation.

Alternatively, you can run

$ php admin/cli/upgrade.php
to complete the installation from the command line.

License
2024 Tamaro Walter

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/.