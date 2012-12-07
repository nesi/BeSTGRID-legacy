<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<?php

# CONFIG
include 'config.php';

$dfUrlBase = "https://$_SERVER[SERVER_NAME]";
$dfUrl = "$dfUrlBase/";

# END-CONFIG

# read properties
include 'read-properties.php';
$davis_properties = parse_properties(file_get_contents($davis_properties_file));

$df_title = $davis_properties['authentication-realm']; # note: property is "authentication-realm" but Davis substitution in ui.html is "authenticationrealm"
$irodsZone = $davis_properties['zone-name'];
$helpURL = $davis_properties['helpURL'];
$df_non_browser_tools_link = "$helpURL#$df_non_browser_tools_tag";
$logo_width = $logo_height = "";

if ( $davis_properties['organisation-logo-geometry'] && strpos($davis_properties['organisation-logo-geometry'],"x")>0) {
    # if logo geometry is specified as nnnxmmm
    $logo_geom_arr = explode("x", $davis_properties['organisation-logo-geometry'], 2);
    $logo_width = $logo_geom_arr[0];
    $logo_height = $logo_geom_arr[1];
};

# other properties used:
# ui-include-body-header (Zendesk)
## organisation-logo-geometry=400x70
# organisation-logo (note: organisationlogo in substitutions)



?>


<!-- put page title into head -->

<title><?=$df_title?> - Changing your non-web-browser tools password</title>

<!-- Load Davis + dojo stylesheets -->
    <script type="text/javascript" src="/dojoroot/dojo/dojo.js" djConfig="isDebug: false, parseOnLoad: true, preventBackButtonFix: false"></script>
    <style type="text/css">
                @import "/dojoroot/dijit/themes/tundra/tundra.css";
                @import "/dojoroot/dojox/grid/resources/Grid.css";
                @import "/dojoroot/dojox/grid/resources/tundraGrid.css";
                /*@import "<parameter dojoroot/>dojoroot/dojo/resources/dojo.css"; This is disabled. Note that dojo.css has never actually been
                 * used in WEBDavis because it wasn't followed by a ';'. Importing it changes the layout dramatically.*/
                @import "/include/davis.css";
                @import "/include/davis-override.css";
    </style>   
    <script type="text/javascript">
        dojo.require("dojox.grid.DataGrid");
        dojo.require("dojo.data.ItemFileWriteStore");
        dojo.require("dojox.data.QueryReadStore");
        dojo.require("dijit.form.Button");
        dojo.require("dijit.Menu");
        dojo.require("dijit.form.CheckBox");
        dojo.require("dijit.Dialog");
	dojo.require("dijit.form.TextBox");
	dojo.require("dojo.parser");
	dojo.require("dijit.form.FilteringSelect");
	dojo.require("dojo.io.iframe");
	dojo.require("dijit.ProgressBar");
	dojo.require("dojo.back");
	dojo.require("dojo.hash");
	dojo.require("dijit.layout.BorderContainer");
	dojo.require("dojox.widget.PlaceholderMenuItem");
	dojo.require("dijit.form.DropDownButton");
	dojo.require("dijit.form.Form");
	dojo.require("dojo.dnd.Source");

        <!-- minimum necessary functions -->
	function doHelp() {
	    var helpURL = "<?= $helpURL ?>";
	    if (helpURL.match('^(https?):\/\/')) {
		window.open(helpURL);
	    } else {
                alert(helpURL);
	    }       
	}

   </script>

</head>

<!-- start body, matching Davis stylesheet -->
<body class="tundra">

<?= $davis_properties['ui-include-body-header'] ?>

<script>dojo.back.init();</script>

<?php
# Initialize the PHP code here - do some sanity checks (https, sharedToken present) and look up the username

$isOK = true;
$errMsg = "";

$duUserName = "";
$duUserHome = "";
$duUserHomeUrl = "";

function log_message($message) {
  require 'config.php';

  // Log to $log_file
  $log_r = fopen($log_file, "a");
  $log_ok = true;
  if ($log_r) { 
      $write_res = fwrite($log_r, strftime("%c") . " $message\n"); 
      $close_res = fclose($log_r);
      if (!$write_res || !$close_res) { $log_ok = false; };
  } else { 
      $log_ok = false; 
  };
  return $log_ok;
}

