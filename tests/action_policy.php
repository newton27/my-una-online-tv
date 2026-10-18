<?php
require_once __DIR__ . '/../classes/NwOnlineTvActionPolicy.php';
if (!NwOnlineTvActionPolicy::allowed('view_channels', false, false)) throw new RuntimeException('Public view denied');
if (NwOnlineTvActionPolicy::allowed('sync_channels', false, true)) throw new RuntimeException('Non-admin sync allowed');
if (NwOnlineTvActionPolicy::allowed('edit_channels', true, false)) throw new RuntimeException('Anonymous edit allowed');
if (!NwOnlineTvActionPolicy::allowed('sync_channels', true, true)) throw new RuntimeException('Admin sync denied');
if (NwOnlineTvActionPolicy::allowed('unexpected_action', true, true)) throw new RuntimeException('Unknown action allowed');
echo "PASS authorization policy\n";
