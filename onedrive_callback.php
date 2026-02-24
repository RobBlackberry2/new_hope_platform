<?php
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$qs = http_build_query([
  'action' => 'onedrive_callback',
  'code' => $code,
  'state' => $state
]);
header("Location: router.php?$qs");
exit;