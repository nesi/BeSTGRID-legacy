<?php

# == Shared code between different AuthTool instances ==

function check_handle_logoff($shib_logoff_extra_html) {
  require "../config.php";

  if (isset($_POST['logoff'])) {
    unset($_SESSION["username"]);
    print "You have logged off from your $site_name account.<p>\n";
    print "You can <a href=\"" . $_SERVER['REQUEST_URI'] . "\">login again</a><p>\n";
    print $shib_logoff_extra_html;
    print "<p><hr>\n";
    exit;
  };

}

function authtool_init_session() {
  # initialize PHP session
  session_name("authtool");
  #  void session_set_cookie_params  ( int $lifetime  [, string $path  [, string $domain  [, bool $secure = false  [, bool $httponly = false  ]]]] )
  $session_expiry_time = 3600; # 1 hour
  session_set_cookie_params($session_expiry_time, $_SERVER["REQUEST_URI"], $_SERVER["HTTP_HOST"], true);
  session_start();
}

function check_local_account() {
  require "../config.php";

  $AuthToolUsername = isset($_REQUEST["AuthToolUsername"]) ? $_REQUEST["AuthToolUsername"] : "";
  $AuthToolPassword = isset($_REQUEST["AuthToolPassword"]) ? $_REQUEST["AuthToolPassword"] : "";

  if ($AuthToolUsername && $AuthToolPassword) {
    unset($_SESSION["username"]);
    $descriptorspec = array(
	0 => array("pipe", "r"),
	1 => array("file", "php://stderr", "a"),
	2 => array("file", "php://stderr", "a")
    );
    $return_value = -1;
    $process = proc_open($external_auth, $descriptorspec, $pipes);
    if (is_resource($process)) {
	fwrite($pipes[0],"$AuthToolUsername\n$AuthToolPassword\n");
	fclose($pipes[0]);

	$return_value = proc_close($process);
	if ($return_value == 0) {
	    $username = $AuthToolUsername;
	    $_SESSION["username"] = $AuthToolUsername;
	};
    };
  };

  if (isset($_SESSION["username"])) $username = $_SESSION["username"];

  if (! isset($username) || !$username) {
    if ($AuthToolUsername) {
       print "Invalid username / password.<p>\n";
    };
    print "Please enter your username and password for the $site_name.<p>";
    print '<FORM METHOD="POST" action="'.$_SERVER['REQUEST_URI'].'" name="AuthToolLogin"><p>' . "\n";
    print '<INPUT TYPE="text" name="AuthToolUsername" size="20" value="" maxlength="20" ><p>' . "\n";
    print '<INPUT TYPE="password" name="AuthToolPassword" size="20" value="" maxlength="20" ><p>' . "\n";
    print '<INPUT NAME="Submit" TYPE="SUBMIT" VALUE="Submit" ><p>' . "\n";
    print '</FORM><p>' . "\n";
    exit;
  };
  return $username;
};



function check_handle_mapping_actions($userDN, $username) {
  if (isset($_POST['map'])) {
    lock_map(); 
        #indent like critical region.
	if (!is_mapped($userDN)) {
	  add_mapping($userDN, $username);
	  print "mapping added. <br />";
	} else {
	  print "you are already mapped.<br />";
	}
        show_mapping($userDN, $username);
    unlock_map();
  } else if (isset($_POST['unmap'])) {
    lock_map();
        #indent like critical region.
	del_mapping($userDN);
	print "mapping removed. <br />";
	show_mapping($userDN, $username);
    unlock_map();
  } else {
    lock_map();
        #indent like critical region.
	show_mapping($userDN, $username);
    unlock_map();
  }
}

function render_mapping_buttons() {
?>
<form method="post" action="<?php print $_SERVER['REQUEST_URI'];?>">
<input type="submit" name="map" value="Map Me" /> <br />
<input type="submit" name="unmap" value="Unmap Me" /> <br />
<input type="submit" name="logoff" value="Log off" /> <br />
</form>
<?php
}