if (!isset($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"]!="on")) {
    $isOK = false;
    $https_URL = "https://$_SERVER[SERVER_NAME]$_SERVER[REQUEST_URI]";
    $errMsg = "This URL MUST be accessed over HTTPS.<p>\nPlease go to <a href=\"$https_URL\">$https_URL</a> instead.";
}

# try changing the password now - if we succeed, we can link back to the account
if ($isOK) {

   $err_msg = "";
   $ok_msg = "";
   if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
       // check all input data
       
       if (!isset($_POST["DFUsername"]) || !$_POST["DFUsername"]) { $err_msg = "Username not specified"; }
       elseif (!isset($_POST["DFCurPassword"])|| !$_POST["DFCurPassword"]) { $err_msg = "Current password not specified"; }
       elseif (!isset($_POST["DFNewPassword1"]) || !isset($_POST["DFNewPassword2"]) || 
               !$_POST["DFNewPassword1"] || !$_POST["DFNewPassword2"] ) { 
               $err_msg = "You must enter the new password into both fields";
       } elseif ($_POST["DFNewPassword1"] != $_POST["DFNewPassword2"]) {
               $err_msg = "The two entries of the new password did not match";
       } elseif ($_POST["DFNewPassword1"] == $_POST["DFCurPassword"]) {
               $err_msg = "The old password and the new password are the same";
       } elseif (strlen($_POST["DFNewPassword1"]) < $min_password_length) {
	   $err_msg = "The password was too short (must be at least $min_password_length characters long)";
       } else {
           // all checks passed, let us change the password now
	   // copy _POST data into local variables, trim username BUT NOT passwords
	   $dfUserName = trim($_POST["DFUsername"]);
	   $dfCurPassword = $_POST["DFCurPassword"];
	   $dfNewPassword1 = $_POST["DFNewPassword1"];
	   $dfNewPassword2 = $_POST["DFNewPassword2"];

           $log_ok = log_message(" Attempting to change password for: username=$dfUserName based on a password login");
           if ($log_ok) {
	       $output = array();
	       $retval = 0;
               $authFileName = tempnam("/tmp","irodstmp");
               
               // now exec ipasswd, feed it the old+new passwords on stdin
               // REF: http://nz.php.net/manual/en/function.proc-open.php
	       $descriptorspec = array(
		  0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		  1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		  2 => array("pipe", "w")   // and so is stderr
	       );


	       $cwd = NULL; // leave to default
	       $env = array('irodsHost' => $irodsHost,
	                    'irodsPort' => $irodsPort,
			    'irodsZone' => $irodsZone,
			    'irodsUserName' => $dfUserName,
			    'irodsAuthFileName' => $authFileName
               );

	       $process = proc_open($ipasswd, $descriptorspec, $pipes, $cwd, $env);
	       $p_stdout = $p_stderr = "";
	       $pw_ok = true;

	       if (is_resource($process)) {
		   // $pipes now looks like this:
		   // 0 => writeable handle connected to child stdin
		   // 1 => readable handle connected to child stdout
		   // Any error output will be appended to /tmp/error-output.txt

		   fwrite($pipes[0], "$dfCurPassword\n$dfNewPassword1\n$dfNewPassword2\n");
		   fclose($pipes[0]);

		   $p_stdout = str_replace("\n",";",stream_get_contents($pipes[1]));
		   $p_stderr = str_replace("\n",";",stream_get_contents($pipes[2]));
		   fclose($pipes[1]);
		   fclose($pipes[2]);

		   // It is important that you close any pipes before calling
		   // proc_close in order to avoid a deadlock
		   $retval = proc_close($process);
		   if ($retval != 0) {
		       $pw_ok = false;
		       $err_msg = "Password change failed. You have likely entered an incorrect username/password combination.";
		   };
	           # regardless of the outcome, clean up
		   if (file_exists($authFileName)) {
		       unlink($authFileName);
		   };
	       } else { 
		   $pw_ok = false;
		   $err_msg = "Password change failed: could not launch ipasswd";
	       };


	       if ($pw_ok) {
		   $ok_msg = "Your password has been succesfully changed.";
                   $duUserName = $dfUserName; # use this as the username for rendering Davis headers content
	           log_message("Password change for user $dfUserName successful");
	       } else {
	           log_message("Password change for user $dfUserName failed. Exitcode: $retval err_msg:\"$err_msg\" ipasswd stdout:\"$p_stdout\" stderr:\"$p_stderr\"");
	       };
	   } else {
	     $err_msg = "Unfortunately, there has been an internal error: unable to log.";
	   };
       };
   };

};


if ($isOK && $duUserName) {
   $duUserHome = "/$irodsZone/home/$duUserName";
   // for password-based login, redirect users to https interface
   $duUserHomeUrl = "$dfUrlBase$duUserHome";
};


?>

<!-- try page header from Davis ui.html -->

<div jsId="borderContainer" dojoType="dijit.layout.BorderContainer" style="width: 100%; height: 100%; border: 0px; padding-top: 0px; padding-bottom: 0px;">
    <div jsId="topContainer" id="topContainer" dojoType="dijit.layout.ContentPane" region="top" style="border: 0px;">
	<span id="header">
	    <table width="100%" border="0px" cellspacing="0" cellpadding="0">
		<tr>
		    <td valign="bottom">
			<h1 class="text"><img src="<?= $davis_properties['organisation-logo'] ?>" alt="" width="<?= $logo_width ?>" height="<?= $logo_height ?>" /><!-- &nbsp;<?= $df_title ?>--> </h1>
		    </td>
		    <td align="right" valign="top">
			<table border="0" cellspacing="0" cellpadding="0">
			    <tr><td>
				<table border="0" cellspacing="0" cellpadding="0">
