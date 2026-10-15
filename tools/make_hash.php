<?php
if ($argc < 2) {
    echo "Ishlatish: php make_hash.php <parol>\n";
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT) . "\n";
