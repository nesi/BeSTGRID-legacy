<?php
require 'config.php';
?>
<HTML><HEAD><TITLE><?= $service_name ?></TITLE></HEAD><BODY>
<h1>Welcome to the <?= $service_name ?></h1><p>

This tool allows you to map your grid identity to a local account on the <?= $site_name ?>.<p>

You need to authenticate to this site with the same authentication mechanism as you would be using when transfering files to/from the <?= $site_name ?>.  (If using Globus.org to transfer files, this means which mechanism you use to activate the Globus.org endpoint).

<ul>
<li>If you are using a Tuakiri login (via the NeSI MyProxyPlus server), please <a href="auth-tuakiri/">login here with your Tuakiri login</a>.</li>
<li>If you are using an X509 certificate (such as one issued by ASGCCA or QuoVadis), please <strong>first make sure your certificate is loaded in your browser</strong> and then <a href="auth-cert/">login here with your certificate</a>.</li>
<p>

This tool requires that in addition to the authentication above (to link to your grid identity), you also have a login username and password for your account at the <?= $site_name ?>.<p>

</BODY>
</HTML>
