<?php
/**
 * ARQUIVO: processar_pedido.php
 * RESPONSABILIDADE: Tela do Almoxarife/Admin para interagir com o 
 * pedido feito pelo funcionário. Aqui a logística decide se entrega 
 * o solicitado na íntegra, parcialmente ou se nega tudo de vez.
 */

// 1. Controle de sessão e importação de banco de dados
session_start();
require_once 'conexao.php';

// 2. SEGURANÇA: Essa página não pode ser acessada por Solicitantes.
// Bloqueia quem não está logado OU quem não é almoxarife nem admin.
if (!isset($_SESSION['usuario_id']) || ($_SESSION['nivel_acesso'] !== 'almoxarife' && $_SESSION['nivel_acesso'] !== 'admin')) {
    header("Location: dashboard.php");
    exit;
}

// 3. CAPTURA DO ID VIA GET (URL)
// Exemplo: processar_pedido.php?id=15 ... Pegamos esse número 15. Se não existir, voltamos.
$id_requisicao = $_GET['id'] ?? null;
if (!$id_requisicao) { 
    header("Location: dashboard.php"); 
    exit; 
}

// 4. BUSCA DO PEDIDO "CABEÇALHO" NO BANCO
// Queremos pegar todas as infos da Tabela Requisicoes (r.*) E o Nome da pessoa 
// que fez esse pedido cruzando com a Tabela Usuarios (u.nome).
$stmt = $pdo->prepare("SELECT r.*, u.nome as solicitante 
                       FROM requisicoes r 
                       JOIN usuarios u ON r.id_usuario = u.id 
                       WHERE r.id = :id");
$stmt->execute([':id' => $id_requisicao]);
$requisicao = $stmt->fetch();

// Prevenção: Se a requisição não existir no banco OU se já foi atendida/negada (já não é 'Pendente'), abortamos.
if (!$requisicao || $requisicao['status'] !== 'Pendente') {
    header("Location: dashboard.php"); 
    exit;
}

// 5. CRUZA O PEDIDO CABEÇALHO PARA BUSCAR OS SEUS ITENS (MATERIAL X VS QUANTIDADES)
$stmt = $pdo->prepare("SELECT ir.id, ir.id_produto, ir.qtd_pedida, p.nome, p.estoque_atual 
                       FROM itens_requisicao ir 
                       JOIN produtos p ON ir.id_produto = p.id 
                       WHERE ir.id_requisicao = :id_requisicao");
$stmt->execute([':id_requisicao' => $id_requisicao]);
$itens = $stmt->fetchAll();

$erro = ''; 
$sucesso = '';

/**
 * =========================================================================
 * FLUXO DE PROCESSAMENTO LOGÍSTICO (Quando o Almoxarife aperta o botão "Enviar")
 * =========================================================================
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Capturamos a ação escolhida nas abas visuais do Bootstrap: 'atender' (Aprovar pedido Total ou Parcial) ou 'negar' (Cancelar tudo)
    $acao = $_POST['acao'] ?? ''; 
    
    try {
        // Garantia ACID de Bancos Relacionais: Tudo funciona, ou Nada é feito.
        $pdo->beginTransaction();

        // CASO A: NEGAR A REQUISIÇÃO INTEIRA DE FORMA ABRUPTA
        if ($acao === 'negar') {
            
            // Justificativa do textarea lá embaixo do formHTML
            $justificativa = trim($_POST['justificativa'] ?? '');
            
            // Validação Educativa de Domínio Público: Todo órgão do sistema S, Prefeituras, devem justificar os "Nãos".
            if (empty($justificativa)) {
                throw new Exception("ATENÇÃO: É obrigatório informar o motivo para negar um pedido em repartições públicas.");
            }

            // Atualizamos o status e registramos o motivo na tabela-mãe.
            $stmt = $pdo->prepare("UPDATE requisicoes SET status = 'Negado', justificativa = :justificativa WHERE id = :id");
            $stmt->execute([':justificativa' => $justificativa, ':id' => $id_requisicao]);
            
            $sucesso = "Este pedido foi NEGADO na totalidade. O funcionário será notificado visualmente.";

        // CASO B: ATENDIMENTO NORMAL (SEJA 100% DA PEDIDA, OU PARTE DELA)
        } elseif ($acao === 'atender') {
            
            // Arrays (listas) HTML chegam num formato qtd_entregue[ID_DO_PRODUTO] = valor_digitado.
            $qtd_entregue = $_POST['qtd_entregue'] ?? [];
            $motivo_parcial = $_POST['motivo_parcial'] ?? [];

            // Laço de repetição que vai processar e descontar do estoque Linha por Linha que o funcionário pediu.
            foreach ($itens as $item) {
                // Recuperamos o que o Almoxarife digitou de liberação na caixinha de input HTML.
                $entregue = isset($qtd_entregue[$item['id']]) ? (int)$qtd_entregue[$item['id']] : 0;
                $motivo = isset($motivo_parcial[$item['id']]) ? trim($motivo_parcial[$item['id']]) : null;
                
                // Validação 1: Evitando Milagres. Não podemos entregar mais borrarchas do que temos registrados no almoxaridado:
                if ($entregue > $item['estoque_atual']) {
                    throw new Exception("Erro fatal: A entrega programada para o produto '{$item['nome']}' excede o estoque físico atual ({$item['estoque_atual']} unidades disponíveis).");
                }
                
                // Validação 2: Exigência Funcional. Entregar menos do que o usuário pediu demanda que motivemos "O porquê de entregar menos" neste item.
                if ($entregue > 0 && $entregue < $item['qtd_pedida'] && empty($motivo)) {
                    throw new Exception("O produto '{$item['nome']}' está sendo entregue de forma parcial (Apenas $entregue de {$item['qtd_pedida']}). Preencha a linha do Motivo Parcial indicando a restrição de uso.");
                }

                // Automação: Se digitarem "Zero entregas", já gera a Justificativa padrão economizando digitação do almoxarife.
                if ($entregue === 0) {
                     $motivo = "Cancelado por falta de estoque emergencial ou bloqueio da chefia.";
                }

                // INSERÇÃO: Atualiza a linha desse material gravando quantos entregou de fato e seu motivo.
                $stmtUpdateItem = $pdo->prepare("UPDATE itens_requisicao SET qtd_entregue = :entregue, motivo_parcial = :motivo WHERE id = :id");
                $stmtUpdateItem->execute([':entregue' => $entregue, ':motivo' => $motivo, ':id' => $item['id']]);

                // SAÍDA DE ESTOQUE MATEMÁTICA: O Estoque passará a ser Ele Mesmo menos a quantidade que deixará o prédio.
                if ($entregue > 0) {
                    $stmtUpdateEstoque = $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual - :entregue WHERE id = :id_produto");
                    $stmtUpdateEstoque->execute([':entregue' => $entregue, ':id_produto' => $item['id_produto']]);
                }
            }

            // Após verificar todos os itens, marca a folha Cabaçealho como "Atendido" finalizando o Loop Funcional do sistema principal.
            $stmt = $pdo->prepare("UPDATE requisicoes SET status = 'Atendido' WHERE id = :id");
            $stmt->execute([':id' => $id_requisicao]);
            $sucesso = "Tudo pronto! O estoque foi atualizado e o funcionário já pode retirar os itens.";
        }

        // CONFIRMA A TRANSAÇÃO AO BANCO, SAINDO DA "MEMÓRIA VIRTUAL" E SALVANDO EM DISCO.
        $pdo->commit();
        header("refresh:4;url=dashboard.php");

    } catch (Exception $e) {
        // Se qualque Throw New Exception da linha de código acima for atingido... Caímos aqui. 
        // Desfaz a transaction inteira antes que os saldos de estoque fiquem pela metade na falha.
        $pdo->rollBack();
        $erro = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Pedido - #<?= htmlspecialchars($id_requisicao) ?></title>
    
    <!-- FRAMEWORKS DE PADRÃO UNIVERSAL PARA ESTETICA WEB -->
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
        
        .nav-pills .nav-link { border-radius: 12px; padding: 0.8rem 1.5rem; font-weight: 700; color: #64748b; background: #f1f5f9; margin-right: 0.5rem; transition: all 0.2s; }
        .nav-pills .nav-link.active { background: #0ea5e9; color: white; }
        .nav-pills .nav-link.active.bg-danger { background: #ef4444 !important; }
        
        .table-custom { border-collapse: separate; border-spacing: 0 8px; }
        .table-custom tbody tr { background: white; border-radius: 12px; transition: all 0.2s ease; }
        .table-custom td { border: none !important; padding: 1rem; vertical-align: middle; }
        .table-custom thead th { border: none; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; }
        
        .form-control { border-radius: 12px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; background: #f8fafc; }
        
        @media print { .no-print { display: none !important; } body { background: #fff !important; } } 
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Análise de Pedido</h4>
            <p class="text-muted small mb-0">Requisição #<?= str_pad(htmlspecialchars($id_requisicao), 5, "0", STR_PAD_LEFT) ?></p>
        </div>
    </div>

    <?php if ($erro): ?> 
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($erro) ?></div> 
    <?php endif; ?>
    
    <?php if ($sucesso): ?>
        <div class="glass-box text-center py-5">
            <div class="bg-success bg-opacity-10 text-success p-4 rounded-circle d-inline-block mb-4">
                <i class="bi bi-check-lg display-4"></i>
            </div>
            <h3 class="fw-bold mb-2">Processamento Concluído!</h3>
            <p class="text-muted mb-4"><?= htmlspecialchars($sucesso) ?></p>
            <div class="spinner-border text-success" role="status"></div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="glass-box">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Solicitante</h6>
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                            <i class="bi bi-person fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($requisicao['solicitante']) ?></h5>
                            <span class="badge bg-warning text-dark rounded-pill mt-1">Pendente</span>
                        </div>
                    </div>
                    <p class="small text-muted mb-1"><i class="bi bi-calendar me-1"></i> Data: <?= date('d/m/Y', strtotime($requisicao['data_pedido'])) ?></p>
                    <p class="small text-muted mb-0"><i class="bi bi-clock me-1"></i> Hora: <?= date('H:i', strtotime($requisicao['data_pedido'])) ?></p>
                </div>
            </div>
            
            <div class="col-lg-8">
                <form method="POST" action="processar_pedido.php?id=<?= htmlspecialchars($id_requisicao) ?>">
                    <ul class="nav nav-pills mb-3 no-print" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#atender" type="button" onclick="document.getElementById('acao').value='atender'">Aprovar / Ajustar</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link bg-danger bg-opacity-10 text-danger" data-bs-toggle="pill" data-bs-target="#negar" type="button" onclick="document.getElementById('acao').value='negar'">Negar Tudo</button>
                        </li>
                    </ul>
                    <input type="hidden" name="acao" id="acao" value="atender">

                    <div class="tab-content">
                        <!-- ABA ATENDER -->
                        <div class="tab-pane fade show active" id="atender">
                            <div class="glass-box">
                                <div class="table-responsive">
                                    <table class="table table-custom mb-0">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th class="text-center">Pedido</th>
                                                <th class="text-center">Estoque</th>
                                                <th class="text-center" width="100">Liberar</th>
                                                <th class="text-center">Motivo (se parcial)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($itens as $item): ?>
                                            <tr>
                                                <td><b class="text-dark"><?= htmlspecialchars($item['nome']) ?></b></td>
                                                <td class="text-center text-muted fw-bold"><?= $item['qtd_pedida'] ?></td>
                                                <td class="text-center text-primary fw-bold"><?= $item['estoque_atual'] ?></td>
                                                <td>
                                                    <input type="number" name="qtd_entregue[<?= $item['id'] ?>]" 
                                                           class="form-control text-center fw-bold text-success border-success border-opacity-25" 
                                                           min="0" max="<?= $item['estoque_atual'] ?>" 
                                                           value="<?= min($item['qtd_pedida'], $item['estoque_atual']) ?>">
                                                </td>
                                                <td>
                                                    <input type="text" name="motivo_parcial[<?= $item['id'] ?>]" 
                                                           class="form-control form-control-sm" 
                                                           placeholder="Ex: Falta estoque">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary py-3 rounded-4 fw-bold shadow-sm">Confirmar Entrega</button>
                                </div>
                            </div>
                        </div>

                        <!-- ABA NEGAR -->
                        <div class="tab-pane fade" id="negar">
                            <div class="glass-box border-start border-danger border-4">
                                <h5 class="fw-bold text-danger mb-3">Motivo da Recusa</h5>
                                <textarea name="justificativa" class="form-control" rows="4" placeholder="Explique por que o pedido foi negado..."></textarea>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-danger py-3 rounded-4 fw-bold shadow-sm">Recusar Pedido</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
