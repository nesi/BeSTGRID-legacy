<!-- courtesy http://www.anyexample.com/programming/php/php_mysql_example__display_table_as_html.xml -->
<html><head><title>MySQL Table Viewer</title></head><body>
<?php

include '../dfusers/config.php';
//$db_server_port = 'localhost';
//$db_user = 'root';
//$db_password = 'lptm42b';
//$db_name = 'sphinx';
//$db_table = 'spheres';

if (!mysql_connect($db_server_port, $db_user, $db_password))
    die("Can't connect to database");

if (!mysql_select_db($db_name))
    die("Can't select database");

// sending query
$result = mysql_query("SELECT * FROM {$db_table}");
if (!$result) {
    die("Query to show fields from table failed");
}

$fields_num = mysql_num_fields($result);

echo "<h1>Table: {$db_table}</h1>";
echo "<table border='1'><tr>";
// printing table headers
for($i=0; $i<$fields_num; $i++)
{
    $field = mysql_fetch_field($result);
    echo "<td>{$field->name}</td>";
}
echo "</tr>\n";
// printing table rows
while($row = mysql_fetch_row($result))
{
    echo "<tr>";

    // $row is array... foreach( .. ) puts every element
    // of $row to $cell variable
    foreach($row as $cell)
        echo "<td>$cell</td>";

    echo "</tr>\n";
}
mysql_free_result($result);
?>
</body></html>

