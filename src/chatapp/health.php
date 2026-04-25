<?php
declare(strict_types=1);

// Cloud Run startup/liveness probe. Does not touch the database so a
// transient DB outage does not mark the instance unhealthy and loop-restart.
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['status' => 'ok']);
