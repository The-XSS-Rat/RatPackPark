<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    return;
}
if (empty($_SESSION['rat_score_events']) || !is_array($_SESSION['rat_score_events'])) {
    return;
}
$events = array_values($_SESSION['rat_score_events']);
unset($_SESSION['rat_score_events']);
echo '<script>window.__ratQueueScoreEvents(' . json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ');</script>';
