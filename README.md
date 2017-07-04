Description
===========

An audio player web app with mobile support for streaming mp3, ogg, m4a and
other formats supported by jPlayer. It's built upon PHP, JavaScript, HTML,
CSS and some UNIX utilties - no database required.

Features
========

* GUI installer.
* Supports mp3, ogg, m4a and other formats supported by jPlayer and your browser.
* ID3 support. Currently ID3v2, but more to follow.
* Search. Supports word and quoted phrase matches using OR. e.g. "The Doors" "Grant Green"
* Interface is fully JavaScript/Ajax powered.
* Playlists
* Infinite random playlist in radio mode. List may be filtered. In radio mode a random playlist will be generated and as each song finishes it's popped off the list and a new song is pushed onto the end.
* Album art is pulled from ID3v2 first and falls back to a `cover.jpg` image in each folder.
* Folder montages of cover art for top level folders.
* No database, but could easily be supported in the future.
* Mobile support. Chrome for iOS is supported when screen locked.
* Keyboard shortcuts: Up/Down for Volume, Left/Right for Track Advancement, Space for Play/Pause, Backspace for Mute.
* Auto-timeout of player after X amount of time. Presents an "Are you still listening?" message with confirm dialog.

License
=======

See [Apache 2 license](https://www.apache.org/licenses/LICENSE-2.0.html), [`LICENSE.md`](LICENSE.md).

Dependencies
============

* PHP 5.3+ (PHP 7 supported, but not throughly tested as of 2016-06-05)
* Webserver with PHP support. Apache2 recommended.
* Flash or HTML5 capable web browser.
* UNIX tools. These are required to build search and radio indexes as well as cover art. These portions of the application are managed from the command line. In the future they may move into the application.
    * `mogrify` and `montage` from ImageMagick
    * `find`
    * Command line `php`
    * `coverlovin.py` if you want to find album art automatically.
    * `exiftool` for displaying audio metadata information like bitrate... Install package:`libimage-exiftool-perl`. If you are using a Debian based Linux distribution you can install using the following command. `sudo apt-get install libimage-exiftool-perl`

Setup
=====

See [`INSTALL.md`](INSTALL.md)

Todo
====

* Look for multiple types of cover art - not just cover.jpg.
* Boolean search operators. Current development.
* Browser history.
* Manage music from web.
* Manage configuration from web. (Basic install is now web based.)
* Radio are you still listening? Would stop play after X amount of time.
