<?php

/*
Copyright 2014 Weldon Sams

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/

class Streams {

    private $cfg;
    private $auth;
    private $t;

    public function __construct($cfg=null, $auth=null, $t=null) {
        $this->cfg = $cfg;
        $this->auth = $auth;
        $this->t = $t;
        $auth->currentPlaylist = $auth->userDir . "/currentPlaylist.obj";
        $auth->currentPlaylistDir = $auth->userDir . "/currentPlaylistDir.obj";
    }

    /**
     * @tested true
     */
    public function human_filesize($size) {
        if (is_file($size)) {
            $size = filesize($size);
        } else{
            // $size is already assumed to be in bytes.
        }
        // $size = 1 to prevent dividing by zero.
        if ($size == 0) {
            $size = 1;
        }
        $filesizename = array("bytes", "kb", "mb", "gb", "tb", "pb", "eb", "zb", "yb");
        return round($size / pow(1000, ($i = floor(log($size, 1000)))), 2) . $filesizename[$i];
    }

    public function handle($input) {
        print("<pre>");
        if (is_array($input)) {
            print_r($input);
        } elseif(is_object($input)) {
            var_dump($input);
        } else {
            $input = preg_replace("/\n*$/", "", $input);
            print($input . "\n");
        }
        print("</pre>");
    }

    /**
     * @tested true
     */
    public function openTheDir($dir) {
        $pageContent = "";
        // This is when you open a dir.
        $dir = $this->singleSlashes($dir);
        if (file_exists($this->cfg->defaultMp3Dir . "/" . $dir . "/cover.jpg")) {

            $adir = explode("/", $dir);
            foreach ($adir as $k=>$d) {
                $adir[$k] = rawurlencode($d);
            }
            $enc_dir = implode("/", $adir);

            $enc_cover = $this->cfg->defaultMp3Url . $this->singleSlashes("/" . $enc_dir . "/cover.jpg");

            $a_tmpl['enc_cover'] = $enc_cover;
            $this->t->setData(array("enc_cover"=>$enc_cover));
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/coverArt.tmpl");
            $coverart = $this->t->compile();
        }
        $pageContent .= $this->getFileIndex($dir, $coverart);
        return $pageContent;
    }

    public function openMyRadio($searchDb=null, $radioDb=null, $loadingStation=false) {
        $userDir = $this->auth->userDir;
        if ($searchDb == null && $radioDb == null) {
            $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
            $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";
        } else {
            $db = $searchDb;
            $fdb = $radioDb;
        }
        $index = "";
        if (file_exists($db)) {
            $dirs = file($db);
            if (is_array($dirs)) {
                foreach ($dirs as $k=>$dir) {
                    $dir = trim(preg_replace("/^(.*?):::.*$/", "\${1}", $dir));
                    $file = $this->singleSlashes($dir);
                    if (is_dir($this->cfg->defaultMp3Dir . "/" . $file) 
                            && $this->containsMusic($file)) {
                        $dirLink = "/";
                        $html_dir = preg_replace("/\"/", "\\\"", $dirLink . $file);
                        $html_end_dir = htmlspecialchars(preg_replace("/^.*\/(.*?)/", "\${1}", $file));

                        // Add create and add-to radio buttons.
                        $this->t->setData(array("html_dir" => $html_dir));
                        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-remove-radio.tmpl");
                        $createAddRadioButton = $this->t->compile();

                        if (file_exists("{$this->cfg->defaultMp3Dir}{$dirLink}{$file}/small_montage.jpg")) {
                            $background_url = "{$this->cfg->defaultMp3Url}{$dirLink}{$file}/small_montage.jpg";
                            $js_background_url = preg_replace("/'/", "\\'", $background_url);
                        } else if (file_exists("{$this->cfg->defaultMp3Dir}{$dirLink}{$file}/small_cover.jpg")) {
                            $background_url = "{$this->cfg->defaultMp3Url}{$dirLink}{$file}/small_cover.jpg";
                            $js_background_url = preg_replace("/'/", "\\'", $background_url);
                        } else {
                            $background_url = "images/bigfolder.png";
                            $js_background_url = $background_url;
                        }

                        $addToPlaylist = "";
                        if ($this->containsMusic("{$dirLink}{$file}")) {
                            $this->t->setData(array("html_dir" => $html_dir, "type" => "dir"));
                            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/add-to-playlist.tmpl");
                            $addToPlaylist = $this->t->compile();
                        }

                        $this->t->setData(array("js_background_url"=>$js_background_url, "html_dir"=>$html_dir,
                                "html_end_dir"=>$html_end_dir, "addToPlaylist"=>$addToPlaylist, "createAddRadioButton"=>$createAddRadioButton));
                        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/coverListItem.tmpl");
                        $coverListItem = $this->t->compile();
                        $o['index'] .= $coverListItem;
                    }
                }
            }
        }

        $this->t->setData(array());
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/playMyRadioButton.tmpl");
        $playRadioButton = $this->t->compile();
        if (!$loadingStation) {
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/saveMyRadioButton.tmpl");
            $saveRadioButton = $this->t->compile();
        }
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/myRadioStationsButton.tmpl");
        $viewMyRadioStationsButton = $this->t->compile();
        $index = "{$playRadioButton} {$saveRadioButton} {$viewMyRadioStationsButton}<br />"
                . "<div id=\"save-radio-dialog\"><input class=\"form-input\" type=\"text\" id=\"save-radio-name\" "
                . "placeholder=\"Enter radio name...\" /> <input class=\"button\" type=\"button\" "
                . "id=\"save-radio-button\" value=\"save\" /></div><br /><br />"
                . "<div id=\"radio-station-wrapper\">" . $o['index'] . "</div>";

        return $index;
    }

    public function viewMyRadio() {
        $userDir = $this->auth->userDir;
        $fullUserDir = "{$this->cfg->streamsRootDir}/{$userDir}";
        $stationsDir = "{$fullUserDir}/stations";
        if (!file_exists($stationsDir)) {
            mkdir($stationsDir);
        }
        $curdir = getcwd();
        chdir($stationsDir);
        $stations = glob("*.files.db");
        $radioNames = array();
        $stationsHtml = "";
        foreach ($stations as $k=>$station) {
            $radioName = $station;
            $radioName = preg_replace("/\.files.db/", "", $radioName);
            $dataName = $radioName;
            $radioName = preg_replace("/_/", " ", $radioName);
            $stationsHtml .= "<div data-station=\"{$dataName}\" "
                    . "data-selected=\"no\" "
                    . "class=\"radio-station\"><div title=\"Remove this radio station.\" "
                    . "class=\"radio-station-remove\" data-station=\"{$dataName}\">&nbsp;</div> "
                    . "<div class=\"radio-station-name\">{$radioName}</div>"
                    . "<div class=\"clear\"></div></div>";
            $radioNames[] = $radioName;
        }
        $this->t->setData(array());
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/playMyRadioButton.tmpl");
        $playRadioButton = $this->t->compile();
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/saveMyRadioButton.tmpl");
        $saveRadioButton = $this->t->compile();
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/myRadioStationsButton.tmpl");
        $viewMyRadioStationsButton = $this->t->compile();
        return json_encode(array("status"=>"ok", "radioNames"=>$radioNames, "html"=>$stationsHtml));
    }

    public function loadStation($station) {
        if (!isset($station) || preg_match("/^\s*$/", $station)) {
            return json_encode(array("status"=>"error", "message"=>"You must enter a station name."));
        }

        $userDir = $this->auth->userDir;
        $fullUserDir = "{$this->cfg->streamsRootDir}/{$userDir}";
        $searchDb = "{$fullUserDir}/stations/{$station}.search.db";
        $radioDb = "{$fullUserDir}/stations/{$station}.files.db";
        $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
        $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";

        if (!file_exists($radioDb) || !file_exists($searchDb)) {
            return json_encode(array("status"=>"error", 
                    "message"=>"Radio does not exist."));
        }

        // This syncs the newly loaded station with the current My Radio station.
        // That way you can add to it and save again if you want.
        //copy($searchDb, $db);
        //copy($radioDb, $fdb);

        $loadingStation = true;
        $html = "<input type=\"text\" id=\"find-music-input\" class=\"form-input find-music-input\" "
                . "placeholder=\"Search to find music for this playlist... Click to add...\" />"
                . "<br /><br />" . $this->openMyRadio($searchDb, $radioDb, $loadingStation);

        return json_encode(array("status"=>"ok", "html"=>$html));
    }

    // similar to function search()
    public function suggestRadioStationItem($term) {
        $f = file($this->cfg->searchDatabase);
        $results = $this->searchArray($term, $f);

        $curdir = getcwd();

        $a_files = array();
        $o['index'] = "";
        $o['isMp3'] = false;
        $index = "";
        foreach ($results as $k=>$key) {
            $r = explode(":::", $f[$key]);
            $dir = trim($r[0]);

            // Don't return directories that don't contain music.
            $cntmusic = count(glob("{$this->cfg->defaultMp3Dir}/{$dir}/*.{"
                    . $this->cfg->getValidMusicTypes("glob") . "}", GLOB_BRACE));
            if ($cntmusic < 1) {
                continue;
            }

            if (!file_exists($this->cfg->defaultMp3Dir . '/' . $dir)) {
                continue;
            }
            $dirLink = "/" . preg_replace("/^(.*)\/.*$/", "\${1}", $dir) . "/";

            $reldir = preg_replace("/^.*\/(.*)$/", "\${1}", $dir);
            $lastdir = preg_replace("/^.*\/(.*)$/", "\${1}", $dir);
            $nexttolastdir = preg_replace("/^.*\/(.*?)\/.*$/", "\${1}", $dir);
            $coverImg = "";
            if (file_exists("{$this->cfg->defaultMp3Dir}/{$dir}/small_cover.jpg")) {
                $coverImg = "<img style=\"width:2em; height:2em;\" src=\"{$this->cfg->defaultMp3Url}/{$dir}/small_cover.jpg\" alt=\"cover\" /> ";
            }
            $autoValue = trim("/" . $dir);
            $autoValue = trim($this->singleSlashes($autoValue));
            $autoLabelValue = trim($nexttolastdir . " / " . $lastdir);
            $autoLabel = $coverImg . " " . $autoLabelValue . " <span class=\"muted\">{$cntmusic} files</span>";
            $a_files[] = array("label"=>$autoLabel, "value"=>$autoLabelValue, "dir"=>$autoValue);
            $chdir = preg_replace("/^(.*)\/.*$/", "\${1}", $this->cfg->defaultMp3Dir . "/" . $dir);
            chdir($chdir);
            if ($k > 15) {
                break;
            }
            chdir($curdir);
        }

        return json_encode($a_files);
    }

    public function addToRadioStation($station, $dir) {
        if (!isset($station) || preg_match("/^\s*$/", $station)) {
            return json_encode(array("status"=>"error", "message"=>"You must enter a station name."));
        }
        if (!isset($dir) || preg_match("/^\s*$/", $dir)) {
            return json_encode(array("status"=>"error", "message"=>"You must enter a dir name."));
        }

        $this->addToPersonalRadio($dir, $station);

        $html_dir = preg_replace("/\"/", "\\\"", $dir);
        $html_end_dir = htmlspecialchars(preg_replace("/^.*\/(.*)$/", "\${1}", $dir));

        if ($found) {
            $this->t->setData(array("html_dir" => $html_dir));
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-remove-radio.tmpl");
            $createAddRadioButton = $this->t->compile();
        } else {
            $this->t->setData(array("html_dir" => $html_dir));
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-add-radio.tmpl");
            $createAddRadioButton = $this->t->compile();
        }

        if (file_exists("{$this->cfg->defaultMp3Dir}{$dir}/small_montage.jpg")) {
            $background_url = "{$this->cfg->defaultMp3Url}{$dir}/small_montage.jpg";
            $js_background_url = preg_replace("/'/", "\\'", $background_url);
        } else if (file_exists("{$this->cfg->defaultMp3Dir}{$dir}/small_cover.jpg")) {
            $background_url = "{$this->cfg->defaultMp3Url}{$dir}/small_cover.jpg";
            $js_background_url = preg_replace("/'/", "\\'", $background_url);
        } else {
            $background_url = "images/bigfolder.png";
            $js_background_url = $background_url;
        }

        $addToPlaylist = "";
        if ($this->containsMusic("{$dir}")) {
            $this->t->setData(array("html_dir" => $html_dir, "type" => "dir"));
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/add-to-playlist.tmpl");
            $addToPlaylist = $this->t->compile();
        }

        $this->t->setData(array("js_background_url"=>$js_background_url, "html_dir"=>$html_dir,
                "html_end_dir"=>$html_end_dir, "addToPlaylist"=>$addToPlaylist, "createAddRadioButton"=>$createAddRadioButton));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/coverListItem.tmpl");
        $coverListItem = $this->t->compile();

        return json_encode(array("status"=>"ok", "html"=>$coverListItem));
    }

    public function removeRadioStation($station) {
        if (!isset($station) || preg_match("/^\s*$/", $station)) {
            return json_encode(array("status"=>"error", "message"=>"You must enter a station name."));
        }
        $radioName = preg_replace("/_/", " ", $station);
        $userDir = $this->auth->userDir;
        $fullUserDir = "{$this->cfg->streamsRootDir}/{$userDir}";
        $searchDb = "{$fullUserDir}/stations/{$station}.search.db";
        $radioDb = "{$fullUserDir}/stations/{$station}.files.db";

        if (!file_exists($radioDb) || !file_exists($searchDb)) {
            return json_encode(array("status"=>"error", 
                    "message"=>"Radio does not exist."));
        }

        if (!unlink($searchDb)) {
            return json_encode(array("status"=>"error", 
                    "message"=>"Could not remove radio station."));
        }
        if (!unlink($radioDb)) {
            return json_encode(array("status"=>"error", 
                    "message"=>"Could not remove radio station."));
        }

        print(json_encode(array("status"=>"ok", "message"=>"Removed radio station {$station}")));
    }

    public function saveMyRadio($name) {
        if (!isset($name) || preg_match("/^\s*$/", $name)) {
            return json_encode(array("status"=>"error", "message"=>"You must enter a station name."));
        }

        $userDir = $this->auth->userDir;
        $fullUserDir = "{$this->cfg->streamsRootDir}/{$userDir}";
        $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
        $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";

        $radioName = $name;
        $radioName = preg_replace("/[^a-zA-Z0-9-_ ]/", "", $radioName);
        $radioName = preg_replace("/  +/", " ", $radioName);
        $radioName = ucwords(strtolower($radioName));
        $humanRadioName = $radioName;
        $radioName = preg_replace("/ /", "_", $radioName);

        $radioDb = "{$fullUserDir}/stations/{$radioName}.files.db";
        $searchDb = "{$fullUserDir}/stations/{$radioName}.search.db";

        if (!file_exists("{$fullUserDir}/stations")) {
            if (!mkdir("{$fullUserDir}/stations")) {
                return json_encode(array("status"=>"error", 
                        "message"=>"Could not create stations directory. Not saving station."));
            }
        }

        if (file_exists($radioDb) || file_exists($searchDb)) {
            return json_encode(array("status"=>"error", 
                    "message"=>"Radio station already exists. Please enter another."));
        }

        copy($db, $searchDb);
        copy($fdb, $radioDb);

        return json_encode(array("status"=>"ok", "message"=>"Saving radio station {$humanRadioName}"));
    }

    /**
     * @tested true
     */
    public function containsMusic($dir) {
        if (count(glob("{$this->cfg->defaultMp3Dir}/{$dir}/*.{" . $this->cfg->getValidMusicTypes("glob") . "}", GLOB_BRACE)) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @tested true
     */
    public function buildIndex($a_files, $dirLink, $search=false) {
        $o = array();
        $o['index'] = "";
        $o['isMp3'] = false;
        $dirLink = $this->singleSlashes($dirLink);
        foreach ($a_files as $k=>$file) {
            $file = $this->singleSlashes($file);
            if (is_dir($file)) {
                $html_dir = preg_replace("/\"/", "\\\"", $dirLink . $file);
                $html_end_dir = htmlspecialchars($file);

                // Add create and add-to radio buttons.
                // TODO: make this a function
                // start: see if album is in radio
                $radioDir = $dirLink . $file;
                $radioDir = trim($this->singleSlashes("/" . $radioDir));
                $userDir = $this->auth->userDir;
                $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
                if (file_exists($fdb)) {
                    $f = file($fdb);
                    $found = false;
                    if (is_array($f)) {
                        foreach ($f as $k=>$album) {
                            $album = trim(preg_replace("/^(.*):::.*$/", "\${1}", $album));
                            $album = trim($this->singleSlashes("/" . $album));
                            if ($album == $radioDir) {
                                $found = true;
                                unset($f[$k]);
                            }
                        }
                    }
                }
                // end: see if album is in radio
                if ($found) {
                    $this->t->setData(array("html_dir" => $html_dir));
                    $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-remove-radio.tmpl");
                    $createAddRadioButton = $this->t->compile();
                } else {
                    $this->t->setData(array("html_dir" => $html_dir));
                    $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-add-radio.tmpl");
                    $createAddRadioButton = $this->t->compile();
                }

                if (file_exists("{$this->cfg->defaultMp3Dir}{$dirLink}{$file}/small_montage.jpg")) {
                    $background_url = "{$this->cfg->defaultMp3Url}{$dirLink}{$file}/small_montage.jpg";
                    $js_background_url = preg_replace("/'/", "\\'", $background_url);
                } else if (file_exists("{$this->cfg->defaultMp3Dir}{$dirLink}{$file}/small_cover.jpg")) {
                    $background_url = "{$this->cfg->defaultMp3Url}{$dirLink}{$file}/small_cover.jpg";
                    $js_background_url = preg_replace("/'/", "\\'", $background_url);
                } else {
                    $background_url = "images/bigfolder.png";
                    $js_background_url = $background_url;
                }

                $addToPlaylist = "";
                if ($this->containsMusic("{$dirLink}{$file}")) {
                    $this->t->setData(array("html_dir" => $html_dir, "type" => "dir"));
                    $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/add-to-playlist.tmpl");
                    $addToPlaylist = $this->t->compile();
                }

                $this->t->setData(array("js_background_url"=>$js_background_url, "html_dir"=>$html_dir,
                        "html_end_dir"=>$html_end_dir, "addToPlaylist"=>$addToPlaylist, "createAddRadioButton"=>$createAddRadioButton));
                $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/coverListItem.tmpl");
                $coverListItem = $this->t->compile();
                $o['index'] .= $coverListItem;
            } else {
                if (preg_match("/\.(" . $this->cfg->getValidMusicTypes("preg") . ")$/i", $file)) {
                    $o['isMp3'] = true;
                    $filesize = $this->human_filesize($file);
                    $displayFile = $file;
                    if (file_exists("metadata.obj")) {
                        $metadataFile = $this->cfg->defaultMp3Dir . "/" . $dirLink . "/metadata.obj";
                        $metadataObj = unserialize(file_get_contents($metadataFile));
                        $bitrate = $metadataObj[$file]['bitrate'];
                    } else {
                        $bitrate = $this->getBitrate($dirLink, $file);
                    }
                    $id3 = $this->id3($dirLink, $file);
                    $filePath = rawurlencode($dirLink . $file);
                    $directLink = preg_replace("/ /", "%20", $dirLink . $file);

                    if ($this->isRestricted($dirLink . $file)) {
                        continue;
                    }

                    // add-to-playlist for single files
                    $html_dir = rtrim(preg_replace("/\"/", "\\\"", $dirLink), "/");
                    $html_file = preg_replace("/\"/", "\\\"", $file);
                    $this->t->setData(array("html_dir" => $html_dir, "html_file" => $html_file, "type" => "file", "createAddRadioButton"=>$createAddRadioButton));
                    $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/add-to-playlist.tmpl");
                    $addToPlaylist = $this->t->compile();

                    $this->t->setData(array("filePath"=>$filePath, "title"=>$id3['title'],
                            "filesize"=>$filesize, "bitrate"=>$bitrate, "add-to-playlist"=>$addToPlaylist, 
                            "direct-link" => "{$this->cfg->defaultMp3Url}/{$directLink}"));
                    $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/albumListItem.tmpl");
                    $o['index'] .= $this->t->compile();
                    continue;
                }
            }
        }
        return $o;
    }

    public function getBitrate($dir, $file) {
        $dir = ltrim(rtrim($dir, "/"), "/");
        $file = ltrim(rtrim($file, "/"), "/");
        $fullPath = $this->cfg->defaultMp3Dir . "/" . $dir . "/" . $file;
        ob_start();
            //system("file \"{$fullPath}\"");
            system("exiftool -AudioBitrate \"{$fullPath}\"");
            $c = ob_get_contents();
        ob_end_clean();
        //return preg_replace("/" . preg_quote($fullPath, "/") . ": /", "", trim($c));
        return preg_replace("/ kbps/", "kbps", preg_replace("/Audio Bitrate : /", "", preg_replace("/  +/", " ", trim($c))));
    }

    /**
     * @tested true
     */
    public function id3($dir, $file) {
        $dir = ltrim(rtrim($dir, "/"), "/");
        $file = ltrim(rtrim($file, "/"), "/");
        $fullPath = $this->cfg->defaultMp3Dir . "/" . $dir . "/" . $file;
        $getID3 = new getID3();
        $pageEncoding = 'UTF-8';
        $getID3->setOption(array("encoding" => $pageEncoding));
        $id3 = $getID3->analyze($fullPath);
        $o = array();

        $o['playlistTitle'] = "";

        // Set artist
        $artist = $this->getId3Artist($id3);
        if ($artist) {
            $o['artist'] = $artist;
        } else {
            $o['artist'] = "Unknown";
        }
        $o['playlistTitle'] .= $o['artist'] . " &rsaquo; ";

        // Set album
        $album = $this->getId3Album($id3, $dir, $file);
        if ($album) {
            $o['album'] = $album;
        } else {
            $o['album'] = "Unknown";
        }
        $o['playlistTitle'] .= $o['album'] . " &rsaquo; ";

        // Set title
        $title = $this->getId3Title($id3);
        if ($title) {
            $o['title'] = $title;
        } else {
            $o['title'] = $file;
        }
        $o['playlistTitle'] .= $o['title'] . " &rsaquo; ";

        $o['playlistTitle'] = rtrim($o['playlistTitle'], " &rsaquo; ");

        // Set album art
        $o['albumart'] = $this->getAlbumArt($id3, $dir);

        return $o;
    }

    /**
     * @tested true
     */
    public function getAlbumArt($id3, $dir=null) {
        if (file_exists("{$this->cfg->defaultMp3Dir}/{$dir}/small_cover.jpg")) {
            $albumart = "{$this->cfg->defaultMp3Url}/{$dir}/small_cover.jpg";
        } else if (isset($id3) && isset($id3['comments']) && isset($id3['comments']['picture'])
                && isset($id3['comments']['picture'][0]) && isset($id3['comments']['picture'][0]['data'])) {
            $albumart = "data:image/jpeg;base64," . base64_encode($id3['comments']['picture'][0]['data']);
        } else if (file_exists("{$this->cfg->defaultMp3Dir}/{$dir}/cover.jpg")) {
            $albumart = "{$this->cfg->defaultMp3Url}/{$dir}/cover.jpg";
        } else {
            $albumart = "images/bigfolder.png";
        }
        return $albumart;
    }

    /**
     * @tested true
     */
    public function getId3Artist($id3) {
        $artist = false;
        if (isset($id3) && isset($id3['tags']) && isset($id3['tags']['id3v2'])
                && isset($id3['tags']['id3v2']['artist']) && isset($id3['tags']['id3v2']['artist'][0])) {
            $artist = $id3['tags']['id3v2']['artist'][0];
        } else if (isset($id3) && isset($id3['tags']) && isset($id3['tags']['id3v1'])
                && isset($id3['tags']['id3v1']['artist']) && isset($id3['tags']['id3v1']['artist'][0])) {
            $artist = $id3['tags']['id3v1']['artist'][0];
        }
        return $artist;
    }

    /**
     * @tested true
     */
    public function getId3Album($id3, $dir=null, $file=null) {
        $album = false;
        if (isset($id3) && isset($id3['tags']) && isset($id3['tags']['id3v2'])
                && isset($id3['tags']['id3v2']['album']) && isset($id3['tags']['id3v2']['album'][0])) {
            $album = $id3['tags']['id3v2']['album'][0];
        } else if (isset($id3) && isset($id3['tags']) && isset($id3['tags']['id3v1'])
                && isset($id3['tags']['id3v1']['album']) && isset($id3['tags']['id3v1']['album'][0])) {
            $album = $id3['tags']['id3v1']['album'][0];
        }
        return $album;
    }

    /**
     * @tested true
     */
    public function getId3Title($id3) {
        $title = false;
        if (isset($id3) && isset($id3['tags']) && isset($id3['tags']['id3v2'])
                && isset($id3['tags']['id3v2']['title']) && isset($id3['tags']['id3v2']['title'][0])) {
            $title = $id3['tags']['id3v2']['title'][0];
        } else if (isset($id3) && isset($id3['tags']) && isset($id3['tags']['id3v1'])
                && isset($id3['tags']['id3v1']['title']) && isset($id3['tags']['id3v1']['title'][0])) {
            $title = $id3['tags']['id3v1']['title'][0];
        }
        return $title;
    }

    public function buildFileIndexCache ($dir) {
        $dir = $this->singleSlashes($dir);

        $curdir = getcwd();

        $chdir = "";
        if ($dir === $this->cfg->defaultMp3Dir) {
            $chdir = $this->cfg->defaultMp3Dir;
            $dirLink = "";
        } else {
            if (!file_exists($this->cfg->defaultMp3Dir . '/' . $dir)) {
                return false;
            }
            $chdir = $this->cfg->defaultMp3Dir . '/' . $dir;
            $dirLink = "{$dir}/";
        }

        chdir($chdir);
        $a_files = glob("*");
        natcasesort($a_files);
        file_put_contents("fileIndex.obj", serialize($a_files));

        if (!file_exists("metadata.obj")) {
            $meta = array();
            foreach ($a_files as $k=>$file) {
                if (preg_match("/\.mp3$/i", $file)) {
                    $bitrate = $this->getBitrate($dir, $file);
                    $meta[$file] = array("bitrate" => $bitrate);
                }
            }
            file_put_contents("metadata.obj", serialize($meta));
        }

        chdir($curdir);
    }

    public function getHomeNavigation() {
        $this->t->setData(array());
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/navigation-nodirs.tmpl");
        return $this->t->compile();
    }

    /**
     * @tested true
     */
    public function getFileIndex ($dir, $coverart=null) {
        $dir = $this->singleSlashes($dir);

        $curdir = getcwd();

        $chdir = "";
        if ($dir === $this->cfg->defaultMp3Dir) {
            $chdir = $this->cfg->defaultMp3Dir;
            $dirLink = "";
        } else {
            if (!file_exists($this->cfg->defaultMp3Dir . '/' . $dir)) {
                return false;
            }
            $chdir = $this->cfg->defaultMp3Dir . '/' . $dir;
            $dirLink = "{$dir}/";
        }

        chdir($chdir);
        if (!file_exists("fileIndex.obj")) {
            $this->buildFileIndexCache($dir);
        }
        $a_files = unserialize(file_get_contents("fileIndex.obj"));
        natcasesort($a_files);

        $o = $this->buildIndex($a_files, $dirLink);
        $index = $o['index'];
        $isMp3 = $o['isMp3'];

        chdir($curdir);

        if ($dirLink == "") {
            $dir = "";
        }

        // When we're at root (/) splitting would make it look like there
        // were 2 directories. You would see Home > (empty enddir).
        if ($dir == "/") {
            $a_dir = array();
        } else {
            $a_dir = preg_split("/\//", $dir);
        }

        $backDirs = "";
        $dirCnt = count($a_dir);
        $cnt = 0;
        $url = "";
        foreach ($a_dir as $k=>$backDir) {
            $url .= "/{$backDir}";

            $enc_url = rawurlencode($url);

            $url = $this->singleSlashes($url);
            $thelinks = $this->getDropDownAlbums($url);
            $this->t->setData(array("backDir" => $backDir));

            $this->t->addData(array("thelinks"=>$thelinks, "url"=>$url));
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/breadcrumb-withdrop-and-url.tmpl");
            $a_dir[$k] = $this->t->compile();

            $cnt++;
        }
        $backDirs = implode(" ", $a_dir);

        $controls = "";
        $this->t->setData(array("backDirs" => $backDirs));
        if (preg_match("/\//", $dir)) {
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/navigation-nodirs.tmpl");
            $controls = $this->t->compile();
        } else if ($dir != "") {
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/navigation-dirs.tmpl");
            $controls = $this->t->compile();
        } else {
            $controls = "";
        }

        if ($isMp3) {
            $controls .= $this->buildPlayerControls($dir);
        }

        $searchBox = $this->buildSearchBox();

        $this->t->setData(array("searchBox" => $searchBox, "controls" => $controls, "coverart" => $coverart, "index" => $index));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/fileIndex.tmpl");
        $index = $this->t->compile();

        return $index;
    }

    public function getPublicListenHash($dir) {
        return sha1($this->cfg->publicListenKey . $dir);
    }

    public function getPermaLink($dir) {
        $enc_dir = rawurlencode($dir);
        $hash = $this->getPublicListenHash($dir);
        return $this->cfg->streamsRootDirUrl . "/index.php?h={$hash}&dir={$enc_dir}";
    }

    /**
     * @tested true
     */
    public function buildPlayerControls($dir) {
        $enc_dir = rawurlencode($dir);
        $permalink = $this->getPermaLink($dir);

        $fdb = "{$this->cfg->streamsRootDir}/{$this->auth->userDir}/files.db";
        $addRemove = "+";

        // Create add-to radio button.
        $html_dir = preg_replace("/\"/", "\\\"", $dir);
        $this->t->setData(array("html_dir" => $html_dir));

        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-add-radio.tmpl");
        $button = $this->t->compile();

        foreach (file($fdb) as $l) {
            // Must strip the first slash. `$dir` is passed in like `/MyMusic/Rock/Band/Album`
            // The radio playlist is a relative path like `MyMusic/Rock/Band/Album`
            if (preg_match("/" . preg_quote(preg_replace("/^\//", "", $dir), "/") . "/i", trim($l))) {
                $addRemove = "&ndash;";

                // Create remove radio button.
                $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-remove-radio.tmpl");
                $button = $this->t->compile();
                break;
            }
        }

        $this->t->setData(array("data-dir" => $dir, "dir" => $dir, "enc_dir" => $enc_dir, 
                "permalink" => $permalink, "add-remove-control" => $addRemove, "button" => $button));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/playerControls.tmpl");
        $controls = $this->t->compile();
        return $controls;
    }

    /**
     * @tested true
     */
    public function buildSearchBox() {
        $this->t->setData(array());
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/searchBox.tmpl");
        $html = $this->t->compile();
        return $html;
    }

    /**
     * @tested true
     */
    public function getDropDownAlbums($dir) {
        $curdir = getcwd();
        chdir("{$this->cfg->defaultMp3Dir}/{$dir}");
        $a_available_dirs = glob("*", GLOB_ONLYDIR);
        natcasesort($a_available_dirs);
        $thelinks = "";
        foreach ($a_available_dirs as $k5=>$thisdir) {
            $enc_thisdir = urlencode($dir . "/" . $thisdir);
            $enc_thisdir = $this->singleSlashes($enc_thisdir);
            $html_thisdir = htmlspecialchars($thisdir);
            $html_thisdir = $this->singleSlashes($html_thisdir);
            $enc_html_thisdir = preg_replace("/\"/", "\\\"", $dir . "/" . $thisdir);
            $enc_html_thisdir = $this->singleSlashes($enc_html_thisdir);
            $this->t->setData(array("html_dir" => $html_thisdir, "enc_html_dir" => $enc_html_thisdir));
            if (file_exists("{$thisdir}/small_montage.jpg")) {
                $this->t->addData(array("src_img"=>
                        "{$this->cfg->defaultMp3Url}/{$dir}/{$html_thisdir}/small_montage.jpg"));
            } else if (file_exists("{$thisdir}/small_cover.jpg")) {
                $this->t->addData(array("src_img"=>
                        "{$this->cfg->defaultMp3Url}/{$dir}/{$html_thisdir}/small_cover.jpg"));
            } else {
                $a_tmpl['src_img'] = "images/bigfolder.png";
                $this->t->addData(array("src_img"=>"images/bigfolder.png"));
            }
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/drop-albums.tmpl");
            $thelinks .= $this->t->compile();
        }
        chdir($curdir);
        if ( $thelinks == "" ) {
            return false;
        }
        return $thelinks;
    }

    /**
     * @tested true
     */
    public function singleSlashes($in) {
        return preg_replace("/\/\/*/", "/", $in);
    }

    /**
     * @tested false - not testable.
     */
    public function print_gzipped_page() {
        global $HTTP_ACCEPT_ENCODING;
        if (headers_sent()) {
            $encoding = false;
        } else if (strpos($HTTP_ACCEPT_ENCODING, 'x-gzip') !== false) {
            $encoding = 'x-gzip';
        } else if (strpos($HTTP_ACCEPT_ENCODING,'gzip') !== false) {
            $encoding = 'gzip';
        } else {
            $encoding = false;
        }

        if ($encoding) {
            $contents = ob_get_contents();
            ob_end_clean();
            header('Content-Encoding: '.$encoding);
            print("\x1f\x8b\x08\x00\x00\x00\x00\x00");
            $size = strlen($contents);
            $contents = gzcompress($contents, 9);
            $contents = substr($contents, 0, $size);
            print($contents);
            exit();
        } else {
            ob_end_flush();
            exit();
        }
    }

    /**
     * @tested false
     */
    public function cleanQuery($q) {
        $q = trim($q);
        $q = preg_replace("/\s\s*/", " ", $q);
        return $q;
    }

    /**
     * @tested true
     */
    public function buildSearchQuery($regex) {
        $a_regex = array($regex);
        if (preg_match("/\".*?\"/", $regex)) {
            // Create phrase array
            $phrase = array();
            while (preg_match("/\"(.*?)\"/", $regex)) {
                $phrase[]= preg_replace("/^.*\"(.*?)\".*$/", "\${1}", $regex);
                $regex = preg_replace("/^(.*)\".*?\"(.*)$/", "\${1} \${2}", $regex);
                $regex = $this->cleanQuery($regex);
            }
            $regex = $this->cleanQuery($regex);
            // Split the rest of the terms into array
            // We must first check to see if the resulting $regex still contains a query.
            if (preg_match("/[^\s]/", $regex)) {
                $rest = preg_split("/ /", $regex);
                // Merge phrase and the rest.
                $reversed = array_reverse($phrase);
                $a_regex = array_merge($a_regex, $reversed, $rest);
            } else {
                $a_regex = array_merge($a_regex, $phrase);
            }
        } else {
            $a_regex = array_merge($a_regex, preg_split("/ /", $regex));
        }
        return array_unique($a_regex);
    }

    /**
     * Word searches. Each word is search on and merged with results.
     * Exact phrase searches are possible when wrapped in quotations.
     *
     * @param $a Array with each value being the index for a particular album.
     * @param $regex The search query string.
     * @param $keys The keys from $a that match $regex.
     *
     * @tested true
     */
    public function searchArray($regex, $a, $keys=array()) {
        $regex = $this->cleanQuery($regex);
        if(is_array($a)) {
            $a_regex = $this->buildSearchQuery($regex);
            foreach ($a_regex as $k2=>$word) {
                foreach($a as $k=>$v) {
                    if(is_array($v)) {
                        $this->searchArray($word, $val, $keys);
                    } else {
                        if(preg_match("/" . preg_quote($word, "/") . "/i", $v)) {
                            $keys[] = $k;
                        }
                    }
                }
            }
            $u = array_unique($keys);
            if (is_array($u)) {
                return $u;
            }
        }
        return array();
    }

    /**
     * @tested true
     */
    public function search($q) {
        $f = file($this->cfg->searchDatabase);
        $results = $this->searchArray($q, $f);

        $curdir = getcwd();

        $a_files = array();
        $o['index'] = "";
        $o['isMp3'] = false;
        $index = "";
        foreach ($results as $k=>$key) {
            $r = explode(":::", $f[$key]);
            $dir = $r[0];

            // Don't return directories that don't contain music.
            $cntmusic = count(glob("{$this->cfg->defaultMp3Dir}/{$dir}/*.{"
                    . $this->cfg->getValidMusicTypes("glob") . "}", GLOB_BRACE));
            if ($cntmusic < 1) {
                continue;
            }

            if (!file_exists($this->cfg->defaultMp3Dir . '/' . $dir)) {
                continue;
            }
            $dirLink = "/" . preg_replace("/^(.*)\/.*$/", "\${1}", $dir) . "/";

            $reldir = preg_replace("/^.*\/(.*)$/", "\${1}", $dir);;
            $a_files[] = $reldir;
            $chdir = preg_replace("/^(.*)\/.*$/", "\${1}", $this->cfg->defaultMp3Dir . "/" . $dir);
            chdir($chdir);
            $o = $this->buildIndex($a_files, $dirLink, true);
            if ($k > $this->cfg->maxSearchResults) {
                break;
            }
            $index .= $o['index'];
            unset($o);
            unset($a_files);
            chdir($curdir);
        }
        if ($index == "") {
            $this->t->setData(array("title"=>"No results"));
            $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/albumListItem.tmpl");
            $index .= $this->t->compile();
        }

        return $index;
    }

    /**
     * @tested true
     */
    public function buildArrayFromDir($dir) {
        $curdir = getcwd();

        chdir($this->cfg->defaultMp3Dir . '/' . $dir);

        $a_files = glob("*.{" . $this->cfg->getValidMusicTypes("glob") . "}", GLOB_BRACE);
        natcasesort($a_files);
        chdir($curdir);

        return $a_files;
    }

    /**
     * @tested true
     */
    public function buildPlaylistArrayFromDir($dir) {
        $curdir = getcwd();

        $playlist = array();

        $a_files = $this->buildArrayFromDir($dir);
        natcasesort($a_files);
        foreach ($a_files as $k=>$file) {
            $playlist[] = $this->buildPlaylistItemArray($dir, $file);
        }

        if (is_array($playlist)) {
            $o = $playlist;
        } else {
            $o = array();
        }

        return $o;
    }

    /**
     * @tested true
     */
    public function buildPlaylistArrayFromFile($dir, $file) {
        $curdir = getcwd();

        $playlist = array();

        $playlist[] = $this->buildPlaylistItemArray($dir, $file);

        if (is_array($playlist)) {
            $o = $playlist;
        } else {
            $o = array();
        }

        return $o;
    }

    /**
     * @param $playlistArray Pass in a playlist array, and build another playlist from $dir. Append
     * this new playlist to the one passed in and return a new playlist.
     *
     * @tested true
     */
    public function buildPlaylistFromDir($dir) {
        $playlist = $this->buildPlaylistArrayFromDir($dir);
        $json = json_encode($playlist);
        return $json;
    }

    /**
     * @tested true
     */
    public function buildPlaylistFromFile($dir, $file) {
        $playlist = $this->buildPlaylistArrayFromFile($dir, $file);
        $json = json_encode($playlist);
        return $json;
    }

    /**
     * TODO: We should look for the artist and album from ID3 tags first. If we look at the current 
     *       directory and the one above, we can't be sure they are album and artist respectively.
     *
     * @tested true
     */
    public function buildPlayerAlbumTitle($dir) {
        if (preg_match("/\/.*\//", $dir)) {
            $artist = preg_replace("/^.*\/(.*?)\/.*$/", "\${1}", $dir);
            $html_artist = htmlspecialchars($artist);
            $album = preg_replace("/^.*\/.*?\/(.*)$/", "\${1}", $dir);
            $html_album = htmlspecialchars($album);
            $html_dir = "{$html_artist} &rsaquo; {$html_album}";
        } else {
            $album = preg_replace("/^.*\/(.*)$/", "\${1}", $dir);
            $html_dir = htmlspecialchars($album);
        }
        return $html_dir;
    }

    /**
     * @tested true
     */
    public function buildPlayerHtml($playlist, $dir, $autoplay='true') {
        $this->t->setData(array("playlist" => $playlist, "autoplay" => $autoplay, "volume" => $this->getVolume()));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/jplayer.tmpl");
        $flashPlayer = $this->t->compile();

        // This #theurl span is required. Without it the player javascript
        // doesn't function. The pause button will just restart and play the list.
        $esc_dir = preg_replace("/\\\"/", "\"", $dir);
        $esc_dir = preg_replace("/\"/", "\\\"", $esc_dir);
        $html_dir = $this->buildPlayerAlbumTitle($dir);
        $this->t->setData(array("esc_dir"=>$esc_dir, "html_dir"=>$html_dir, "flashPlayer"=>$flashPlayer));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/contentPlayer.tmpl");
        $html = $this->t->compile();

        return $html;
    }

    /**
     * @tested true
     */
    public function addToPlaylist($dir) {
        $currentPlaylistArray = json_decode(file_get_contents($this->auth->currentPlaylist));
        $toAddJson = $this->buildPlaylistFromDir($dir);
        $toAddArray = json_decode($toAddJson);
        $newPlaylist = array_merge($currentPlaylistArray, $toAddArray);
        $newPlaylistJson = json_encode($newPlaylist);
        file_put_contents($this->auth->currentPlaylist, $newPlaylistJson);
        file_put_contents($this->auth->currentPlaylistDir, "/Custom playlist");
        return $toAddJson;
    }

    /**
     * @tested true
     */
    public function addToPlaylistFile($dir, $file) {
        $currentPlaylistArray = json_decode(file_get_contents($this->auth->currentPlaylist));
        $toAddJson = $this->buildPlaylistFromFile($dir, $file);
        $toAddArray = json_decode($toAddJson);
        $newPlaylist = array_merge($currentPlaylistArray, $toAddArray);
        $newPlaylistJson = json_encode($newPlaylist);
        file_put_contents($this->auth->currentPlaylist, $newPlaylistJson);
        file_put_contents($this->auth->currentPlaylistDir, "/Custom playlist");
        return $toAddJson;
    }

    /**
     * @tested true
     */
    public function clearPlaylist() {
        file_put_contents($this->auth->currentPlaylist, json_encode(array()));
        file_put_contents($this->auth->currentPlaylistDir, "/Custom playlist");
        return "{}";
    }

    /**
     * @tested false - not easily tested - will do this one later.
     */
    public function logout() {
        unset($_SESSION['auth']);
        unset($_SESSION);
        session_destroy();
    }

    /**
     * @tested true
     */
    public function getRandomPlaylistJson($numItems, $personalRadioDatabase=null) {
        $radioDatabase = $this->cfg->radioDatabase;
        if (isset($personalRadioDatabase) && $personalRadioDatabase != null 
                && $personalRadioDatabase != "undefined") {
            $radioDatabase = $personalRadioDatabase;
        }

        $f = file($radioDatabase);
        $c = count($f);

        // Return if there are now files in the database.
        if ($c === 0) {
            return json_encode(array());
        }

        // Make sure the passed values is an integer.
        $numItems = intval($numItems);

        // Make sure the passed number is less than the total number of files.
        if ($c < $numItems) {
            $numItems = $c;
        } else if ($numItems === 0) {
            $numItems = 1;
        }

        // Make sure $items is an array. array_rand() returns an integer if there's only 1 value.
        if ($numItems === 1) {
            $items = array(array_rand($f, $numItems));
        } else {
            $items = array_rand($f, $numItems);
        }

        // Build the playlist
        $playlist = array();
        foreach ($items as $k=>$key) {
            $audioFile = trim($f[$key]);

            $dir = preg_replace("/^(.*)\/.*$/", "\${1}", $audioFile);
            $file = preg_replace("/^.*\/(.*)$/", "\${1}", $audioFile);

            $playlist[] = $this->buildPlaylistItemArray($dir, $file);
        }

        return json_encode($playlist);
    }

    /**
     * @tested true
     */
    public function buildPlaylistItemArray($dir, $file) {
        $dir = $this->singleSlashes($dir);
        $file = $this->singleSlashes($file);
        $enc_dir = $this->urlEncodeDir($dir);
        $enc_file = rawurlencode($file);

        $directMusicUrl = "{$this->cfg->defaultMp3Url}/{$enc_dir}/{$enc_file}";
        $js_directMusicUrl = "{$this->cfg->defaultMp3Url}/{$enc_dir}/{$enc_file}";
        $id3 = $this->id3($dir, $file);

        $playlist = array("mp3"=>$js_directMusicUrl, "title"=>$this->buildPlaylistTitle($id3, $dir, $file), "dir"=>$dir, "file"=>$file);
        return $playlist;
    }

    /**
     * @tested true
     */
    public function urlEncodeDir($dir) {
        $adir = explode("/", $dir);
        foreach ($adir as $adk=>$adv) {
            $adir[$adk] = rawurlencode($adv);
        }
        $enc_dir = implode("/", $adir);
        return $enc_dir;
    }

    /**
     * @tested true
     */
    public function buildPlaylistTitle($id3, $dir, $file) {
        $enc_dir = preg_replace("/\"/", "\\\"", $dir);
        $enc_file = preg_replace("/\"/", "\\\"", $file);
        $this->t->setData(array("albumart"=>$id3['albumart'], "playlistTitle"=>$id3['playlistTitle'],
                "dir"=>$enc_dir, "file"=>$enc_file));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/playlistTitle.tmpl");
        $html = $this->t->compile();
        return $html;
    }

    /**
     * @tested true
     */
    public function playRadio($num) {
        $playlist = $this->getRandomPlaylistJson($num);
        $html = $this->buildPlayerHtml($playlist, null, 'true');
        return $html;
    }

    /**
     * @tested true
     */
    public function createPlaylistJs($dir) {
        $html = "";
        if (file_exists($this->cfg->defaultMp3Dir . '/' . $dir)
                && is_dir($this->cfg->defaultMp3Dir . '/' . $dir)) {
            $playlist = $this->buildPlaylistFromDir($dir);
            file_put_contents($this->auth->currentPlaylist, $playlist);
            file_put_contents($this->auth->currentPlaylistDir, $dir);
            // Set to 'false' if you don't want the player to autostart
            // TODO: Set this to 'false', but make the blue 'Play' button
            //       say something like. 'Open album'. Then use the player
            //       play button.
            $html = $this->buildPlayerHtml($playlist, $dir, 'true');
        }
        return $html;
    }

    /**
     * @tested true
     */
    public function getHomeIndex() {
        // TODO: Break this $dirLink logic into function. Follow openTheDir() > getFileIndex()
        //       to find uses. This was taken from there. Possibly other uses.
        $dir = "/";
        $curdir = getcwd();
        $chdir = "";
        if ($dir === $this->cfg->defaultMp3Dir) {
            $chdir = $this->cfg->defaultMp3Dir;
            $dirLink = "";
        } else {
            if (!file_exists($this->cfg->defaultMp3Dir . '/' . $dir)) {
                return false;
            }
            $chdir = $this->cfg->defaultMp3Dir . '/' . $dir;
            $dirLink = "{$dir}/";
        }
        chdir($chdir);
        $a_files = glob("*");
        natcasesort($a_files);

        $o = $this->buildIndex($a_files, $dirLink);
        $index = $o['index'];
        $isMp3 = $o['isMp3'];
        chdir($curdir);
        return $index;
    }

    public function createPersonalRadio($dir, $num) {
        $dir = $this->singleSlashes($dir);
        $indexer = new StreamsSearchIndexer($this->cfg, $this->auth);
        $userDir = $this->auth->userDir;
        $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
        $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";
        $indexer->setDb($db);
        $indexer->setFdb($fdb);
        $indexer->setVerbose(false);
        $indexer->setDir($dir);
        $indexer->setDirlistFile($this->cfg->streamsRootDir . "/" . $userDir . "/dir.list");
        $indexer->index();
        $playlist = $this->getRandomPlaylistJson($num, $fdb);
        $html = $this->buildPlayerHtml($playlist, null, 'true');
        $o = array("status"=>"ok", "message"=>"Playing radio for {$dir}", "contentPlayer"=>$html);
        return json_encode($o);
    }

    public function startPersonalRadio($num, $station) {
        $userDir = $this->auth->userDir;
        $searchDb = "{$this->cfg->streamsRootDir}/{$userDir}/stations/{$station}.search.db";
        $radioDb = "{$this->cfg->streamsRootDir}/{$userDir}/stations/{$station}.files.db";
        if (file_exists($searchDb) && file_exists($radioDb)) {
            $playlist = $this->getRandomPlaylistJson($num, $radioDb);
        } else {
            $indexer = new StreamsSearchIndexer($this->cfg, $this->auth);
            $userDir = $this->auth->userDir;
            $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
            $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";
            $playlist = $this->getRandomPlaylistJson($num, $fdb);
        }
        $html = $this->buildPlayerHtml($playlist, null, 'true');
        $o = array("status"=>"ok", "message"=>"Playing radio for {$dir}", "contentPlayer"=>$html);
        return json_encode($o);
    }

    public function removeFromPlaylist($dir, $file) {
        //$auth->currentPlaylist = $auth->userDir . "/currentPlaylist.obj";
        $playlist = json_decode(file_get_contents($this->auth->currentPlaylist), true);
        $newPlaylist = array();
        foreach ($playlist as $k=>$item) {
            if ($item['dir'] === $dir && $item['file'] === $file) {
                unset($playlist[$k]);
            } else {
                array_push($newPlaylist, $item);
            }
        }
        file_put_contents($this->auth->currentPlaylist, json_encode($newPlaylist));
        return json_encode(array());
    }

    public function addToPersonalRadio($dir, $station=null) {
        $dir = $this->singleSlashes($dir);
        $indexer = new StreamsSearchIndexer($this->cfg, $this->auth);
        $userDir = $this->auth->userDir;
        if ($station != null) {
            $db = "{$this->cfg->streamsRootDir}/{$userDir}/stations/{$station}.search.db";
            $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/stations/{$station}.files.db";
        } else {
            $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
            $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";
        }
        $indexer->setDb($db);
        $indexer->setFdb($fdb);
        $indexer->setVerbose(false);
        $indexer->setDir($dir);
        $indexer->setDirlistFile($this->cfg->streamsRootDir . "/" . $userDir . "/dir.list");
        $indexer->setAddToIndex(true);
        $indexer->index();

        // Create add-to radio button.
        $html_dir = preg_replace("/\"/", "\\\"", $dir);
        $this->t->setData(array("html_dir" => $html_dir));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-remove-radio.tmpl");
        $createRemoveRadioButton = $this->t->compile();

        $o = array("status"=>"ok", "message"=>"Added {$dir} to personal radio station.", 
                "button"=>$createRemoveRadioButton);
        return json_encode($o);
    }

    public function removeFromPersonalRadio($dir, $station) {
        $userDir = $this->auth->userDir;
        $searchDb = "{$this->cfg->streamsRootDir}/{$userDir}/stations/{$station}.search.db";
        $radioDb = "{$this->cfg->streamsRootDir}/{$userDir}/stations/{$station}.files.db";
        if (file_exists($searchDb) && file_exists($radioDb)) {
            $fdb = $radioDb;
            $db = $searchDb;
        } else {
            $fdb = "{$this->cfg->streamsRootDir}/{$userDir}/files.db";
            $db = "{$this->cfg->streamsRootDir}/{$userDir}/search.db";
        }

        $dir = trim($this->singleSlashes("/" . $dir));
        if (file_exists($fdb)) {
            $f = file($fdb);
            $found = false;
            if (is_array($f)) {
                foreach ($f as $k=>$album) {
                    $album = trim($this->singleSlashes("/" . $album));
                    if (preg_match("/^" . preg_quote($dir, "/") . "\//i", $album)) {
                        $found = true;
                        unset($f[$k]);
                    }
                }
            }
            if ($found) {
                file_put_contents($fdb, implode("", $f));
            }
            unset($f); unset($k); unset($album);
        }

        if (file_exists($db)) {
            $f = file($db);
            $found = false;
            if (is_array($f)) {
                foreach ($f as $k=>$album) {
                    $album = trim(preg_replace("/^(.*?):::.*$/", "\${1}", $this->singleSlashes("/" . $album)));
                    if ($dir == $album) {
                        $found = true;
                        unset($f[$k]);
                    }
                }
            }
            if ($found) {
                file_put_contents($db, implode("", $f));
            }
        }

        // Create remove radio button.
        $html_dir = preg_replace("/\"/", "\\\"", $dir);
        $this->t->setData(array("html_dir" => $html_dir));
        $this->t->setFile("{$this->cfg->streamsRootDir}/tmpl/create-add-radio.tmpl");
        $createAddRadioButton = $this->t->compile();

        $o = array("status"=>"ok", "message"=>"Removed from personal radio station.", "button"=>$createAddRadioButton);
        return json_encode($o);
    }

    public function isRestricted($file) {
        if (!is_array($this->auth->restrictedUsers)) {
            return false;
        }
        $fullPath = $this->cfg->defaultMp3Dir . $file;
        if (in_array($this->auth->username, $this->auth->restrictedUsers)) {
            $getID3 = new getID3();
            $getID3->setOption(array("encoding" => "UTF-8"));
            $id3 = $getID3->analyze($fullPath);
            $id3String = serialize($id3);
            if (preg_match("/amazon/i", $id3String)) {
                return true;
            }
            if (preg_match("/itunes/i", $id3String)) {
                return true;
            }
        }
        return false;
    }

    public function getVolume() {
        $userDir = $this->auth->userDir;
        $volumeFile = "{$this->cfg->streamsRootDir}/{$userDir}/volume.db";
        if (!file_exists($volumeFile)) {
            file_put_contents($volumeFile, json_encode(array("volume" => "0.5")));
        }
        $json = json_decode(file_get_contents($volumeFile));
        return $json->volume;
    }

    public function saveVolume($volume) {
        $userDir = $this->auth->userDir;
        $volumeFile = "{$this->cfg->streamsRootDir}/{$userDir}/volume.db";
        file_put_contents($volumeFile, json_encode(array("volume" => $volume)));
        return json_encode(array("status" => "ok", "volume" => $this->getVolume()));
    }

}
