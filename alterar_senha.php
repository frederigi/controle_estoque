<?php
/**
 * ARQUIVO: alterar_senha.php
 * RESPONSABILIDADE: Processar a troca de senha do usuário logado via AJAX.
 */
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['senha'] ?? '';
    
    if (strlen($nova_senha) < 4) {
        echo json_encode(['success' => false, 'error' => 'A senha deve ter pelo menos 4 caracteres.']);
        exit;
    }

    try {
        $id = $_SESSION['usuario_id'];
        $hash = md5($nova_senha); // Mantendo o padrão MD5 do projeto

        $stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
        $stmt->execute([':senha' => $hash, ':id' => $id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
}
