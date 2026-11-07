<?php
// Faux serveur HelloAsso pour les tests (serveur intégré de PHP) : jeton OAuth2 et liste des paiements.
// Données : $DOSSIER/paiements-<type>-<formulaire>.json ; compteurs d'appels : $DOSSIER/appels-*.txt
$dossier = getenv('HELLOASSO_SIMULE');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$compter = fn(string $quoi) => file_put_contents("$dossier/appels-$quoi.txt", '.', FILE_APPEND);
header('Content-Type: application/json');
if ($path === '/oauth2/token' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $compter('jeton');
    if (($_POST['grant_type'] ?? '') !== 'client_credentials' || ($_POST['client_id'] ?? '') !== 'id-test' || ($_POST['client_secret'] ?? '') !== 'secret-test') {
        http_response_code(401);
        echo '{"error":"unauthorized_client"}';
        return;
    }
    echo json_encode(['access_token' => 'jeton-simule', 'token_type' => 'bearer', 'expires_in' => 1800]);
    return;
}
if (preg_match('#^/v5/organizations/les-amis-du-phare/forms/(Membership|Event)/([a-z0-9-]+)/payments$#', $path, $m)) {
    $compter('paiements');
    if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer jeton-simule') {
        http_response_code(401);
        return;
    }
    $file = "$dossier/paiements-{$m[1]}-{$m[2]}.json";
    $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
    if ($data === null) {
        http_response_code(404);
        echo '{"errors":[{"message":"not found"}]}';
        return;
    }
    echo json_encode(['data' => $data, 'pagination' => ['pageSize' => 100, 'totalCount' => count($data), 'pageIndex' => 1, 'totalPages' => 1, 'continuationToken' => 'x']]);
    return;
}
http_response_code(404);
