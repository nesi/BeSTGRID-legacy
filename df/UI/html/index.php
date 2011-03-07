<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>BeSTGRID DataFabric</title>

<?
$do_redirect = ! isset($_REQUEST["noredirect"]);
$srv_name=$_SERVER["SERVER_NAME"];
$redirect_timeout=3;
?>

<? if ($do_redirect): ?>
<SCRIPT language="javascript">
function newwnd() {window.location="/cgi-bin/geoip.cgi"; }
tm1=setTimeout("newwnd()",<?=$redirect_timeout?>* 1000);
</SCRIPT>
<? endif; ?>

<style>
body {
	background: url(/images/bg_body.gif) repeat-x top left;
	font-family:Trebuchet MS, Helvetica, sans-serif;
	}
	
#logo {margin:3px 0 35px 1px;}
	
a {
	text-decoration: none;
	color:#069;
	background: none;
}

a:visited {color:#6699CC }
a:hover {text-decoration: underline;}

h1, h2 {
	color:#069; 
	background: none;
	font-weight: normal;
	margin: 0;
	padding-top: .5em;
	padding-bottom: .17em;
	border-bottom:1px solid #069;
	font-size:1.2em;
}

p, ul {
	font-size:.8em;
	color:#333;
	line-height:160%;
	}
	
table#datafabric_header {width:100%; height:80px; margin-bottom:40px;}

#userPanel {color:#fff; font-size:.85em; font-weight:normal;}

#userPanel a {color:#FF6;}

/* etc */

h1 b {display:none;}
</style>

</head>
<body>
<table width="100%">
<tbody><tr><th align="LEFT"><img id="logo" src="/images/lg_BeSTGRID-DataFabric.gif" alt="BeSTGRID logo"></th></tr></tbody>
</table>
<h1>BeSTGRID DataFabric</h1>
<? if (! $do_redirect) : ?>
<strong><p>You have been successfully redirected to your nearest DataFabric server: <?=$srv_name?></p></strong>
<? endif; ?>
<ul>
<li>Log in using your institution's Identity Provider: <a href="http://<?=$srv_name?>/BeSTGRID/home/">http://<?=$srv_name?>/BeSTGRID/home/</a>
</li><li>Log in using credentials uploaded to MyProxy: <a href="https://<?=$srv_name?>/BeSTGRID/home/">https://<?=$srv_name?>/BeSTGRID/home/</a>
</li></ul>
<h2>User instructions</h2>
<p>For more information on accessing this service, please see the <strong><a href="http://technical.bestgrid.org/index.php/Using_the_DataFabric">Using the DataFabric</a></strong> manual.</p>

<?php if ($do_redirect): ?>
<h2>Redirecting to your nearest DataFabric entry point</h2>
<p>You will be redirected to your nearest DataFabric entry point in <?=$redirect_timeout?> seconds...</p>
<p><strong><a href="/cgi-bin/geoip.cgi">Click here to redirect immediately</a></strong></p>
<?php endif; ?>

</body>
</html>
