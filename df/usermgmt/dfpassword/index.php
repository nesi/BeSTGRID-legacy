<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<?php

# CONFIG
include 'config.php';

$st_name = 'auEduPersonSharedToken';
$cn_name = 'cn';
$idp_name = 'Shib-Identity-Provider';

$dfUrlBase = "http://$_SERVER[SERVER_NAME]";
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

<title><?=$df_title?> - Setting your non-web-browser tools password</title>

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


if (!isset($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"]!="on")) {
    $isOK = false;
    $https_URL = "https://$_SERVER[SERVER_NAME]$_SERVER[REQUEST_URI]";
    $errMsg = "This URL MUST be accessed over HTTPS.<p>\nPlease go to <a href=\"$https_URL\">$https_URL</a> instead.";
} elseif (!isset($_SERVER[$st_name])) {
    $isOK = false;
    $errMsg = "Unfortunately, we have not received the shared token attribute as a part of your login.\nPlease report this issue to your own institution's IT Service Desk";
} else {

    # get the data from the current session
    $user_data = array();
    $duSharedToken = $_SERVER[$st_name];
    $user_data["duSharedToken"] = $_SERVER[$st_name];
    if (isset($_SERVER[$cn_name])) { $user_data["duCN"]=$_SERVER[$cn_name]; };
    if (isset($_SERVER[$idp_name])) { $user_data["duIdP"]=$_SERVER[$idp_name]; };

    $retval = 0;
    $output = array();
    $lastline = exec("$iquest \"%s\" \"select USER_NAME where  USER_INFO like '<ST>" . escapeshellcmd($duSharedToken) . "</ST>'\"", $output, $retval);

    if ( $retval != 0 || count($output) != 1 || strstr($output[0],":") || ! ($duUserName = $output[0]) ) { 
        # Either an error invoking iquest or no record found.
	# When no record is found, iquest (as of 3.3.1) does not indicate an
	# error exit code (returns 0) and produces one line of output (so same
        # as when displaying results); the line is exactly:
        # CAT_NO_ROWS_FOUND: Nothing was found matching your query
	# So we tell the difference by checking for a colon (":") which should
        # not be present in a username.
        $isOK = false;
        $errMsg = "Unfortunately, we could not find your DataFabric account (based on your SharedToken value of " . htmlentities("$duSharedToken") . ").<p>\nPlease register first as a DataFabric user by going to <a href=\"$dfUrl\">$dfUrl</a> and logging in with your institutional login";
    };
};

if ($isOK && $duUserName) {
   $duUserHome = "/$irodsZone/home/$duUserName";
   // for Shibboleth login, redirect users to plain http interface
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
<?php if ($isOK) { ?>
				    <tr class="text">
					<td colspan="100" align="right" style="padding-bottom:4px;">You are logged in as &lt;<a title="Go to your home folder" class="link" href="<?= $duUserHomeUrl ?> "><?= $duUserName ?></a>&gt;</td>
				    </tr>
<?php } ?>
				    <tr>
					<td id="sessionButtonsHTML" style="width:auto;" align="right" valign="top"></td>
<?php if ($isOK) { ?>
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

if (!$isOK || !$duUserName ) {
    # Put the content into a listingBreadCrumb DIV (not SPAN because it's
    # multiline) to get a better color scheme for links
    print "<H1>Apologies, we encountered an error</H1><P><div id=\"listingBreadCrumb\">$errMsg</div>\n";
}

if ($isOK && $duUserName) {

    if ($verbose>=1) {
	print "<H1>Welcome $user_data[duCN]</H1>\n<P>Your sharedToken is $duSharedToken\n";
	print "<P>Your DataFabric account name is $duUserName\n";
    }
    $err_msg = "";
    $ok_msg = "";
 
    if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
       if ($_POST["DFPassword1"] && $_POST["DFPassword2"] && ($_POST["DFPassword1"] == $_POST["DFPassword2"]) && (strlen($_POST["DFPassword1"]) >= $min_password_length) ) {
	   // Log to $log_file
	   $log_r = fopen($log_file, "a");
           $log_ok = true;
	   if ($log_r) { 
	       $write_res = fwrite($log_r, strftime("%c") . " Setting password for: username=$duUserName SharedToken=$duSharedToken\n"); 
	       $close_res = fclose($log_r);
	       if (!$write_res || !$close_res) { $log_ok = false; };
	   } else { $log_ok = false; };
	       if (! $log_ok) {
		   $err_msg = "Unfortunately, there has been an internal error: unable to log.";
	       } else {
	       $new_password = $_POST["DFPassword1"];
	       $output = array();
	       $retval = 0;
	       exec("$iadmin moduser $duUserName password " . escapeshellarg($new_password), $output, $retval);
	       if ($retval == 0 ) { 
		   $ok_msg = "Your password has been succesfully set.";
	       } else {
		   $err_msg = "Unfortunately, there was an error setting your password (error code $retval).  Your password was not changed.  You may try again";
	       };
	   };
       } else {
           if (!$_POST["DFPassword1"] || !$_POST["DFPassword2"]) { 
               $err_msg = "You must enter the same password into both fields";
           } elseif ($_POST["DFPassword1"] != $_POST["DFPassword2"]) {
               $err_msg = "The passwords did not match";
           } elseif (strlen($_POST["DFPassword1"]) < $min_password_length) {
               $err_msg = "The password was too short (must be at least $min_password_length characters long)";
           } else {
               $err_msg = "There was an error receiving your password";
           };
       };
    };

    if ($ok_msg) {
	print "<P><STRONG>$ok_msg</STRONG>\n";
	print "<P>Your account name is: $duUserName\n";
        print "<P>You can now:\n";
        print "<div id=\"listingBreadCrumb\"><UL>\n";
        print "<LI>Return back to the DataFabric: <a href=\"$duUserHomeUrl\">$duUserHomeUrl</a></LI>\n";
        print "<LI>Read the documentation on using the DataFabric with <a href=\"$df_non_browser_tools_link\" target=\"_blank\">non-web-browser tools</a></LI>\n";
        print "<LI>Set your password <a href=\"$_SERVER[REQUEST_URI]\">again</a></LI>\n";
        print "</div></UL>\n";
    };

    if ($err_msg) print "<P><font color=\"red\"><STRONG>Error:</STRONG> $err_msg</font>\n";

    if ( $_SERVER["REQUEST_METHOD"] <> "POST" || $err_msg ) {
        print "<P>You may set a <span id=\"listingBreadCrumb\"><a href=\"$df_non_browser_tools_link\" target=\"_blank\">non-web-browser tools password</a></span> for your account here:\n";
        print '<FORM METHOD="POST" action="'.$_SERVER['REQUEST_URI'].'" name="DataFabricSetPassword"><p>' . "\n";
        print "<TABLE><TBODY>\n";
        print "<TR><TD><span class=\"text\">Account name:</span></TD><TD><span class=\"text\">$duUserName</span></TD></TR>\n";
        print "<TR><TD><span class=\"text\">Password:</span></TD><TD><INPUT TYPE=\"password\" name=\"DFPassword1\" size=\"20\" value=\"\" maxlength=\"20\" ></TD></TR>\n";
        print "<TR><TD><span class=\"text\">Password (retype again):</span></TD><TD><INPUT TYPE=\"password\" name=\"DFPassword2\" size=\"20\" value=\"\" maxlength=\"20\" ></TD></TR>\n";
        print '<TR><TD></TD><TD><INPUT NAME="Submit" TYPE="SUBMIT" VALUE="Submit" ></TD></TR>' . "\n";
        print "</TBODY></TABLE>\n";
        print '</FORM><p>' . "\n";
    }

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
