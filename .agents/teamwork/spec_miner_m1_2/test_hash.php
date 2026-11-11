<?php
// Test seed user generation and bcrypt hashing

$pass = 'Password123!';
$hash = password_hash($pass, PASSWORD_BCRYPT);
echo "Password: $pass\n";
echo "Bcrypt Hash: $hash\n";
echo "Verify result: " . (password_verify($pass, $hash) ? 'TRUE' : 'FALSE') . "\n";
echo "Verify wrong password: " . (password_verify('WrongPass', $hash) ? 'TRUE' : 'FALSE') . "\n";

// Generate fixed bcrypt hash for seeders or dynamically generate in seeder
// Using a fixed precomputed hash or generating in seeder?
// Let's check both:
// Precomputed bcrypt hash for 'Password123!':
// E.g. $2y$10$w8TKnP55p2mRz9bJkUu5le3N/6Oq4d1k6E6kF9sZ4W8a9J1p1l9eq (sample)
