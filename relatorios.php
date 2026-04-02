<?php
/**
 * ARQUIVO: relatorios.php
 * RESPONSABILIDADE: Painel Consolidado de Extratos para o Gestor.
 * Exibe Entradas x Saídas (Requisições Atendidas) filtradas por data, 
 * desenhadas para serem Impressas em papel A4 formatado perfeitamente.
 */
session_start();
require_once 'conexao.php';

// Bloqueio de Segurança: Apenas a Chefia Logística acessa relatório
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['nivel_acesso'], ['almoxarife', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

// =========================================================================
// LÓGICA DE FILTRAGEM
// Usamos D-1 (Primeiro dia do Mês) até D-31 (Último dia do mês) como Padrão 
// caso o usuário nao tenha clicado em Filtrar ainda.
// =========================================================================
$data_inicio = $_GET['inicio'] ?? date('Y-m-01'); 
$data_fim = $_GET['fim'] ?? date('Y-m-t'); 

try {
    // === BUSCA 1 : ENTRADAS (Tudo que o caminhão descarregou) ===
    $stmt_entradas = $pdo->prepare("SELECT e.id, p.nome as produto, e.quantidade, e.lote, e.validade, e.data_entrada 
                                    FROM entradas e 
                                    JOIN produtos p ON e.id_produto = p.id 
                                    WHERE DATE(e.data_entrada) BETWEEN :inicio AND :fim 
                                    ORDER BY e.data_entrada DESC");
    $stmt_entradas->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
    $lista_entradas = $stmt_entradas->fetchAll();

    // === BUSCA 2 : SAÍDAS (Tudo que funcionário pediu E foi DEFERIDO (> 0 fornecidos) ===
    $stmt_saidas = $pdo->prepare("SELECT ir.id, p.nome as produto, ir.qtd_pedida, ir.qtd_entregue, ir.motivo_parcial, r.data_pedido, u.nome as solicitante
                                  FROM itens_requisicao ir
                                  JOIN produtos p ON ir.id_produto = p.id
                                  JOIN requisicoes r ON ir.id_requisicao = r.id
                                  JOIN usuarios u ON r.id_usuario = u.id
                                  WHERE r.status = 'Atendido' AND ir.qtd_entregue > 0 AND DATE(r.data_pedido) BETWEEN :inicio AND :fim 
                                  ORDER BY r.data_pedido DESC");
    $stmt_saidas->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
    $lista_saidas = $stmt_saidas->fetchAll();

} catch(Exception $e) {
    die("Falha técnica no motor de relatórios: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios do Estoque</title>
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

        .card-stat {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
        }
        
        /* Tabela */
        .table-custom { border-collapse: separate; border-spacing: 0 10px; }
        .table-custom tbody tr { background: white; border-radius: 12px; transition: all 0.2s ease; }
        .table-custom td { border: none !important; padding: 1rem; vertical-align: middle; }
        .table-custom thead th { border: none; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; }

        @media print { 
            .no-print, nav, .btn, .navbar, #navBarMenu { display: none !important; } 
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .glass-box { box-shadow: none !important; border: 1px solid #ddd !important; padding: 1.5rem !important; margin-bottom: 2rem !important; }
            .container { width: 100% !important; max-width: 100% !important; padding: 0.5in !important; margin: 0 !important; }
            .table-custom tbody tr { box-shadow: none !important; border-bottom: 1px solid #eee !important; }
            .page-break { page-break-after: always; display: block; height: 0; }
            h5.fw-bold { color: #000 !important; font-weight: 800 !important; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 8px; }
            .text-success, .text-warning { color: #000 !important; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- BARRA SUPERIOR (ocultada na Impressora) -->
<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    
    <div class="d-flex justify-content-between align-items-end mb-4 no-print">
        <div>
            <h4 class="fw-bold text-dark mb-1">Extrato de Movimentação</h4>
            <p class="text-muted small mb-0">Período: <b><?= date('d/m/Y', strtotime($data_inicio)) ?></b> até <b><?= date('d/m/Y', strtotime($data_fim)) ?></b></p>
        </div>
        <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm btn-print">
            <i class="bi bi-printer me-2"></i> Imprimir
        </button>
    </div>

    <!-- PAINEL DE FILTROS -->
    <div class="glass-box p-4 mb-4 no-print">
        <form method="GET" action="relatorios.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Início</label>
                <input type="date" name="inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Fim</label>
                <input type="date" name="fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">Filtrar Dados</button>
            </div>
        </form>
    </div>

    <!-- RESUMO RÁPIDO -->
    <div class="row g-3 mb-5 no-print">
        <div class="col-md-6">
            <div class="card-stat border-start border-success border-4">
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                    <i class="bi bi-box-arrow-in-down fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= count($lista_entradas) ?></h3>
                    <p class="text-muted small mb-0">Entradas Registradas</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-stat border-start border-warning border-4">
                <div class="bg-warning bg-opacity-10 text-dark p-3 rounded-circle me-3">
                    <i class="bi bi-box-arrow-up fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= count($lista_saidas) ?></h3>
                    <p class="text-muted small mb-0">Saídas (Entregues)</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================================================= -->
    <!-- CABEÇALHO DE TIMBRADO (Só aparece na folha de papel quando imprimir)  -->
    <!-- ========================================================================= -->
    <div class="d-none d-print-block text-center mb-5 mt-4">
        <h2 class="fw-bold">RELATÓRIO DO CONTROLE DE ESTOQUE</h2>
        <h5 class="text-muted mt-2"><b>Período:</b> <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?></h5>
        <hr class="mt-4 border-2 border-dark opacity-100">
    </div>

    <!-- SESSÃO 1: ENTRADAS -->
<div class="glass-box mb-5">
        <h5 class="fw-bold text-success mb-4"><i class="bi bi-truck me-2"></i> Entradas de Materiais</h5>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Data</th>
                        <th>Produto</th>
                        <th class="text-center">Quantidade</th>
                        <th class="text-end pe-4">Lote/Nota</th>
                    </tr>
                </thead>
                <tbody>
                        <?php if (count($lista_entradas) > 0): ?>
                            <?php foreach ($lista_entradas as $ent): ?>
                                <tr>
                                    <td class="ps-4 small text-muted"><?= date('d/m/Y', strtotime($ent['data_entrada'])) ?></td>
                                    <td><b class="text-dark"><?= htmlspecialchars($ent['produto']) ?></b></td>
                                    <td class="text-center fw-bold text-success">+<?= $ent['quantidade'] ?></td>
                                    <td class="text-end pe-4 small text-muted"><?= $ent['lote'] ?: '--' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center p-5 text-muted">Vazio.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    
    <!-- Este break manda a impressora puxar outra folha Sulfite pra a segunda tabela !-->
    <div class="page-break w-100"></div>

    <!-- SESSÃO 2: SAÍDAS -->
<div class="glass-box mb-5">
        <h5 class="fw-bold text-warning mb-4"><i class="bi bi-cart-check me-2"></i> Saídas de Materiais</h5>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Data</th>
                        <th>Produto</th>
                        <th>Solicitante</th>
                        <th class="text-center">Quantidade</th>
                    </tr>
                </thead>
                <tbody>
                        <?php if (count($lista_saidas) > 0): ?>
                            <?php foreach ($lista_saidas as $sai): ?>
                                <tr>
                                    <td class="ps-4 small text-muted"><?= date('d/m/Y', strtotime($sai['data_pedido'])) ?></td>
                                    <td><b class="text-dark"><?= htmlspecialchars($sai['produto']) ?></b></td>
                                    <td class="small text-muted"><?= htmlspecialchars($sai['solicitante']) ?></td>
                                    <td class="text-center fw-bold text-danger"><?= $sai['qtd_entregue'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center p-5 text-muted">Vazio.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Rodapé de Autenticação Impresso -->
    <div class="d-none d-print-block text-center mt-5 small text-muted border-top pt-3">
        <i>* Relatório gerado pelo Sistema de Controle de Estoque em <b><?= date('d/m/Y H:i:s') ?></b> pelo usuário: <b><?= htmlspecialchars($_SESSION['usuario_nome']) ?></b>.</i>
    </div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
