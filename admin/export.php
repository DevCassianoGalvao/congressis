<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();

$pdo = get_db();

// Mesmos filtros da listagem
$search = trim($_GET['q']      ?? '');
$status = trim($_GET['status'] ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(nome LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (in_array($status, ['lead', 'comprou'], true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT id, nome, email, telefone, cpf, cep, cnpj,
            utm_source, utm_medium, utm_campaign, utm_content, utm_term, status, created_at
     FROM leads $where_sql
     ORDER BY created_at DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Enviar CSV
$filename = 'congressis_leads_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

// BOM UTF-8 para compatibilidade com Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Nome', 'E-mail', 'Telefone', 'CPF', 'CEP', 'CNPJ',
               'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Content', 'UTM Term', 'Status', 'Data/Hora']);

function fmt_cpf(?string $v): string {
    if (!$v || strlen($v) !== 11) return $v ?? '';
    return substr($v,0,3).'.'.substr($v,3,3).'.'.substr($v,6,3).'-'.substr($v,9,2);
}
function fmt_cep(?string $v): string {
    if (!$v || strlen($v) !== 8) return $v ?? '';
    return substr($v,0,5).'-'.substr($v,5);
}
function fmt_cnpj(?string $v): string {
    if (!$v || strlen($v) !== 14) return $v ?? '';
    return substr($v,0,2).'.'.substr($v,2,3).'.'.substr($v,5,3).'/'.substr($v,8,4).'-'.substr($v,12);
}

foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['nome'],
        $row['email'],
        $row['telefone'],
        fmt_cpf($row['cpf']   ?? null),
        fmt_cep($row['cep']   ?? null),
        fmt_cnpj($row['cnpj'] ?? null),
        $row['utm_source'],
        $row['utm_medium'],
        $row['utm_campaign'],
        $row['utm_content'],
        $row['utm_term'],
        $row['status'],
        $row['created_at'],
    ]);
}
fclose($out);
exit;
