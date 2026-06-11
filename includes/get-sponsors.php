<?php
// Retorna todos os sponsors ativos agrupados por categoria
function get_sponsors_by_category(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT id, category, name, logo_path, url
        FROM sponsors
        WHERE active = 1
        ORDER BY category, sort_order ASC, id ASC
    ");
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $grouped[$s['category']][] = $s;
    }
    return $grouped;
}