# == Mapping handling functions ==

function abort_with_error($error_message, $do_unlock = false) {
   print "$error_message<p>\n";
   if ($do_unlock) unlock_map();
   exit;
}

#create a lockfile for the map
function lock_map() {
  require "../config.php";
  $attempt_count = 0;
  while (file_exists($GLOBALS["lockfile"])) {

    if ($attempt_count > 5) {
      abort_with_error("This mapping tool is currently locked.  Please try again later.");
    }

    sleep(1);
    $attemp_count++;
  }
  $result = touch($GLOBALS["lockfile"]);
  if (!$result) abort_with_error("Error creating lock file - aborting.");
}


#remove lockfile for the map
function unlock_map() {
  require "../config.php";
  $result = unlink($GLOBALS["lockfile"]);
  if (!$result) abort_with_error("Error deleting lock file - aborting.");
}

#search for matching DNs in the mapfile
#called with the mapfile locked
function is_mapped($userDN) {
  require "../config.php";
  if ($maphandle = fopen($mapfile, "r")) {

    while (!feof($maphandle)) {
      $buffer = fgets($maphandle, 4096);
      if (!$buffer) continue;

      preg_match('/"(.+)"(.+)/', $buffer, $matches);

      if ($matches[1] == $userDN)
	return $matches[2];

      // NOT USED: $sanity_count++;
    }
    fclose($maphandle);
    return false;
  }
  abort_with_error("Could not open mapping file for reading.", true);
}

#called with the mapfile locked
function add_mapping($userDN, $username) {
  require "../config.php";

  if ($maphandle = fopen($mapfile, "a")) {
    $is_ok = true;
    $mapping = "\"$userDN\" $username\n";
    if (!fwrite($maphandle, $mapping)) $is_ok = false;
    if (!fclose($maphandle)) $is_ok = false;
    if (!$is_ok) abort_with_error("Error updating mapping file - aborting.", true);
    return true;
  }
  abort_with_error("Error updating mapping file - aborting.", true);
  return false;
}

#called with the mapfile locked
function del_mapping($userDN) {

  require "../config.php";

  if (! $maphandle = fopen($mapfile, "r")) abort_with_error("Could not update mapping file to delete mapping.", true);
  if (! $temphandle = fopen($tempfile, "a+")) abort_with_error("Could not create temporary mapping file to delete mapping.", true);

  $mapping_exists = false;
  $count = 0;
  $is_OK = true;

  while (!feof($maphandle) && $count < 100) {
    $count++;
    $buffer = fgets($maphandle, 4096);
    if (!$buffer) continue;

    preg_match('/"(.+)"(.+)/', $buffer, $matches);

                #write all non matching lines
    if ($matches[1] == $userDN)
      $mapping_exists = true;
    else
      if (!fwrite($temphandle, $buffer)) $is_OK = false;
  }
        #close files
  if (!fclose($maphandle)) $is_OK = false;
  if (!fclose($temphandle)) $is_OK = false;

  # last sanity check before replacing the file
  if (!$is_OK) {
      unlink($tempfile);
      abort_with_error("Could not create a new mapfile with deleted mapping, aborting", true);
  };

  #delete old mapfile and move in new one
  if (!unlink ($mapfile)) $is_OK = false;
  if (!rename ($tempfile, $mapfile)) $is_OK = false;

  if (!$is_OK) abort_with_error("Could not replace the mapfile with a new mapfile when deleting mapping, aborting", true);

  return $mapping_exists;

}

#called with the mapfile locked
function show_mapping($userDN, $username) {

  print "DN: $userDN<br />";
  print $GLOBALS["site_name"] . " username: $username<br />";

  $mapped = is_mapped($userDN);

  if ($mapped)
    print "you are currently mapped to: $mapped";
  else
    print "you are not currently mapped.";

}
?>

