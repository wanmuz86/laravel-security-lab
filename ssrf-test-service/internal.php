<?php
// Lab 6 Part A3: a local "internal" service that must NOT be reachable through the secure fetcher.
header('Content-Type: application/json');
echo json_encode([
    'service' => 'internal-lab-service',
    'classification' => 'training-only',
    'message' => 'This service should not be reachable through the secure fetcher.',
]);
