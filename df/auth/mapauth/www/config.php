<?php

$mapfile = "/opt/mapauth/mapfile/grid-mapfile";
$tempfile = "/opt/mapauth/mapfile/grid-mapfile.new";
$lockfile = "/opt/mapauth/mapfile/grid-mapfile.lock";
$external_auth = "/opt/pwauth/bin/pwauth";
$site_name = "University of Example HPC system";
$service_name = "$site_name DTN authentication and mapping tool";

$user_DN_prefix = "/DC=nz/DC=org/DC=nesi/DC=myproxyplus";
$user_DN_req_attrs = array( "cn", "o", "auEduPersonSharedToken");
$user_DN_pattern = '$user_DN_prefix/O=$_SERVER[o]/CN=$_SERVER[cn] $_SERVER[auEduPersonSharedToken]';

?>
