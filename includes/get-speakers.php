<?php
function get_speakers(PDO $pdo): array {
    try {
        $stmt = $pdo->query(
            'SELECT * FROM speakers WHERE active = 1 ORDER BY sort_order ASC, id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
