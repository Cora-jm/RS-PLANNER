<?php
require 'core/Autoloader.php';
$class = 'App\Core\Database';
echo "Testing $class...\n";
if (class_exists($class)) {
    echo "SUCCESS: Class loaded!\n";
} else {
    echo "FAILURE: Class NOT loaded.\n";
}
