<?php
if (!function_exists('inject_scripts')) {
    function inject_scripts(PDO $pdo, string $location): void {
        if (!in_array($location, ['head', 'body_start', 'footer'], true)) return;
        try {
            $stmt = $pdo->prepare('SELECT script_content FROM site_scripts WHERE location = ? LIMIT 1');
            $stmt->execute([$location]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty(trim($row['script_content']))) {
                echo $row['script_content'] . "\n";
            }
        } catch (Throwable $e) {
            error_log('inject_scripts error: ' . $e->getMessage());
        }
    }
}
