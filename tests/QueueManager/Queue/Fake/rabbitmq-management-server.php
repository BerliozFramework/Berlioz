<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

// Router script of a fake RabbitMQ management API, for the PHP built-in web server.

header('Content-Type: application/json');

if (($_SERVER['HTTP_AUTHORIZATION'] ?? null) !== 'Basic ' . base64_encode('monitoring:secret')) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authorized']);
    return;
}

switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/api/queues/%2F':
        echo json_encode([['name' => 'default', 'messages' => 3]]);
        return;
    case '/prefix/api/queues/%2F':
        echo json_encode([['name' => 'prefixed', 'messages' => 1]]);
        return;
    case '/redirect':
        http_response_code(302);
        header('Location: /leak');
        echo json_encode(['redirect' => true]);
        return;
    case '/leak':
        echo json_encode(['leaked' => true]);
        return;
    case '/error':
        http_response_code(500);
        echo json_encode(['error' => 'internal']);
        return;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
}
