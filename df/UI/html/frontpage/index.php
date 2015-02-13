<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<?php
include 'config.php';

# use our own server name as the fallback
$srv_name = $_SERVER["SERVER_NAME"];

# read properties
include 'read-properties.php';
$davis_properties = parse_properties(file_get_contents($davis_properties_file));

$df_title = $davis_properties['authentication-realm']; # note: property is "authentication-realm" but Davis substitution in ui.html is "authenticationrealm"
$irodsZone = $davis_properties['zone-name'];
$df_path = "/$irodsZone/home/";
$helpURL = $davis_properties['helpURL'];
$logo_width = $logo_height = "";

if ( $davis_properties['organisation-logo-geometry'] && strpos($davis_properties['organisation-logo-geometry'],"x")>0) {
    # if logo geometry is specified as nnnxmmm
    $logo_geom_arr = explode("x", $davis_properties['organisation-logo-geometry'], 2);
    $logo_width = $logo_geom_arr[0];
    $logo_height = $logo_geom_arr[1];
};


?>

<title><?=$df_title?></title>
<link href="bg_style.css" rel="stylesheet" type="text/css" />

</head>
<body>

<?= $davis_properties['ui-include-body-header'] ?>

<table width="100%">
<tbody><tr><td align="LEFT"><img id="logo" src="<?= $davis_properties['organisation-logo'] ?>" alt="BeSTGRID logo"></td></tr></tbody>
</table>


                    <table id="hor_rule" width="100%" border="0" cellpadding="0" cellspacing="0" style="padding-top:10px; padding-bottom:20px;">
                                <tr>
                                <td>
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                <tr>
                                                        <td bgcolor="#DDDDDD"><img src="/images/1px.png" alt="" width="1" height="1" /></td>
                                                </tr>
                                        </table>
                                </td>
                        </tr>
                    </table>

<h1><?=$df_title?></h1>

<?php if (isset($df_announce) && ($df_announce != "") ) { ?>
<!-- BEGIN: announcement -->
<table border="1px" cellpadding="5" cellspacing="0" style="border-color: LightGrey; width: 85%; border-collapse: collapse;">
<tr style="background: #FFFF99;" align="left">
<td>
<?= $df_announce ?>
</td>
</tr>
</table>
<!-- END: announcement -->
<?php } else { ?>
<!-- NO announcement -->
<?php } ?>

<div id="df_server_search_progress">
<p><em>Determining your nearest DataFabric server ... While we are working, you can also log in to this server:</em> <strong><?=$srv_name?></strong></p>
</div>
<div id="df_server_search_result">
<ul>
<li>Log in using your institution's Identity Provider: <a id="df_server_link_http" href="http://<?=$srv_name?><?=$df_path?>">http://<?=$srv_name?><?=$df_path?></a>
</li><li>Log in using your DataFabric username and password: <a id="df_server_link_https" href="https://<?=$srv_name?><?=$df_path?>">https://<?=$srv_name?><?=$df_path?></a>
</li>
<p>
<li>Access the DataFabric with the iDrop client: <strong><a href="http://iren-web.renci.org/idrop-release/idrop.jnlp">Start iDrop java client</a></strong> (Help: see <strong><a href="http://technical.bestgrid.org/index.php/Using_the_DataFabric#Accessing_the_DataFabric_with_iDrop">iDrop instructions</a></strong>)</li>
</ul>
</div>

<h2>User instructions</h2>
<p>For more information on accessing this service, please see the <strong><a href="http://technical.bestgrid.org/index.php/Using_the_DataFabric">Using the DataFabric</a></strong> manual.</p>

<script language="javascript">

var servers = [ 
<?php
    foreach ( preg_split("/:/",$df_servers) as $server) {
      print "\t\"$server\",\n";
    };
?>
              ]

var rodsZone = "<?= $irodsZone ?>";
var df_path = "<?= $df_path ?>";

var bestServer = null;
var bestTime = null;

var debug=<?= isset($_REQUEST["debug"]) ? "true" : "false" ?>;

function measureTime(serverHost) {
  if (typeof XMLHttpRequest != "undefined") {  
    var timer_client = new XMLHttpRequest(); 
    // do a synchronous request
    timer_client.open("GET", "http://"+serverHost+"/favicon.ico",false);
    //timer_client.timeout=5000 // time-out after 5s...
    var prevTime = Date.now();
    timer_client.send(); 
    var newTime = Date.now();
    if (timer_client.status == 200) {
        return (newTime-prevTime);
    }
  };                                           
  return null;
}

for (i=0; i<servers.length; i++) {
    // try each server twice
    for (tryNr=0; tryNr<2; tryNr++) { 
        if (debug) console.log("Trying "+servers[i] + " ... ");
        serverTime = null
        try {
            serverTime = measureTime(servers[i]);
        } catch (err) { console.error("Ooops " + err); }
        if (debug) console.log(" ... time="+serverTime+"ms");
        if (serverTime != null && 
            (bestTime == null || serverTime < bestTime)) {
            bestTime = serverTime;
            bestServer = servers[i];
        };
    };
};
if (bestServer) {
    if (debug) console.log("Best server is "+bestServer+" (time="+bestTime+"ms)");
    document.getElementById("df_server_search_progress").innerHTML = "<p><em>Your nearest DataFabric server appears to be: </em> <strong>"+bestServer+"</strong></p>";
    document.getElementById("df_server_link_http").innerHTML = "http://" + bestServer + df_path
    document.getElementById("df_server_link_http").href = "http://" + bestServer + df_path
    document.getElementById("df_server_link_https").innerHTML = "https://" + bestServer + df_path
    document.getElementById("df_server_link_https").href = "https://" + bestServer + df_path
} else {
    if (debug) console.log("Failed to determine the best server - all attempts failed");
    document.getElementById("df_server_search_progress").innerHTML = "<p><em>Unfortunately, there was a problem contacting all of the DataFabric servers ... you mat still try to log in to this server: </em> <strong>"+"<?=$srv_name?>"+"</strong></p>";
    // leave server links as originally created (with <?=$srv_name?>)
};
</script>

</body>
</html>
