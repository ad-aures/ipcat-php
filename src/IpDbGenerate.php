#!/usr/bin/php
<?php

/**
* PHP var_export() with short array syntax (square brackets) indented 2 spaces.
*
* NOTE: The only issue is when a string value has `=>\n[`, it will get converted to `=> [`
* @link https://www.php.net/manual/en/function.var-export.php
*/
function varexport($expression, $return=FALSE) {
    $export = var_export($expression, TRUE);
    $patterns = [
        "/array \(/" => '[',
        "/^([ ]*)\)(,?)$/m" => '$1]$2',
        "/=>[ ]?\n[ ]+\[/" => '=> [',
        "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
    ];
    $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
    if ((bool)$return) return $export; else echo $export;
}


/*
 * Now load in external datacenter list
 */
$rowstr = file_get_contents('https://raw.github.com/client9/ipcat/master/datacenters.csv');
$rows = explode("\n", $rowstr);
foreach ($rows as $row) {
    if (strlen($row) == 0 || $row[0] == '#') {
        continue;
    }
    $parts = explode(',', $row);

    $newrow= array(
        '_ip0' => ip2long($parts[0]),
        '_ip1' => ip2long($parts[1]),
        'owner' => sprintf("%s", $parts[3]),
    );
    $keys[$newrow['_ip0']] = $newrow;
}

ksort($keys);

$ary = array();

$last0 = 0;
$last1 = 0;
foreach ($keys as $k => $v) {
    $i0 = $v['_ip0'];
    $i1 = $v['_ip1'];

    // safety checks to make sure data is sorted correctly
    if ($i0 == 0 || $i1 == 0 || $i0 > $i1 || $last1 >= $i0) {
        print_r($v);
        die;
    }
    $last0 = $i0;
    $last1 = $i1;
    $ary[] = $v;
}

// autogenerate database
print <<<EOT
<?php
namespace AdAures\Ipcat;

/* Autogenerated.  Do not edit */
class IpDb {
    public static function find(\$ipstr) {
        \$ip = ip2long(\$ipstr);
        \$haystack = self::\$db;
        \$high = count(\$haystack) - 1;
        \$low = 0;
        while (\$high >= \$low) {
            \$probe = floor((\$high + \$low) / 2);
            \$row = \$haystack[\$probe];
            if (\$row['_ip0'] > \$ip) {
                \$high = \$probe - 1;
            } else if (\$row['_ip1'] < \$ip) {
                \$low = \$probe + 1;
            } else {
                return \$row;
            }
        }
        return null;
    }

EOT;
print "    static public \$db = ";
print varexport($ary, TRUE);
print ";\n";
print "}\n";
