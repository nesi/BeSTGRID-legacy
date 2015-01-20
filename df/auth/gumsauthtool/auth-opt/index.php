<?php
require "config.php";

$mapfile = "../mapfile/mapfile";
$lockfile = "maplock";
$userDN = $_SERVER['SSL_CLIENT_S_DN'];
$username = $_SERVER['PHP_AUTH_USER'];

if (!$userDN) {
  print "<strong>Unfortunately, you have not presented an SSL certificate " . 
        "to this site.</strong><p>" .
        "This most likely means that you do not have your certificate loaded " . 
        "in your browser.<p>" . 
        "Please import your certificate into your browser and configure your " . 
        "browser to use the certificate for SSL connections.<p>" . 
        "After configuring your certificate, please try connecting " . 
        "here <a href=\"\">again</a>.";
  //print "You haven't presented an SSL certificate, which means ";
  //print "the server is misconfigured.  Please correct and try again.";
  exit;
 }

if (!$username) {
  print "You haven't presented a login username, which means ";
  print "the server is misconfigured.  Please correct and try again.";
  exit;
 }


if ($_POST['map']) {
  lock_map(); #indent like critical region.
		if (!is_mapped($userDN)) {
		  add_mapping($userDN, $username);
		  print "mapping added. <br />";
		} else {
		  print "you are already mapped.<br />";
		}
  show_mapping($userDN, $username);
  unlock_map();

 } else if ($_POST['unmap']) {
  lock_map();
  del_mapping($userDN);
  show_mapping($userDN, $username);
  unlock_map();
 } else {
  lock_map();
  show_mapping($userDN, $username);
  unlock_map();
 }


?>
<form method="post" action="<?php print $_SERVER['PHP_SELF'];?>">
<input type="submit" name="map" value="Map Me" /> <br />
<input type="submit" name="unmap" value="Unmap Me" /> <br />
</form>

<?php

#create a lockfile for the map
function lock_map() {
  require "config.php";
  $attempt_count = 0;
  while (file_exists($GLOBALS["lockfile"])) {

    if ($attempt_count > 5) {
      print "This mapping tool is currently locked.";
      print "Please try again later.";
      exit;
    }

    sleep(1);
    $attemp_count++;
  }
  touch($GLOBALS["lockfile"]);
}


#remove lockfile for the map
function unlock_map() {
  require "config.php";
  return unlink($GLOBALS["lockfile"]);
}

#search for matching DNs in the mapfile
function is_mapped($userDN) {
  require "config.php";
  if ($maphandle = fopen($mapfile, "r")) {

    while (!feof($maphandle)) {
      $buffer = fgets($maphandle, 4096);

      preg_match('/"(.+)"(.+)/', $buffer, $matches);

      if ($matches[1] == $userDN)
	return $matches[2];

      $sanity_count++;
    }
    fclose($maphandle);
    return false;
  }
  return false;
}

function add_mapping($userDN, $username) {
  require "config.php";

  if ($maphandle = fopen($mapfile, "a")) {
    $mapping = "\"$userDN\" $username\n";
    fwrite($maphandle, $mapping);
    fclose($maphandle);
    return true;
  }
  return false;
}

function del_mapping($userDN) {

  require "config.php";

  if (! $maphandle = fopen($mapfile, "r"))
    return false;
  if (! $temphandle = fopen($tempfile, "a+"))
    return false;

  $mapping_exists = false;

  while (!feof($maphandle) && $count < 100) {
    $count++;
    $buffer = fgets($maphandle, 4096);

    preg_match('/"(.+)"(.+)/', $buffer, $matches);

                #write all non matching lines
    if ($matches[1] == $userDN)
      $mapping_exists = true;
    else
      fwrite($temphandle, $buffer);
  }
        #close files
  fclose($maphandle);
  fclose($temphandle);

        #delete old mapfile and move in new one
  unlink ($mapfile);
  rename ($tempfile, $mapfile);
  return $mapping_exists;

}

function show_mapping($userDN, $username) {

  print "DN: $userDN<br />";
  print "University of Canterbury HPC username: $username<br />";

  $mapped = is_mapped($userDN);

  if ($mapped)
    print "you are currently mapped to: $mapped";
  else
    print "you are not currently mapped.";

}
?>