<?php if ($duUserName) { ?>
				    <tr class="text">
					<td colspan="100" align="right" style="padding-bottom:4px;">You are logged in as &lt;<a title="Go to your home folder" class="link" href="<?= $duUserHomeUrl ?> "><?= $duUserName ?></a>&gt;</td>
				    </tr>
<?php } ?>
				    <tr>
					<td id="sessionButtonsHTML" style="width:auto;" align="right" valign="top"></td>
<?php if ($duUserName) { ?>
					<td align="right" style="width:100%;" valign="top">
					    <span id="homeButton"><a title="Go to your home folder" href="<?= $duUserHomeUrl ?>"><img border="0" alt="home" src="/images/home.png" width="28" height="32"/></a></span>
					</td>
<?php } ?>
					<td align="left" valign="top" style="width:1px; padding-left:2px;" >
					    <button dojoType="dijit.form.DropDownButton" showLabel="false" type="button" title="Help" baseClass="iconButton" iconClass="questionIcon">
						<div dojoType="dijit.Menu" style="display: none;">
<!-- needed for dojo 1.5.0                                              <div dojoType="dijit.Menu" > -->
						    <div dojoType="dijit.MenuItem" onClick="doHelp()">Help</div>
						</div>
					    </button>
					</td>
				    </tr>
				</table>
			    </td></tr>
			</table>
		    </td>
		</tr>
	    </table>
	    <table id="hor_rule" width="100%" border="0" cellpadding="0" cellspacing="0" style="padding-top:10px; padding-bottom:20px;">
			<tr>
			<td>
					<table width="100%" border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td bgcolor="#000000"><img src="/images/1px.png" alt="" width="1" height="1" /></td>
					</tr>
				</table>
			</td>
		</tr>
	    </table>
	</span>

<!-- real page content starts here -->
	    <table width="80%" border="0" cellpadding="0" cellspacing="0" style="padding-top:0px; padding-bottom:5px;">
		<tr>
		    <td valign="bottom">
			<div class="text" style="padding-bottom:6px;">

<?php

if (!$isOK) {
    # Put the content into a listingBreadCrumb DIV (not SPAN because it's
    # multiline) to get a better color scheme for links
    print "<H1>Apologies, we encountered an error</H1><P><div id=\"listingBreadCrumb\">$errMsg</div>\n";
}

if ($isOK) {

   if ($ok_msg) {
	print "<P>$ok_msg\n";
	print "<P>Your account name is: $duUserName\n";
        print "<P>You can now:\n";
        print "<div id=\"listingBreadCrumb\"><UL>\n";
        print "<LI>Return back to the DataFabric: <a href=\"$duUserHomeUrl\">$duUserHomeUrl</a></LI>\n";
        print "<LI>Read the documentation on using the DataFabric with <a href=\"$df_non_browser_tools_link\" target=\"_blank\">non-web-browser tools</a></LI>\n";
        print "<LI>Change your password <a href=\"$_SERVER[REQUEST_URI]\">again</a></LI>\n";
        print "</div></UL>\n";
   };
   if ($err_msg) print "<P><font color=\"red\"><STRONG>Error:</STRONG> $err_msg</font>\n";
 

   if ( $_SERVER["REQUEST_METHOD"] <> "POST" || $err_msg ) {
      print "<P>You may change your <span id=\"listingBreadCrumb\"><a href=\"$df_non_browser_tools_link\" target=\"_blank\">non-web-browser tools password</a></span> here.<P>Please enter your current details and the new password (retyping the same password twice):\n<P>\n";
      print '<FORM METHOD="POST" action="'.$_SERVER['REQUEST_URI'].'" name="DataFabricChangePassword"><p>' . "\n";
      print "<TABLE><TBODY>\n";
      print '<TR><TD><span class="text">Username:</span></TD><TD><INPUT TYPE="text" name="DFUsername" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD><span class="text">Current password:</span></TD><TD><INPUT TYPE="password" name="DFCurPassword" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD><span class="text">New password:</span></TD><TD><INPUT TYPE="password" name="DFNewPassword1" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD><span class="text">New password (again):</span></TD><TD><INPUT TYPE="password" name="DFNewPassword2" size="20" value="" maxlength="20" ></TD></TR>' . "\n";
      print '<TR><TD></TD><TD><INPUT NAME="Submit" TYPE="SUBMIT" VALUE="Submit" ><p>' . "\n";
      print "</TBODY></TABLE>\n";
      print '</FORM><p>' . "\n";
   };

}
 
?>
			</div>
		    </td>
		</tr>
	    </table>
    </div>
</div>
</body>
</html>
