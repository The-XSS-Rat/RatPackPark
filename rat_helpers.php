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
        $now = time();
        $event = [
            'type' => $type,
            'message' => $message,
            'points' => $points,
            'ts' => $now,
        ];

        $tenantId = $_SESSION['temporary_tenant_id'] ?? null;
        if ($tenantId !== null) {
            $event['tenantId'] = (int)$tenantId;
        }

        $startedAt = $_SESSION['temporary_tenant_started_at'] ?? null;
        if ($startedAt !== null) {
            $elapsed = max(0, $now - (int)$startedAt);
            $event['elapsed'] = $elapsed;
        }

        $_SESSION['rat_score_events'][] = $event;

        if ($tenantId !== null) {
            if (!isset($_SESSION['rat_speedrun']) || !is_array($_SESSION['rat_speedrun'])) {
                $_SESSION['rat_speedrun'] = [];
            }
            if (!isset($_SESSION['rat_speedrun'][$tenantId]) || !is_array($_SESSION['rat_speedrun'][$tenantId])) {
                $_SESSION['rat_speedrun'][$tenantId] = [
                    'history' => [],
                    'categories' => [],
                ];
            }

            $bucket =& $_SESSION['rat_speedrun'][$tenantId];
            $bucket['history'][] = [
                'type' => $type,
                'message' => $message,
                'points' => $points,
                'ts' => $now,
                'elapsed' => $event['elapsed'] ?? null,
            ];
            if (count($bucket['history']) > 50) {
                $bucket['history'] = array_slice($bucket['history'], -50);
            }

            $normalizedType = strtoupper($type);
            if (!isset($bucket['categories'][$normalizedType]) && isset($event['elapsed'])) {
                $bucket['categories'][$normalizedType] = [
                    'elapsed' => $event['elapsed'],
                    'ts' => $now,
                    'message' => $message,
                ];
            }
        }
    }
}
