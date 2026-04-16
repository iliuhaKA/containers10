<?php

$config['db']['host'] = getenv('MYSQL_HOST');
$config['db']['database'] = getenv('MYSQL_DATABASE');
// $config['db']['username'] = getenv('MYSQL_USER');
// $config['db']['password'] = getenv('MYSQL_PASSWORD');
$config['db']['username'] = file_get_contents('/run/secrets/user');
$config['db']['password'] = file_get_contents('/run/secrets/secret');
