<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
require_once ABSPATH . WPINC . '/pomo/po.php';
require_once ABSPATH . WPINC . '/pomo/mo.php';

$po_file = __DIR__ . '/languages/osq-stress-check-ja.po';
$mo_file = __DIR__ . '/languages/osq-stress-check-ja.mo';

$po = new POMO_PO();
$po->import_from_file($po_file);

$mo = new MO();
$mo->entries = $po->entries;
$mo->set_headers($po->headers);
$mo->export_to_file($mo_file);

echo "Compiled $mo_file successfully.";
