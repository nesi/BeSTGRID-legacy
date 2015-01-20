<?php
require "config.php";

$userDN = "";
//$userDN = $_SERVER['SSL_CLIENT_S_DN'];
//$username = $_SERVER['PHP_AUTH_USER'];

$idpMapURL = "https://slcs1.nesi.org.nz/idp-acl.txt";

$idpMapStr = `wget --no-check-certificate -O - --quiet https://slcs1.nesi.org.nz/idp-acl.txt`;

#print "Downloaded list of IdPs: $idpMapStr";

$curIdP = $_SERVER["Shib-Identity-Provider"];

$idpMap = explode("\n",$idpMapStr);

foreach ($idpMap as $idpIter) {
  $idpIterArr = explode(", ", $idpIter, 2);
  if ( ($idpIterArr[0]) && ($idpIterArr[1]) ) {
      $idpIterName = $idpIterArr[0];
      $idpIterDNBase = $idpIterArr[1];
      if ( $idpIterName == $curIdP ) {
          # OK, this user is coming from a known IdP
          $curIdPDNBase = $idpIterDNBase;
          break;
      };
  };
};

if (!$curIdPDNBase) {
  print "<strong>Unfortunately, your Identity Provider is not authorized to use this service.</strong><p>\n";
  print "Your Identity Provider entityID is: <tt>$curIdP</tt><p>\n";
  print "Please contact the <a href=\"mailto:support@nesi.org.nz\">NeSI support desk</a> if you believe you have received this message in error.\n";
  exit;
}

$AuthToolUsername = isset($_REQUEST["AuthToolUsername"]) ? $_REQUEST["AuthToolUsername"] : "";
$AuthToolPassword = isset($_REQUEST["AuthToolPassword"]) ? $_REQUEST["AuthToolPassword"] : "";

if ($_SERVER['cn'] && $_SERVER['shared-token']) {
    $userDN = $curIdPDNBase . "/CN=" . $_SERVER['cn'] . " " . $_SERVER['shared-token'];
};

if (!$userDN) {
  print "<strong>Unfortunately, you have not provided the required Shibboleth attributes.</strong><p>";
  print "The attributes required are: cn, shared-token.<p>\n";
  print "Your attributes are:\n<PRE>\n";
  print "cn=".$_SERVER['cn']. "\n";
  print "shared-token=".$_SERVER['shared-token']. "\n";
  print "</PRE>\n";
  exit;
};

# initialize PHP session
session_name("authtool");
#  void session_set_cookie_params  ( int $lifetime  [, string $path  [, string $domain  [, bool $secure = false  [, bool $httponly = false  ]]]] )
$session_expiry_time = 3600; # 1 hour
session_set_cookie_params($session_expiry_time, $_SERVER["REQUEST_URI"], $_SERVER["HTTP_HOST"], true);
session_start();

  print "Your Identity Provider entityID is: <tt>$curIdP</tt><p>\n";
  print "Distinguished Name (DN) is: <tt>$userDN</tt><p>\n";

if (isset($_POST['logoff'])) {
  unset($_SESSION["username"]);
  print "You have logged off from the Auth Tool.<p>";
  print 'You may proceed to the <a href="/Shibboleth.sso/Logout">Shibboleth Logout</a> to logout from your AAF account as well.<p>';
  print 'Or you may <a href="' . $_SERVER['REQUEST_URI'] . '">login again</a><p><p><hr>';
  exit;
};

# check password
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

if (isset($_POST['map'])) {
  lock_map(); #indent like critical region.
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
  del_mapping($userDN);
  print "mapping removed. <br />";
  show_mapping($userDN, $username);
  unlock_map();
 } else {
  lock_map();
  show_mapping($userDN, $username);
  unlock_map();
 }

?>
<form method="post" action="<?php print $_SERVER['REQUEST_URI'];?>">
<input type="submit" name="map" value="Map Me" /> <br />
<input type="submit" name="unmap" value="Unmap Me" /> <br />
<input type="submit" name="logoff" value="Log off" /> <br />
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
      if (!$buffer) continue;

      preg_match('/"(.+)"(.+)/', $buffer, $matches);

      if ($matches[1] == $userDN)
	return $matches[2];

      // NOT USED: $sanity_count++;
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
  $count = 0;

  while (!feof($maphandle) && $count < 100) {
    $count++;
    $buffer = fgets($maphandle, 4096);
    if (!$buffer) continue;

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
  print $GLOBALS["site_name"] . " username: $username<br />";

  $mapped = is_mapped($userDN);

  if ($mapped)
    print "you are currently mapped to: $mapped";
  else
    print "you are not currently mapped.";

}
?>
