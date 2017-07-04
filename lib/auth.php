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

if ($auth->tries > $auth->maxTries) {
    $auth->message = "<strong>Locked out.</strong><br />";
    $auth->disabled = "disabled='disabled'";
}

if ($_GET['action'] == "login" && $auth->tries > $auth->maxTries) {
    $_SESSION['auth'] = serialize($auth);
    header("Location:{$_SERVER['PHP_SELF']}");
    exit();
}

/**
 * Users can have multiple passwords. I know this is crazy.
 */
function validateUser($hashFunction, $postPassword, $actualPassword) {
    if (is_array($actualPassword)) {
        foreach ($actualPassword as $password) {
            if ($hashFunction($postPassword) === $password) {
                return true;
            }
        }
        return false;
    } else {
        return $hashFunction($postPassword) === $actualPassword;
    }
}

if ($_GET['action'] == "login") {
    if (array_key_exists($_POST['username'], $auth->users)) {
        $hashFunction = $cfg->hashFunction;
        //if ($hashFunction($_POST['password']) == $auth->users[$_POST['username']]) {
        if (validateUser($hashFunction, $_POST['password'], $auth->users[$_POST['username']])) {
            // Set session login variables.
            if (!file_exists("sessions/users")) {
                mkdir("sessions/users");
            }
            $auth->userDir = "sessions/users/{$_POST['username']}";
            if (!file_exists($auth->userDir)) {
                mkdir($auth->userDir);
            }
            $auth->username = $_POST['username'];
            $auth->is_logged_in = true;
            $auth->tries = 0;
            $auth->currentPlaylist = $auth->userDir . "/currentPlaylist.obj";
            $auth->currentPlaylistDir = $auth->userDir . "/currentPlaylistDir.obj";
            $_SESSION['auth'] = serialize($auth);
            header("Location:{$_SERVER['PHP_SELF']}");
            exit();
        } else {
            $auth->tries = $auth->tries + 1;
        }
    } else {
        $auth->tries = $auth->tries + 1;
    }
}

if (!$auth->is_logged_in || $auth->tries > $auth->maxTries) {
    $_SESSION['auth'] = serialize($auth);

    $t->setData(array("message"=>$auth->message, "self"=>$_SERVER['PHP_SELF'], "disabled"=>$auth->disabled));
    $t->setFile("{$cfg->streamsRootDir}/tmpl/loginForm.tmpl");
    $pageContent = $t->compile();

    $t->setData(array("viewport" => $viewport, "pageContent" => $pageContent, 
            "message" => $message, "jsMobileVar" => $jsMobileVar, "mobileCss" => $mobileCss));
    $t->setFile("{$cfg->streamsRootDir}/tmpl/index.tmpl");
    $html = $t->compile();

    print($html);
    die();
}
