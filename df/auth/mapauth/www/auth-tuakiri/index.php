<?php
require "../config.php";
require '../include/authlib.php';

$userDN = "";

/* this is how myproxyplus maps a Shibboleth login to a DN (in myproxy-mapapp.pl):

    # My DN namespace prefix

    my $namespace = "/DC=nz/DC=org/DC=nesi/DC=myproxyplus";


    if (!defined($fields{"organisation"})
      || !defined($fields{"commonName"})
      || !defined($fields{"sharedToken"})) {
      syslog("err", "Error: Required field for DN missing: \"%s\"", $input);
      exit(1);
    }

    $result = $namespace . "/O=" . $fields{"organisation"} . "/CN=" . $fields{"commonName"} . " " . $fields{"sharedToken"} . "\n";
    syslog("info", "DN: \"%s\"", $result);


  So we want to do the same: check if we have organisation, commonName and
  sharedToken and then use these to construct the DN.

*/

// check all required attributes are present
for ($i=0; $i<count($user_DN_req_attrs);$i++) {
  if (!isset($_SERVER[$user_DN_req_attrs[$i]]) || !$_SERVER[$user_DN_req_attrs[$i]]) {
    print "<strong>Unfortunately, your home organisation did not provide all of the required attributes.</strong><p>";
    print "The attributes required are: cn, shared-token.<p>\n";
    print "Your value for $user_DN_req_attrs[$i] appears to be blank (\"" . $_SERVER[$user_DN_req_attrs[$i]] . "\")\n";
    exit;
  };
};

eval("\$userDN=\"$user_DN_pattern\";");

if (!$userDN) {
  print "<strong>Unfortunately, it was not possible to construct the Distinguished Name your certificate would contain.<p>";
  exit;
};

print "Your Distinguished Name (DN) is: <tt>$userDN</tt><p>\n";

authtool_init_session();

$shib_logoff_extra_html = "Or you can also <a href=\"/Shibboleth.sso/Logout\">log out from your Tuakiri account</a>.<p>\n";
check_handle_logoff($shib_logoff_extra_html);

$username = check_local_account();
if ($username) {
  check_handle_mapping_actions($userDN, $username);
  render_mapping_buttons();
};

?>
