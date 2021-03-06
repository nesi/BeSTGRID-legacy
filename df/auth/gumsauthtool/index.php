<HTML><HEAD><TITLE>Canterbury HPC AuthTool</TITLE></HEAD><BODY>
<h1>Welcome to the University of Canterbury HPC AuthTool</h1><p>

The AuthTool requires that you have a certificate loaded in your browser, and that you have a login username and password for your account at the University of Canterbury BlueFern NeSI systems.<p>

<?php
$user_DN = $_SERVER['SSL_CLIENT_S_DN'];

if ($user_DN) {
  print "Congratulations, you have your certificate loaded into your browser and your subject name is:<p><pre>$user_DN</pre><p>You may follow the link below to configure a mapping to your personal account.<p>";
### encode userDN : at least = as %3D, maybe / as %2F
} else {
  print "Unfortunately, it appears you do not have a certificate loaded in your browser.<p><strong>Please import your certificate into your browser.</strong> Do not follow the links below until you have done so. <p><strong>It is very likely that the link below will not work for you and that you will receive a hard to understand error message</strong>. <p>Please note that after importing your certificate, it may also be necessary to configure your browser to use the certificate to authenticate in SSL connections.  After you have loaded and configured your certificate in the browser, please return back to this <a href=\"\">AuthTool page</a>.<p>";
};
?>

<ul><li><a href="auth-opt/">University of Canterbury BlueFern NeSI Auth Tool</a></li></ul>

<?php
 if ($user_DN) {
   print "<p>As you have your certificate loaded in your browser, you may also check your <a href=\"/gums/map_grid_identity.jsp?host=/C%3DNZ/O%3DBeSTGRID/OU%3DUniversity+of+Canterbury/CN%3Dgram5p7.canterbury.ac.nz&DN=".urlencode($user_DN)."&FQAN=\">Grid Identity Mapping</a> in the GUMS service.";
  };
?>
</BODY>
</HTML>
