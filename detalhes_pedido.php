<?php
/**
 * ARQUIVO: detalhes_pedido.php
 * RESPONSABILIDADE: Painel final para o Solicitante que emitiu um pedido,
 * ou o Histórico passível de Impressão do Logístico. Exibe item por item de 
 * de uma requisição e apresenta os motivos para os negados/cortados parcialmente.
 */

// 1. Controle
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// 2. Coletamos na URL de qual Recibo o funcionário quis abrir a tela
$id_requisicao = $_GET['id'] ?? null;
if (!$id_requisicao) { 
    header("Location: dashboard.php"); 
    exit; 
}

// 3. RECUPERAÇÃO INFORMATIVA DE SEGURANÇA (Autorização)
// Usamos um truque aqui: Ao buscar a Tabela-Mãe (Requisicoes), informamos a cláusula `WHERE (id_usuario = eu_mesmo OR sou_O_Admin)`.
// Se a professora Tenta acessar uma URL hackeando pro ID 99, e o 99 for do porteiro, a query voltará VAZIA, bloqueando tela.
$is_almox_gerencial = in_array($_SESSION['nivel_acesso'], ['almoxarife', 'admin']) ? 1 : 0;

$stmt = $pdo->prepare("SELECT r.*, u.nome as solicitante 
                       FROM requisicoes r 
                       JOIN usuarios u ON r.id_usuario = u.id 
                       WHERE r.id = :id AND (r.id_usuario = :id_usuario OR :is_almoxarife = 1)");
$stmt->execute([
    ':id' => $id_requisicao, 
    ':id_usuario' => $_SESSION['usuario_id'],
    ':is_almoxarife' => $is_almox_gerencial
]);
$requisicao = $stmt->fetch();

// Redireciona de Volta caso não tenha permissão de espionar coleguinhas
if (!$requisicao) { header("Location: dashboard.php"); exit; }

// 4. Se a Requisição for autorizada a abrir, puxa os Itens Internos dela:
// A tabela ITENS se relaciona com PRODUTOS para devolver o Nome que o funcionário digitou na época.
$stmt = $pdo->prepare("SELECT ir.qtd_pedida, ir.qtd_entregue, ir.motivo_parcial, p.nome, p.descricao 
                       FROM itens_requisicao ir 
                       JOIN produtos p ON ir.id_produto = p.id 
                       WHERE ir.id_requisicao = :id_requisicao");
$stmt->execute([':id_requisicao' => $id_requisicao]);
$itens = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Requisição #<?= htmlspecialchars($id_requisicao) ?></title>
    <!-- Template UI Premium -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style> 
        body { background-color: #f4f7fa; font-family: 'Inter', system-ui, sans-serif; }
        @media print { 
            .no-print, nav, .btn, .alert-info, .navbar { display: none !important; } 
            body { background: white !important; padding: 0 !important; margin: 0 !important; }
            .glass-box { box-shadow: none !important; border: 1px solid #000 !important; padding: 0 !important; max-width: 100% !important; margin: 0 !important; border-radius: 0 !important; }
            .info-card { border: 1px solid #000 !important; background: transparent !important; border-radius: 0 !important; }
            .table { border: 1px solid #000 !important; }
            .table th, .table td { border: 1px solid #000 !important; color: #000 !important; }
            .container { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0.5in !important; }
            .text-primary, .text-success, .text-danger { color: #000 !important; font-weight: bold !important; }
            .bg-primary, .bg-success, .bg-light { background-color: transparent !important; }
        }        
        .glass-box { background: white; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.05); }
        .info-card { background-color: #f8f9fa; border-radius: 8px; padding: 15px; border-left: 4px solid #0d6efd;}
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    
    <!-- CABEÇALHO DE TIMBRADO (Só aparece na Impressora)  -->
    <div class="d-none d-print-block text-center mb-4 mt-2">
        <h3 class="fw-bold">COMPROVANTE DE REQUISIÇÃO DE MATERIAIS</h3>
        <p class="text-muted small">Prefeitura Municipal de São Carlos - Emprego e Renda</p>
        <hr class="border-2 border-dark opacity-100">
    </div>

    <!-- ÁREA CENTRAL -->
    <div class="glass-box mx-auto p-4 p-md-5" style="max-width: 900px;">
        
        <!-- CABEÇALHO DO LAUDO (Header) -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-diagram-3 text-primary me-2"></i> 
                    Pedido #<?= str_pad($id_requisicao, 5, "0", STR_PAD_LEFT) ?>
                </h4>
            </div>
            <button onclick="window.print()" class="btn btn-light border text-secondary btn-sm no-print rounded-pill px-3 fw-bold">
                <i class="bi bi-printer me-1"></i> Imprimir Pedido
            </button>
        </div>
        
        <!-- BLOCO 1: RESUMOS BUROCRÁTICOS -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="info-card h-100" style="border-left-color: #6c757d;">
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Solicitante</span>
                    <span class="fw-bold fs-6 text-dark"><i class="bi bi-person me-1"></i> <?= htmlspecialchars($requisicao['solicitante']) ?></span>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="info-card h-100" style="border-left-color: #0dcaf0;">
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Data do Pedido</span>
                    <span class="fw-bold fs-6 text-dark"><i class="bi bi-calendar-event me-1"></i> <?= date('d/m/y \à\s H:i', strtotime($requisicao['data_pedido'])) ?></span>
                </div>
            </div>

            <div class="col-md-4">
                <!-- A cor da Box muda logicamente baseada se foi ou não aprovada no Banco -->
                <?php
                    $bl_c = '#ffc107'; $s_t = 'Aguardando Análise';
                    if ($requisicao['status'] === 'Atendido') { $bl_c = '#198754'; $s_t = 'Finalizado'; }
                    elseif ($requisicao['status'] === 'Negado') { $bl_c = '#dc3545'; $s_t = 'Cancelado'; }
                ?>
                <div class="info-card h-100" style="border-left-color: <?= $bl_c ?>;">
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1">Status do Pedido</span>
                    <span class="fw-bold fs-6 text-dark"><?= $requisicao['status'] ?> <small class="fw-normal text-muted">(<?= $s_t ?>)</small></span>
                </div>
            </div>
        </div>
        
        <!-- ALERT DE NEGAÇÃO COMPLETA:
             Aparecerá visível um caixão vermelho forte caso o diretor Logístico tenha cancelado
             Toda a página de requisições inteira, via caixa Justificativa no Banco MYSQL .
        -->
        <?php if ($requisicao['status'] === 'Negado' && !empty($requisicao['justificativa'])): ?>
            <div class="alert alert-danger px-4 py-3 rounded-4 border-0 shadow-sm mt-2 mb-4">
                <div class="d-flex">
                    <i class="bi bi-x-circle-fill fs-3 text-danger me-3 mt-1"></i>
                    <div>
                        <h6 class="fw-bold text-danger mb-1">Pedido Negado/Cancelado</h6>
                        <p class="mb-0 text-dark small"><b>Motivo informado pelo setor:</b> "<?= nl2br(htmlspecialchars($requisicao['justificativa'])) ?>"</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <h6 class="fw-bold mt-5 mb-3 text-muted text-uppercase">Itens do Pedido</h6>
        
        <!-- BLOCO 2: TABELA DE ITENS QUE FORAM PEDIDOS EM FORMATO LIMPO -->
        <div class="table-responsive bg-white rounded border">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4 fw-bold border-0">Produto</th>
                        <th width="100" class="text-center fw-bold border-0">Status</th>
                        <th width="110" class="text-center fw-bold border-0 bg-primary bg-opacity-10 text-primary">Pedido</th>
                        <th width="110" class="text-center fw-bold border-0 bg-success bg-opacity-10 text-success">Entregue</th>
                        <th width="280" class="fw-bold border-0">Observações do Setor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                    <tr>
                        <!-- 1. Nome -->
                        <td class="ps-4">
                            <b class="text-dark"><?= htmlspecialchars($item['nome']) ?></b>
                            <div class="small text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars($item['descricao']) ?></div>
                        </td>
                        
                        <!-- 2. Ícone Emoticon Rápido visualmente -->
                        <td class="text-center fs-4">
                            <?= ($requisicao['status'] === 'Pendente') ? '<i class="bi bi-hourglass-split text-warning"></i>' : (($item['qtd_entregue'] > 0) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger opacity-50"></i>') ?>
                        </td>
                        
                        <!-- 3. Quantia Demandada Inicial-->
                        <td class="text-center bg-primary bg-opacity-10 fw-bold text-primary fs-5"><?= $item['qtd_pedida'] ?></td>
                        
                        <!-- 4. Quantia Entregue Fisicamente (Pendente ganha traço) -->
                        <?php
                            // Logica Didática de Cores
                            $cor_entrega = 'text-success fw-bold'; // Verde para entrega Cheia 100%
                            if ($item['qtd_entregue'] < $item['qtd_pedida'] && $requisicao['status'] === 'Atendido') {
                                $cor_entrega = 'text-warning text-dark fw-bold'; // Amarelo: Entregou mas n entregou tudo (faltou)
                            } elseif ($requisicao['status'] === 'Negado' || $item['qtd_entregue'] == 0) {
                                $cor_entrega = 'text-danger fw-bold'; // Vermelho absoluto NADA recebido
                            } else if ($requisicao['status'] === 'Pendente') {
                                $cor_entrega = 'text-muted'; // Pendente logistica
                            }
                        ?>
                        <td class="text-center bg-success bg-opacity-10 fs-5 <?= $cor_entrega ?>">
                            <?= $requisicao['status'] === 'Pendente' ? '-' : $item['qtd_entregue'] ?>
                        </td>
                        
                        <!-- 5. FEEDBACK PARCIAL DE LINHA INDIVIDUAL (Se o admin enviou 2 borrachas e o cara pediu 5.. exibe aqui pq enviou só 2) -->
                        <td class="pe-3">
                            <div class="p-2 bg-light rounded small text-dark">
                                <?php 
                                    if ($requisicao['status'] === 'Pendente') {
                                        echo "<span class='text-muted'><i class='bi bi-clock me-1'></i>Aguardando setor...</span>";
                                    } else {
                                        if ($item['qtd_entregue'] < $item['qtd_pedida']) {
                                            // Se cortou parte... 
                                            echo "<i class='bi bi-info-circle-fill text-danger me-1'></i> " . (!empty($item['motivo_parcial']) ? htmlspecialchars($item['motivo_parcial']) : "* Ajuste feito pelo setor.");
                                        } else {
                                            // Sucesso 100%
                                            echo '<span class="text-success fw-bold"><i class="bi bi-check2-all me-1"></i>Quantidade Entrega Completa</span>';
                                        }
                                    }
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
