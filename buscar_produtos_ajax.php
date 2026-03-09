<?php
/**
 * ARQUIVO: buscar_produtos_ajax.php
 * RESPONSABILIDADE: Atender requisições JavaScript (Fetch) para busca em tempo real.
 * Retorna JSON com os produtos encontrados.
 */

session_start();
require_once 'conexao.php';

// Segurança: Apenas quem está logado pode consultar o banco
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

$query = $_GET['q'] ?? '';

// Só pesquisa se houver pelo menos 2 caracteres para não sobrecarregar o banco
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Busca produtos que comecem ou contenham o termo digitado
    $stmt = $pdo->prepare("SELECT id, nome, descricao FROM produtos WHERE nome LIKE :q OR descricao LIKE :q ORDER BY nome ASC LIMIT 10");
    $stmt->execute([':q' => "%$query%"]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($produtos);

} catch (Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['erro' => $e->getMessage()]);
}
