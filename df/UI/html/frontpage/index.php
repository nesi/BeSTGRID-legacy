<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<?
include 'config.php';

$remote_addr = $_SERVER["REMOTE_ADDR"];
$srv_name=`$geoip_path --client $remote_addr --servers $df_servers`;
$srv_name=trim($srv_name);


# read properties
include 'read-properties.php';
$davis_properties = parse_properties(file_get_contents($davis_properties_file));

$df_title = $davis_properties['authentication-realm']; # note: property is "authentication-realm" but Davis substitution in ui.html is "authenticationrealm"
$irodsZone = $davis_properties['zone-name'];
$df_path = "$irodsZone/home/";
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
<p><em>Your nearest DataFabric server appears to be:</em> <strong><?=$srv_name?></strong></p>
<ul>
<li>Log in using your institution's Identity Provider: <a href="http://<?=$srv_name?><?=$df_path?>">http://<?=$srv_name?><?=$df_path?></a>
</li><li>Log in using credentials uploaded to MyProxy: <a href="https://<?=$srv_name?><?=$df_path?>">https://<?=$srv_name?><?=$df_path?></a>
</li></ul>
<h2>User instructions</h2>
<p>For more information on accessing this service, please see the <strong><a href="http://technical.bestgrid.org/index.php/Using_the_DataFabric">Using the DataFabric</a></strong> manual.</p>

</body>
</html>
