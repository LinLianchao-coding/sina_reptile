<?php

date_default_timezone_set("Asia/Shanghai");
header("Content-Type: text/html; charset=utf-8");
set_time_limit(300);
require 'sql.php';
require 'function.php';
$sql = new sql();
$sql->insert('page_record', array('user_id' => 1, 'page' => 100));

