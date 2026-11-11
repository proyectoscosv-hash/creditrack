<?php
require __DIR__ . '/../../../system/Test/bootstrap.php';

$db = \Config\Database::connect('tests');

// Test idempotent seeding on Pais
$countries = [
    ['Id_pais' => 1, 'Pais' => 'El Salvador'],
    ['Id_pais' => 2, 'Pais' => 'Guatemala'],
    ['Id_pais' => 3, 'Pais' => 'Honduras'],
];

foreach ($countries as $c) {
    $found = $db->table('Pais')->where('Id_pais', $c['Id_pais'])->countAllResults();
    if ($found === 0) {
        $db->table('Pais')->insert($c);
    } else {
        $db->table('Pais')->where('Id_pais', $c['Id_pais'])->update(['Pais' => $c['Pais']]);
    }
}

echo "Seeded countries count: " . $db->table('Pais')->countAllResults() . "\n";

// Run it again to test idempotency
foreach ($countries as $c) {
    $found = $db->table('Pais')->where('Id_pais', $c['Id_pais'])->countAllResults();
    if ($found === 0) {
        $db->table('Pais')->insert($c);
    } else {
        $db->table('Pais')->where('Id_pais', $c['Id_pais'])->update(['Pais' => $c['Pais']]);
    }
}
echo "Seeded countries count after rerun: " . $db->table('Pais')->countAllResults() . "\n";
