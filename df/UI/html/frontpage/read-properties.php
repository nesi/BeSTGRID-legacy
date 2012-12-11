<?php

# function to parse properties.
# credits: https://gist.github.com/977771

function parse_properties($txtProperties) {
	$result = array();
	$lines = split("\n", $txtProperties);
	$key = "";
	$isWaitingOtherLine = false;
	foreach ($lines as $i => $line) {
		if (empty($line) || (!$isWaitingOtherLine && strpos($line, "#") === 0))
			continue;
			
		if (!$isWaitingOtherLine) {
			$key = substr($line, 0, strpos($line, '='));
			$value = substr($line, strpos($line, '=')+1, strlen($line));        
		}
		else {
			$value .= $line;    
		}    

		/* Check if ends with single '\' */
                // OK, we'd get wrong if the line was ending with \\, but that's acceptable
		if (strrpos($value, "\\") === strlen($value)-strlen("\\")) {
			$value = substr($value,0,strlen($value)-1); # ."\n";
			$isWaitingOtherLine = true;
		}
		else {
			$isWaitingOtherLine = false;
		}
		
                # do basic decoding - at least \n
		$value = str_replace("\\n", "\n", $value);

		$result[$key] = $value;
		unset($lines[$i]);        
	}
	
	return $result;
}


# DEBUG: read and dump properties
/*
$txt_props = file_get_contents("/opt/davis/davis/webapps/root/WEB-INF/davis-host.properties");

$props = parse_properties($txt_props);
print "<PRE>\n";
print $props . "\n";
foreach ($props as $key => $value) {
   print "'$key' => '$value'\n";
};
print "</PRE>\n";
*/



?>
