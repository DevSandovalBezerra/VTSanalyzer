<?php
require __DIR__.'/../src/bootstrap.php';
try { db()->query('SELECT 1'); queue()->ping(); echo "healthy\n"; } catch(Throwable) { exit(1); }
