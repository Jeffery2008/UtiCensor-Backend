<?php
echo "Hello World!\n";
echo "Current SAPI: " . php_sapi_name() . "\n";
echo "Is CLI: " . (php_sapi_name() === 'cli' ? 'Yes' : 'No') . "\n";
echo "Test completed.\n"; 
 