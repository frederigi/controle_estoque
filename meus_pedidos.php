<?php
/**
 * ARQUIVO: meus_pedidos.php
 * RESPONSABILIDADE: Listar todas as requisições enviadas pelo solicitante logado.
 * Permite acompanhar o status (Pendente, Atendido, Negado) de forma clara.
 */

session_start();
require_once 'conexao.php';

// Segurança: Somente solicitantes (ou outros logados) veem seus próprios pedidos
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Busca as requisições do usuário logado
try {
    $stmt = $pdo->prepare("SELECT id, data_pedido, status, justificativa 
                           FROM requisicoes 
                           WHERE id_usuario = :id_usuario 
                           ORDER BY data_pedido DESC");
    $stmt->execute([':id_usuario' => $usuario_id]);
    $meus_pedidos = $stmt->fetchAll();
} catch (Exception $e) {
    die("Erro ao carregar seus pedidos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos - Estoque Fácil - DERSC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Outfit', sans-serif; color: #334155; }
        .glass-box { background: white; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: none; padding: 2rem; }
        .table-custom { border-collapse: separate; border-spacing: 0 10px; }
        .table-custom tbody tr { background: white; border-radius: 12px; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .table-custom tbody tr:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .table-custom td { border: none !important; padding: 1.25rem; vertical-align: middle; }
        .badge-status { padding: 0.6rem 1rem; border-radius: 50px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
        .status-pendente { background: #fff7ed; color: #9a3412; }
        .status-atendido { background: #f0fdf4; color: #166534; }
        .status-negado { background: #fef2f2; color: #991b1b; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Histórico de Solicitações</h4>
            <p class="text-muted small mb-0">Acompanhe o andamento dos seus pedidos de material</p>
        </div>
        <a href="nova_requisicao.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-plus-lg me-2"></i> Novo Pedido
        </a>
    </div>

    <div class="glass-box">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr class="text-muted small text-uppercase">
                        <th class="ps-4">Código</th>
                        <th>Data e Hora</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($meus_pedidos) > 0): ?>
                        <?php foreach ($meus_pedidos as $pedido): ?>
                            <?php 
                                $statusClass = 'status-pendente';
                                if ($pedido['status'] === 'Atendido') $statusClass = 'status-atendido';
                                if ($pedido['status'] === 'Negado') $statusClass = 'status-negado';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-dark">#<?= str_pad($pedido['id'], 5, "0", STR_PAD_LEFT) ?></span>
                                </td>
                                <td class="text-muted">
                                    <i class="bi bi-calendar3 me-2"></i><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-status <?= $statusClass ?>">
                                        <?= $pedido['status'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="detalhes_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-primary">
                                        Ver Itens <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center p-5">
                                <i class="bi bi-inbox text-muted display-1 opacity-25 d-block mb-3"></i>
                                <h5 class="text-muted fw-bold">Você ainda não fez nenhum pedido.</h5>
                                <a href="nova_requisicao.php" class="btn btn-primary mt-3 rounded-pill px-4">Fazer meu primeiro pedido</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
