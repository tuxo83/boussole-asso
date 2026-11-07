<?php
// API JSON (/api/…) et serveur MCP (/mcp) : authentification par clé personnelle, mêmes droits que sur le site
declare(strict_types=1);

const MCP_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

return [
    'ordre' => 98,
    'routes' => [
        ['GET', '/api/moi', fn() => api_repondre(fn() => api_moi())],
        ['GET', '/api/espaces', fn() => api_repondre(fn() => api_espaces())],
        ['GET', '/api/espaces/{slug}', fn(array $p) => api_repondre(fn() => api_espace($p['slug']))],
        ['POST', '/api/espaces/{slug}', fn(array $p) => api_repondre(fn() => api_espace_modifier($p['slug'], api_corps()))],
        ['POST', '/api/espaces/{slug}/dates', fn(array $p) => api_repondre(fn() => api_date_cle_ajouter($p['slug'], (string) (api_corps()['date'] ?? ''), (string) (api_corps()['libelle'] ?? '')))],
        ['GET', '/api/pages', fn() => api_repondre(fn() => api_pages())],
        ['GET', '/api/pages/{id}', fn(array $p) => api_repondre(fn() => api_page((int) $p['id']))],
        ['POST', '/api/pages', fn() => api_repondre(fn() => api_page_ecrire(null, api_corps()))],
        ['POST', '/api/pages/{id}', fn(array $p) => api_repondre(fn() => api_page_ecrire((int) $p['id'], api_corps()))],
        ['GET', '/api/membres', fn() => api_repondre(fn() => api_membres())],
        ['POST', '/mcp', fn() => mcp_repondre()],
        // Transport HTTP du MCP sans flux serveur : GET n'est pas proposé
        ['GET', '/mcp', function () {
            http_response_code(405);
            header('Allow: POST');
        }],
    ],
];

function api_corps(): array
{
    static $body = null;
    if ($body === null) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($decoded) ? $decoded : $_POST;
    }
    return $body;
}

