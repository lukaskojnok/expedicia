<?php
require_once __DIR__ . "/config/common.php";
auth_logout($db);
header("Location: /login.php");
exit;
