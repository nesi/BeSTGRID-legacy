<?php

$log_file = '/var/www/html/dfpassword/.htirods/.htlog/passwordchange.log';
$iCommandsPath = "/opt/iRODS/iRODS/clients/icommands/bin";
$irodsEnvHome = "/var/www/html/dfpassword/.htirods";
$iquest = "HOME=$irodsEnvHome $iCommandsPath/iquest";
$iadmin = "HOME=$irodsEnvHome $iCommandsPath/iadmin";
$min_password_length = 8;

?>
