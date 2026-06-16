<?php
/**
 * Seed inicial — Congressis Mini-CRM
 * Popula sponsors e speakers com os dados do lançamento.
 * IMPORTANTE: Delete este arquivo após rodar!
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = get_db();
} catch (Throwable $e) {
    die('Erro ao conectar: ' . $e->getMessage());
}

// ── Adiciona coluna flag se não existir (migração idempotente) ──
$col = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'speakers' AND COLUMN_NAME = 'flag'")->fetchColumn();
if (!$col) {
    $pdo->exec("ALTER TABLE speakers ADD COLUMN `flag` VARCHAR(5) DEFAULT NULL AFTER photo_path");
}

// ── Limpa e reinsere para garantir idempotência ──
$pdo->exec("DELETE FROM sponsors");
$pdo->exec("DELETE FROM speakers");
$pdo->exec("ALTER TABLE sponsors AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE speakers AUTO_INCREMENT = 1");

// ── Sponsors ──
$sponsors = [
    // Apoio Institucional
    ['apoio', 'CRO-RJ',               'assets/logos/logo-cro-rj.png',                                    'https://www.cro-rj.org.br/', 10],
    ['apoio', 'ABO Rio de Janeiro',    'assets/logos/logos-15-06-2026/PHOTO-2026-06-13-10-46-39.jpg.jpeg', null,                         20],
    ['apoio', 'ABO Região dos Lagos',  'assets/logos/logos-15-06-2026/logo-nova-abo.png',                 null,                         30],
    ['apoio', 'Rede Fitos',            'assets/logos/logos-15-06-2026/logo-redesfito-vertical (1) (2).png', null,                       40],
    // Patrocínio
    ['patrocinio', 'Cronotherapy',     'assets/logos/logos-15-06-2026/cronotherapy-photoroom.png',        null,                         10],
    ['patrocinio', 'Upy',              'assets/logos/logos-16-06-2026/logo-upy.png',                      null,                         20],
    // Expositores
    ['expositores', 'Priscila Tissi',       'assets/logos/logos-15-06-2026/Logo Preto PNG.png',           null,                         10],
    ['expositores', 'Cogumelos da Floresta','assets/logos/logos-16-06-2026/cogumelos-da-floresta.png',    null,                         20],
    ['expositores', 'Stephani Campos',      'assets/logos/logos-16-06-2026/stephani-campos.png',          null,                         30],
    // Parceiros
    ['parceiros', 'Suelen',            'assets/logos/logos-16-06-2026/logo-suelen.png',                   null,                         10],
    ['parceiros', 'Aretuza Lattanzi',  'assets/logos/logos-16-06-2026/aretuza.png',                       null,                         20],
    ['parceiros', 'Be Angatu',         'assets/logos/logos-16-06-2026/be-angatu.png',                     null,                         30],
    ['parceiros', 'É Forte Cast',      'assets/logos/logos-16-06-2026/efc-logo.png',                      null,                         50],
    ['parceiros', 'Serra News',        'assets/logos/logos-16-06-2026/serra-news.png',                    'https://www.serranewsrj.com.br', 60],
    ['parceiros', 'Delas por Elas',    'assets/logos/logos-16-06-2026/delas-por-elas.png',                null,                         70],
];

$stmtS = $pdo->prepare(
    'INSERT INTO sponsors (category, name, logo_path, url, sort_order, active) VALUES (?,?,?,?,?,1)'
);
foreach ($sponsors as $s) {
    $stmtS->execute($s);
}

// ── Speakers ──
$speakers = [
    [
        'Flávia Karina',
        'Especialista em Periodontia · Odontologia Integrativa e Biológica · Formação em Ozonioterapia · Gestão Estratégica de Consultórios',
        'Da odontologia convencional à clínica integrativa: como aumentar autoridade, diferenciação e faturamento.',
        'assets/speakers/flavia.jpeg', null, 10,
    ],
    [
        'Jean Baptiste Bonillo-Tomazelli',
        'Professor e terapeuta Floral de Bach pela Escola de Bach (Inglaterra)',
        'TDAH e Saúde Emocional: a contribuição dos Florais de Bach no acompanhamento das pessoas com TDAH e de suas famílias.',
        'assets/speakers/jean.jpeg', 'fr', 20,
    ],
    [
        'Suellen Benvênuti',
        'Mestra em Reiki, Terapeuta Vibracional e especialista em Desenvolvimento Humano',
        'Reiki na Saúde Integrativa: da evidência científica à humanização do cuidado.',
        'assets/speakers/suellen.png', null, 30,
    ],
    [
        'Andréa Pirazzo',
        'Especialista em Harmonização Orofacial · Pós em Saúde Integrativa / Odonto Biológica · Especialista em Biofísica · Terapias Bioenergéticas e Biorressonância',
        'Odontologia e estética sob a ótica da saúde integrativa.',
        'assets/speakers/andrea.jpeg', null, 40,
    ],
    [
        'Gabriela e Fernanda Borba',
        'Fisioterapeuta Dermatofuncional · Esteticista e Empresária',
        'Ozonioterapia na Estética Moderna: longevidade e saúde.',
        'assets/speakers/gabriela-fernanda.png', null, 50,
    ],
    [
        'Danielle Murta',
        'Cirurgiã-dentista. Ortodontia, Odontologia Hospitalar e Laserterapia',
        'Laserterapia na Odontologia: modulando inflamação, dor e regeneração para uma saúde além da cavidade oral.',
        'assets/speakers/danielle.jpeg', null, 60,
    ],
];

$stmtSp = $pdo->prepare(
    'INSERT INTO speakers (name, subtitle, quote, photo_path, flag, sort_order, active) VALUES (?,?,?,?,?,?,1)'
);
foreach ($speakers as $sp) {
    $stmtSp->execute($sp);
}

echo '<h2 style="font-family:sans-serif;color:green;padding:2rem">✅ Seed concluído!</h2>';
echo '<p style="font-family:sans-serif;padding:0 2rem"><strong>' . count($sponsors) . ' sponsors</strong> e <strong>' . count($speakers) . ' speakers</strong> inseridos.</p>';
echo '<p style="font-family:sans-serif;padding:.5rem 2rem;color:red"><strong>⚠ Delete o arquivo seed.php do servidor agora!</strong></p>';
echo '<p style="font-family:sans-serif;padding:0 2rem"><a href="/" style="color:#13251B">← Ver o site</a></p>';

// Auto-delete
@unlink(__FILE__);
