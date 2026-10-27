<?php
echo "exec désactivé ? ";
var_dump(in_array('exec', explode(',', ini_get('disable_functions'))));

echo "<br>Utilisateur : " . exec('whoami') . "<br>";

$output = [];
$code = 0;
exec('which mysqldump 2>&1', $output, $code);
echo "which mysqldump → code $code : " . implode(' ', $output) . "<br>";

$output2 = [];
$code2 = 0;
exec('/usr/bin/mysqldump --version 2>&1', $output2, $code2);
echo "mysqldump --version → code $code2 : " . implode(' ', $output2);