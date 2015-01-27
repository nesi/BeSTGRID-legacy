<?php
require "../config.php";
require '../include/authlib.php';

// in this instance, get the DN from the client SSL cert
$userDN = isset($_SERVER['SSL_CLIENT_S_DN']) ? $_SERVER['SSL_CLIENT_S_DN'] : "";


if (!$userDN) {
  print "<strong>Unfortunately, you have not presented an SSL certificate " . 
        "to this site.</strong><p>" .
        "This most likely means that you do not have your certificate loaded " . 
        "in your browser.<p>" . 
        "Please import your certificate into your browser and configure your " . 
        "browser to use the certificate for SSL connections.<p>" . 
        "After configuring your certificate, please try connecting " . 
        "here <a href=\"\">again</a>.";
  exit;
};

print "Your Distinguished Name (DN) is: <tt>$userDN</tt><p>\n";

authtool_init_session();

$shib_logoff_extra_html = "";
check_handle_logoff($shib_logoff_extra_html);

$username = check_local_account();
if ($username) {
  check_handle_mapping_actions($userDN, $username);
  render_mapping_buttons();
};

?>