function api_repondre(callable $action): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        api_authentifier();
        echo json_encode(['ok' => true, 'donnees' => $action()], JSON_UNESCAPED_UNICODE);
    } catch (ApiErreur $erreur) {
        http_response_code($erreur->statut);
        echo json_encode(['ok' => false, 'erreur' => $erreur->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

// ---------- MCP (JSON-RPC 2.0 sur HTTP, sans état)

function mcp_outils(): array
{
    $texte = fn(string $description) => ['type' => 'string', 'description' => $description];
    $objet = fn(array $props, array $requis = []) => ['type' => 'object', 'properties' => (object) $props, 'required' => $requis];
    return [
        ['name' => 'moi', 'description' => 'Qui suis-je : membre de la clé, ses droits, la portée de la clé.', 'inputSchema' => $objet([])],
        ['name' => 'lister_espaces', 'description' => 'Espaces visibles (comités, commissions, projets, événements), avec météo et alertes.', 'inputSchema' => $objet([])],
        ['name' => 'lire_espace', 'description' => "Détail d'un espace : équipe, dates clés, fichiers.", 'inputSchema' => $objet(['slug' => $texte("Identifiant de l'espace")], ['slug'])],
        ['name' => 'modifier_espace', 'description' => "Changer la météo (soleil, eclaircie, pluie, orage) ou la description d'un espace.",
            'inputSchema' => $objet(['slug' => $texte("Identifiant de l'espace"), 'meteo' => ['type' => 'string', 'enum' => ['soleil', 'eclaircie', 'pluie', 'orage']], 'description' => $texte('Nouvelle description')], ['slug'])],
        ['name' => 'ajouter_date_cle', 'description' => 'Ajouter une date clé à un espace.',
            'inputSchema' => $objet(['slug' => $texte("Identifiant de l'espace"), 'date' => $texte('Date AAAA-MM-JJ'), 'libelle' => $texte('Libellé')], ['slug', 'date', 'libelle'])],
        ['name' => 'lister_pages', 'description' => 'Pages du site (toutes si droit « publications », sinon les publiées).', 'inputSchema' => $objet([])],
        ['name' => 'lire_page', 'description' => "Contenu d'une page (blocs).", 'inputSchema' => $objet(['id' => ['type' => 'integer']], ['id'])],
        ['name' => 'creer_page', 'description' => "Créer une page. Contenu : « texte » (paragraphes séparés par une ligne vide) ou « blocs » (format de l'éditeur à blocs).",
            'inputSchema' => $objet(['titre' => $texte('Titre'), 'texte' => $texte('Texte simple'), 'blocs' => ['type' => 'object'], 'parent_id' => ['type' => 'integer'], 'publiee' => ['type' => 'boolean']], ['titre'])],
        ['name' => 'modifier_page', 'description' => 'Modifier une page (titre, contenu, parent, publication).',
            'inputSchema' => $objet(['id' => ['type' => 'integer'], 'titre' => $texte('Titre'), 'texte' => $texte('Texte simple'), 'blocs' => ['type' => 'object'], 'parent_id' => ['type' => 'integer'], 'publiee' => ['type' => 'boolean']], ['id'])],
        ['name' => 'lister_membres', 'description' => 'Membres (droit « membres » nécessaire).', 'inputSchema' => $objet([])],
    ];
}

function mcp_appeler(string $name, array $a): array
{
    return match ($name) {
        'moi' => api_moi(),
        'lister_espaces' => api_espaces(),
        'lire_espace' => api_espace((string) ($a['slug'] ?? '')),
        'modifier_espace' => api_espace_modifier((string) ($a['slug'] ?? ''), $a),
        'ajouter_date_cle' => api_date_cle_ajouter((string) ($a['slug'] ?? ''), (string) ($a['date'] ?? ''), (string) ($a['libelle'] ?? '')),
        'lister_pages' => api_pages(),
        'lire_page' => api_page((int) ($a['id'] ?? 0)),
        'creer_page' => api_page_ecrire(null, $a),
        'modifier_page' => api_page_ecrire((int) ($a['id'] ?? 0), $a),
        'lister_membres' => api_membres(),
        default => throw new ApiErreur("Outil inconnu : $name", 404),
    };
}

function mcp_repondre(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $request = json_decode((string) file_get_contents('php://input'), true);
    $id = is_array($request) ? ($request['id'] ?? null) : null;
    $reply = fn(array $payload) => print(json_encode(['jsonrpc' => '2.0', 'id' => $id] + $payload, JSON_UNESCAPED_UNICODE));
    if (!is_array($request) || ($request['jsonrpc'] ?? '') !== '2.0' || !is_string($request['method'] ?? null)) {
        http_response_code(400);
        $reply(['error' => ['code' => -32600, 'message' => 'Requête JSON-RPC invalide']]);
        return;
    }
    try {
        api_authentifier();
    } catch (ApiErreur $erreur) {
        http_response_code(401);
        header('WWW-Authenticate: Bearer');
        $reply(['error' => ['code' => -32001, 'message' => $erreur->getMessage()]]);
        return;
    }
    // Notification (sans id) : rien à répondre
    if (!array_key_exists('id', $request)) {
        http_response_code(202);
        return;
    }
    $params = is_array($request['params'] ?? null) ? $request['params'] : [];
    switch ($request['method']) {
        case 'initialize':
            $version = in_array($params['protocolVersion'] ?? '', MCP_VERSIONS, true) ? $params['protocolVersion'] : MCP_VERSIONS[0];
            $reply(['result' => [
                'protocolVersion' => $version,
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => ['name' => 'boussole', 'version' => BOUSSOLE_VERSION],
                'instructions' => 'Outils pour ' . setting('nom_association', 'cette association') . ' : espaces, dates clés, pages du site. Les droits sont ceux du membre propriétaire de la clé.',
            ]]);
            return;
        case 'ping':
            $reply(['result' => (object) []]);
            return;
        case 'tools/list':
            $reply(['result' => ['tools' => mcp_outils()]]);
            return;
        case 'tools/call':
            try {
                $result = mcp_appeler((string) ($params['name'] ?? ''), is_array($params['arguments'] ?? null) ? $params['arguments'] : []);
                $reply(['result' => ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]], 'isError' => false]]);
            } catch (ApiErreur $erreur) {
                $reply(['result' => ['content' => [['type' => 'text', 'text' => $erreur->getMessage()]], 'isError' => true]]);
            }
            return;
        default:
            $reply(['error' => ['code' => -32601, 'message' => 'Méthode inconnue : ' . $request['method']]]);
    }
}
