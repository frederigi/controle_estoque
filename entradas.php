<?php
/**
 * ARQUIVO: entradas.php
 * RESPONSABILIDADE: Painel logístico onde o Almoxarife registra 
 * a chegada física de caminhões/malotes. Aqui, alimentamos o saldo 
 * dos produtos e mantemos o registro de rastreabilidade (Nº de Lote e Data de Validade).
 */
session_start();
require_once 'conexao.php';

// Controle de Nível: Retorna quem não é da logística
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['nivel_acesso'], ['almoxarife', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = '';
$sucesso = '';

/**
 * =========================================================================
 * PROCESSAMENTO DE NOVAS ENTRADAS (Somatória no Saldo Físico)
 * =========================================================================
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_produto'])) {
    $id_produto = $_POST['id_produto'];
    $quantidade = (int) $_POST['quantidade'];
    $lote = trim($_POST['lote'] ?? '');
    
    // Tratamento de Data Nula: Se bater vazio, temos que forçar a variável para NULL 
    // ou o banco MySQL vai reclamar que uma string "" não é formato de data 'YYYY-MM-DD'.
    $validade = trim($_POST['validade'] ?? '');
    if (empty($validade)) {
        $validade = null;
    }

    // Regra de Negócio: Não faz sentido o almoxarifado "receber Zero caixas".
    if ($quantidade > 0) {
        try {
            // Utilizamos Transaction para garantir que Log de Entrada e Saldo de Estoque ocorram em Sincronia.
            $pdo->beginTransaction();

            // 1) LANÇAMENTO DE RECIBO
            $stmt = $pdo->prepare("INSERT INTO entradas (id_produto, quantidade, lote, validade, id_usuario) 
                                   VALUES (:id_produto, :quantidade, :lote, :validade, :id_usuario)");
            $stmt->execute([
                ':id_produto' => $id_produto,
                ':quantidade' => $quantidade,
                ':lote' => $lote,
                ':validade' => $validade,
                ':id_usuario' => $_SESSION['usuario_id']
            ]);

            // 2) ATUALIZAÇÃO DO ESTOQUE
            $stmtUpdate = $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + :qtd WHERE id = :id");
            $stmtUpdate->execute([':qtd' => $quantidade, ':id' => $id_produto]);

            $pdo->commit();
            $sucesso = "Entrada salva e quantidade adicionada ao estoque com sucesso!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Falha ao registrar entrada no sistema: " . $e->getMessage();
        }
    } else {
        $erro = "Atenção: A quantidade da entrada deve ser maior do que zero.";
    }
}

// -------------------------------------------------------------
// DADOS PARA O FRONT-END
// -------------------------------------------------------------

// Busca o Catálogo Mestre apenas para gerar as "Options" de Produto do FormHTML.
$stmt = $pdo->query("SELECT id, nome, estoque_atual FROM produtos ORDER BY nome ASC");
$produtos = $stmt->fetchAll();

// Busca o Livro-Caixa de Entradas para exibir a listagem.
// Utilizamos JOIN com produtos e usuarios para ao invés do código Id, ele mostre o Nome Humano (p.nome / u.nome)
$stmt = $pdo->query("SELECT e.id, p.nome as produto, e.quantidade, e.lote, e.validade, e.data_entrada, u.nome as responsavel
                     FROM entradas e
                     JOIN produtos p ON e.id_produto = p.id
                     JOIN usuarios u ON e.id_usuario = u.id
                     ORDER BY e.data_entrada DESC 
                     LIMIT 50");
$entradas = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Recebimentos</title>
    <!-- Template UI Premium -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style> 
        body { background-color: #f0f2f5; font-family: 'Outfit', 'Inter', sans-serif; color: #334155; }
        
        .navbar { background: #ffffff !important; border-bottom: 1px solid #e2e8f0; }
        .navbar-brand { color: #0f172a !important; font-weight: 800; }

        .glass-box { 
            background: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
            border: none;
            padding: 2rem;
        }
        
        /* Tabela */
        .table-custom { border-collapse: separate; border-spacing: 0 10px; }
        .table-custom tbody tr { background: white; border-radius: 12px; transition: all 0.2s ease; }
        .table-custom td { border: none !important; padding: 1rem; vertical-align: middle; }
        .table-custom thead th { border: none; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; }
        
        .btn-add {
            background: #22c55e;
            color: white;
            border-radius: 15px;
            padding: 0.8rem 1.5rem;
            font-weight: 700;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-add:hover { background: #16a34a; transform: scale(1.05); color: white; }

        .modal-content { border-radius: 24px; border: none; }
        .form-control { border-radius: 12px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; background: #f8fafc; }
        
        /* REGRAS DE IMPRESSÃO */
        @media print {
            .no-print, nav, .btn, .navbar, #navBarMenu, .alert-info, .alert-danger, .alert-success { display: none !important; }
            body { background: white !important; font-size: 10pt; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .glass-box { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .table { width: 100% !important; border-collapse: collapse !important; }
            .table-custom tr { background: transparent !important; border-bottom: 1px solid #000 !important; }
            .table th, .table td { border: 1px solid #000 !important; padding: 5px !important; color: #000 !important; vertical-align: middle !important; }
            .table thead th { background-color: #f0f0f0 !important; border: 1px solid #000 !important; font-weight: bold !important; color: #000 !important; }
            .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; }
            .d-print-block { display: block !important; }
            .text-success, .text-muted { color: #000 !important; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    
    <!-- CABEÇALHO PARA IMPRESSÃO -->
    <div class="d-none d-print-block text-center mb-4 mt-2">
        <h3 class="fw-bold">RELATÓRIO DE ENTRADAS DE MATERIAIS</h3>
        <p class="text-muted small">Prefeitura Municipal de São Carlos - Emprego e Renda</p>
        <hr class="border-2 border-dark opacity-100">
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="fw-bold text-dark mb-1">Entradas de Estoque</h4>
            <p class="text-muted small mb-0">Adicione quantidades aos produtos já cadastrados</p>
        </div>
        <button type="button" class="btn btn-add shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEntrada">
            <i class="bi bi-plus-lg me-2"></i> Registrar Entrada
        </button>
    </div>

    <!-- AVISO DE ORIENTAÇÃO PARA O ALMOXARIFE -->
    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center no-print">
        <i class="bi bi-truck fs-4 me-3 text-info"></i>
        <div>
            <h6 class="fw-bold mb-1">Dica de Logística</h6>
            <p class="mb-0 small text-dark">Use esta tela para dar entrada física nos materiais que chegaram. Só aparecerão aqui os produtos que você já cadastrou previamente na tela de <a href="admin_produtos.php" class="fw-bold text-primary">Produtos</a>.</p>
        </div>
    </div>

    <?php if ($erro): ?> 
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($erro) ?></div> 
    <?php endif; ?>
    <?php if ($sucesso): ?> 
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><i class="bi bi-check2-circle me-2"></i><?= $sucesso ?></div> 
    <?php endif; ?>

    <div class="glass-box">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-bold text-muted mb-0"><i class="bi bi-clock-history me-2"></i> Histórico Recente</h6>
            <button onclick="window.print()" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold no-print">Imprimir</button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Data</th>
                        <th>Produto</th>
                        <th class="text-center">Quantidade</th>
                        <th>Lote/Nota</th>
                        <th class="text-end pe-4">Responsável</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($entradas) > 0): ?>
                        <?php foreach ($entradas as $e): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="d-block fw-bold text-dark small"><?= date('d/m/Y', strtotime($e['data_entrada'])) ?></span>
                                    <span class="small text-muted"><?= date('H:i', strtotime($e['data_entrada'])) ?></span>
                                </td>
                                <td><b class="text-dark"><?= htmlspecialchars($e['produto']) ?></b></td>
                                <td class="text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-bold">
                                        +<?= $e['quantidade'] ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= $e['lote'] ?: '--' ?></td>
                                <td class="text-end pe-4 small text-muted"><?= htmlspecialchars($e['responsavel']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center p-5 text-muted">Nenhuma entrada registrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL DE ENTRADA -->
<div class="modal fade" id="modalEntrada" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content overflow-hidden">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-success mb-0">Adicionar Estoque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="entradas.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Selecione o Produto</label>
                        <select name="id_produto" class="form-select" required>
                            <option value="">Escolher...</option>
                            <?php foreach ($produtos as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['nome']) ?> (Estoque: <?= $p['estoque_atual'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Quantidade</label>
                        <input type="number" name="quantidade" class="form-control text-center fw-bold text-success fs-4" min="1" required placeholder="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Lote ou Número da Nota</label>
                        <input type="text" name="lote" class="form-control" placeholder="Opcional">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small">Validade</label>
                        <input type="date" name="validade" class="form-control">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success py-3 rounded-4 fw-bold shadow-sm">
                            Confirmar Entrada <i class="bi bi-check-lg ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
