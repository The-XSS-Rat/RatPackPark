<?php
if (!function_exists('rat_track_add_score_event')) {
    function rat_track_add_score_event(string $type, string $message, int $points = 1): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['rat_score_events']) || !is_array($_SESSION['rat_score_events'])) {
            $_SESSION['rat_score_events'] = [];
        }
        $_SESSION['rat_score_events'][] = [
            'type' => $type,
            'message' => $message,
            'points' => $points,
            'ts' => time(),
        ];
    }
}
