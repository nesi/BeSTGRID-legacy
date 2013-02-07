<?php

$davis_properties_file = '/opt/davis/davis/webapps/root/WEB-INF/davis-host.properties';

$df_non_browser_tools_tag = 'Mounting_DataFabric_as_a_disk_drive';

$log_file = '/var/www/html/dfpassword/.htirods/.htlog/passwordchange.log';
$iCommandsPath = "/opt/iRODS/iRODS/clients/icommands/bin";
$irodsEnvHome = "/var/www/html/dfpassword/.htirods";
$iquest = "HOME=$irodsEnvHome $iCommandsPath/iquest";
$iadmin = "HOME=$irodsEnvHome $iCommandsPath/iadmin";
$min_password_length = 8;

$verbose = 0;

?>
