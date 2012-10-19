<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<?
include 'config.php';

$remote_addr = $_SERVER["REMOTE_ADDR"];
$srv_name=`$geoip_path --client $remote_addr --servers $df_servers`;
$srv_name=trim($srv_name);
?>

<title><?=$df_title?></title>
<link href="bg_style.css" rel="stylesheet" type="text/css" />

</head>
<body>
<table width="100%">
<tbody><tr><th align="LEFT"><img id="logo" src="/images/lg_BeSTGRID-DataFabric.gif" alt="BeSTGRID logo"></th></tr></tbody>
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
