<?php
/**
 * ARQUIVO: admin_produtos.php
 * RESPONSABILIDADE: Gerenciar o catálogo mestre de produtos. 
 * E, conforme exigência do usuário, permitir registrar Entradas (Lote/Qtd/Validade)
 * diretamente no ato de cadastrar um material novo.
 */
session_start();
require_once 'conexao.php';

// Controle de Nível: Acesso restrito
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['nivel_acesso'], ['almoxarife', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = '';
$sucesso = '';

/**
 * =========================================================================
 * PROCESSAMENTO DE INSERÇÃO/EDIÇÃO DO CATÁLOGO E ENTRADAS EMBUTIDAS
 * =========================================================================
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_produto'])) {
    
    // Tratamos as variáveis de texto com trim() para evitar espaços falsos
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $estoque_minimo = (int)$_POST['estoque_minimo'];
    
    // Variáveis da Parte de Entrada (Opconais ao Cadastrar)
    $qtd_entrada = isset($_POST['qtd_entrada']) ? (int)$_POST['qtd_entrada'] : 0;
    $lote_entrada = trim($_POST['lote_entrada'] ?? '');
    $validade_entrada = trim($_POST['validade_entrada'] ?? '');
    
    if (empty($nome)) {
        $erro = "Validação falhou: O nome identificador do material é obrigatório.";
    } else {
        try {
            // Utilizando transação, pois se a pessoa quiser 'Cadastrar' E já 'Dar Entrada'
            // Ocorrerão inserções em 2 tabelas diferentes (produtos e entradas).
            $pdo->beginTransaction();

            if ($_POST['acao_produto'] == 'novo') {
                
                // 1. Cria o Produto Zerado no sistema principal
                $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, estoque_atual, estoque_minimo) VALUES (:nome, :descricao, 0, :minimo)");
                $stmt->execute([':nome' => $nome, ':descricao' => $descricao, ':minimo' => $estoque_minimo]);
                
                // Pega o ID dele acabou de ser salvo na Tabela Produtos
                $id_novo_produto = $pdo->lastInsertId();
                $sucesso = "🎉 Catálogo atualizado: '$nome' inserido com êxito.";

                // 2. Se o Almoxarife preencheu quantidade > 0 no form misto, já efetuamos a Entrada dele imediatamente!
                if ($qtd_entrada > 0) {
                    
                    // a) Registra no Livro de Movimentações (Entradas)
                    $stmtEntrada = $pdo->prepare("INSERT INTO entradas (id_produto, quantidade, lote, validade, id_usuario) VALUES (:id_produto, :quantidade, :lote, :validade, :id_usuario)");
                    $stmtEntrada->execute([
                        ':id_produto' => $id_novo_produto,
                        ':quantidade' => $qtd_entrada,
                        ':lote'       => empty($lote_entrada) ? null : $lote_entrada,
                        ':validade'   => empty($validade_entrada) ? null : $validade_entrada,
                        ':id_usuario' => $_SESSION['usuario_id']
                    ]);
                    
                    // b) Aumenta o próprio estoque_atual da tabela-mãe (Produtos)
                    $stmtUpdateEstoque = $pdo->prepare("UPDATE produtos SET estoque_atual = estoque_atual + :quantidade WHERE id = :id");
                    $stmtUpdateEstoque->execute([':quantidade' => $qtd_entrada, ':id' => $id_novo_produto]);
                    
                    $sucesso .= "<br>📦 Plus: Uma entrada inicial de <b>$qtd_entrada unidades</b> foi creditada ao sistema logístico!";
                }

            } elseif ($_POST['acao_produto'] == 'editar') {
                
                // Na Edição focamos puramente em renomeação, limite minimo e descritivo. 
                // Entrada retroativa não é permitida por aqui para não bugar fluxos contábeis.
                $id = (int)$_POST['id_produto'];
                $stmt = $pdo->prepare("UPDATE produtos SET nome = :nome, descricao = :descricao, estoque_minimo = :minimo WHERE id = :id");
                $stmt->execute([':nome' => $nome, ':descricao' => $descricao, ':minimo' => $estoque_minimo, ':id' => $id]);
                
                $sucesso = "As especificações de '$nome' foram retificadas com sucesso.";
            }

            // Confirma todas as queries acima salvando fisicamente
            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Ocorreu uma falha sistêmica ao gravar no Banco de Dados: " . $e->getMessage();
        }
    }
}

// Traz todo o Catálogo Ativo
$stmt = $pdo->query("SELECT * FROM produtos ORDER BY nome ASC");
$produtos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo e Cargas - Controle de Estoque</title>
    <!-- CSS Bootstrap Elegante e Responsivo -->
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
        
        /* Tabela Zebrada Moderna */
        .table-custom { border-collapse: separate; border-spacing: 0 12px; }
        .table-custom tbody tr { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border-radius: 12px; transition: all 0.2s ease; }
        .table-custom tbody tr:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .table-custom td { border: none !important; padding: 1.25rem; vertical-align: middle; }
        .table-custom td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .table-custom td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        .table-custom thead th { border: none; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; padding-bottom: 0.5rem; }
        
        /* MODAL */
        .modal-content { border-radius: 24px; border: none; }
        .form-control { border-radius: 12px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; background: #f8fafc; }
        
        .btn-add {
            background: #0ea5e9;
            color: white;
            border-radius: 15px;
            padding: 0.8rem 1.5rem;
            font-weight: 700;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-add:hover { background: #0284c7; transform: scale(1.05); color: white; }
        .btn-add:hover { background: #0284c7; transform: scale(1.05); color: white; }

        /* REGRAS DE IMPRESSÃO */
        @media print {
            .no-print, nav, .btn, .navbar, #navBarMenu, .alert-info, .alert-danger, .alert-success { display: none !important; }
            body { background: white !important; font-size: 10pt; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .glass-box { box-shadow: none !important; border: none !important; padding: 0 !important; }
            .table { width: 100% !important; border-collapse: collapse !important; }
            .table-custom tr { background: transparent !important; border-bottom: 1px solid #000 !important; transform: none !important; box-shadow: none !important; }
            .table th, .table td { border: 1px solid #000 !important; padding: 8px !important; color: #000 !important; vertical-align: middle !important; }
            .table thead th { background-color: #f0f0f0 !important; border: 1px solid #000 !important; font-weight: bold !important; color: #000 !important; text-transform: uppercase; }
            .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; }
            .d-print-block { display: block !important; }
            .text-primary, .text-muted, .text-dark { color: #000 !important; }
            .bg-light { background-color: transparent !important; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    
    <!-- CABEÇALHO PARA IMPRESSÃO (Só aparece no Papel) -->
    <div class="d-none d-print-block text-center mb-4 mt-2">
        <h3 class="fw-bold">CATÁLOGO GERAL DE PRODUTOS</h3>
        <p class="text-muted small">Prefeitura Municipal de São Carlos - Emprego e Renda</p>
        <hr class="border-2 border-dark opacity-100">
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="fw-bold text-dark mb-1">Produtos (Fichas)</h4>
            <p class="text-muted small mb-0">Gerencie o cadastro de materiais do almoxarifado</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-light border text-secondary fw-bold px-3 rounded-pill shadow-sm">
                <i class="bi bi-printer me-2"></i> Imprimir Catálogo
            </button>
            <button type="button" class="btn btn-add shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProduto" onclick="resetForm()">
                <i class="bi bi-plus-lg me-2"></i> Novo Tipo de Produto
            </button>
        </div>
    </div>

    <!-- AVISO DE ORIENTAÇÃO PARA O ALMOXARIFE -->
    <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center no-print">
        <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
        <div>
            <h6 class="fw-bold mb-1">Atenção Almoxarife!</h6>
            <p class="mb-0 small text-dark">Esta tela serve apenas para cadastrar <b>novos itens</b> no catálogo. Se você já tem o produto cadastrado e quer apenas <b>aumentar a quantidade</b> em estoque, use a página de <a href="entradas.php" class="fw-bold text-primary">Entradas</a>.</p>
        </div>
    </div>

    <?php if ($erro): ?> 
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><i class="bi bi-x-octagon-fill me-2"></i><?= htmlspecialchars($erro) ?></div> 
    <?php endif; ?>
    
    <?php if ($sucesso): ?> 
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= $sucesso ?></div> 
    <?php endif; ?>

    <div class="glass-box">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th width="50%">Produto</th>
                        <th width="15%" class="text-center">Estoque</th>
                        <th width="15%" class="text-center">Aviso Mínimo</th>
                        <th width="20%" class="text-center">Status</th>
                        <th width="15%" class="text-end no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                        <?php 
                            $isAlert = ($p['estoque_atual'] <= $p['estoque_minimo']);
                            $statusBadge = $isAlert ? '<span class="badge bg-danger rounded-pill">Baixo</span>' : '<span class="badge bg-success rounded-pill">OK</span>';
                            if ($p['estoque_atual'] == 0) $statusBadge = '<span class="badge bg-secondary rounded-pill">Vazio</span>';
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light p-3 rounded-4 me-3 text-primary"><i class="bi bi-box fs-5"></i></div>
                                    <div>
                                        <b class="text-dark d-block"><?= htmlspecialchars($p['nome']) ?></b>
                                        <span class="small text-muted"><?= htmlspecialchars($p['descricao']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center fw-bold fs-5 text-dark"><?= $p['estoque_atual'] ?></td>
                            <td class="text-center text-muted"><?= $p['estoque_minimo'] ?></td>
                            <td class="text-center"><?= $statusBadge ?></td>
                            <td class="text-end no-print">
                                <button onclick="editar(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nome'])) ?>', '<?= htmlspecialchars(addslashes($p['descricao'])) ?>', <?= $p['estoque_minimo'] ?>)" 
                                        class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-primary shadow-sm">
                                    <i class="bi bi-pencil me-1"></i> Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if(count($produtos) === 0): ?>
                <div class="text-center p-5">
                    <i class="bi bi-inbox text-muted display-1 mb-3 opacity-25 d-block"></i>
                    <h5 class="text-muted fw-bold">Nenhum produto cadastrado.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL DE CADASTRO / EDIÇÃO -->
<div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content overflow-hidden">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-primary mb-0" id="formTitle">Novo Produto</h5>
                    <small class="text-muted" id="formSubtitle">Cadastrar novo item no sistema</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin_produtos.php" id="formProduto">
                    <input type="hidden" name="acao_produto" id="acao_produto" value="novo">
                    <input type="hidden" name="id_produto" id="id_produto" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nome do Produto</label>
                        <input type="text" name="nome" id="nome_input" class="form-control" required placeholder="Ex: Caneta Azul">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Descrição / Marca</label>
                        <textarea name="descricao" id="desc_input" class="form-control" rows="2" placeholder="Ex: Bic ponta grossa"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small">Aviso de Estoque Baixo (Qtd)</label>
                        <input type="number" name="estoque_minimo" id="min_input" class="form-control fw-bold text-center" value="10" min="0">
                        <div class="form-text small">O sistema avisa quando chegar nesta quantidade.</div>
                    </div>

                    <!-- ESTOQUE INICIAL (Apenas no Novo) -->
                    <div id="boxEntradaInteligente" class="bg-light p-3 rounded-4 border mb-4">
                        <h6 class="fw-bold text-success mb-2 small"><i class="bi bi-plus-square me-1"></i> Estoque Inicial (Opcional)</h6>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Quantidade Já em Mãos</label>
                                <input type="number" name="qtd_entrada" id="qtd_entrada" class="form-control text-center fw-bold text-success" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-3 rounded-4 fw-bold shadow-sm" id="btnSalvar">Salvar Cadastro</button>
                        <button type="button" class="btn btn-light py-2 rounded-4 fw-bold text-muted" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Inicializa o modal somente após o carregamento do Bootstrap
    let modalProduto;
    document.addEventListener('DOMContentLoaded', function() {
        modalProduto = new bootstrap.Modal(document.getElementById('modalProduto'));
    });

    function editar(id, nome, desc, min) {
        try {
            document.getElementById('formTitle').innerText = 'Editar Produto';
            document.getElementById('formSubtitle').innerText = 'Modificando: ' + nome;
            document.getElementById('acao_produto').value = 'editar';
            document.getElementById('id_produto').value = id;
            document.getElementById('nome_input').value = nome;
            document.getElementById('desc_input').value = desc;
            document.getElementById('min_input').value = min;
            
            // Oculta área de entrada inicial na edição
            document.getElementById('boxEntradaInteligente').classList.add('d-none');
            
            document.getElementById('btnSalvar').innerText = 'Salvar Alterações';
            document.getElementById('btnSalvar').classList.replace('btn-primary', 'btn-warning');
            
            if (modalProduto) {
                modalProduto.show();
            } else {
                // Fallback caso o DOMContentLoaded ainda não tenha disparado
                modalProduto = new bootstrap.Modal(document.getElementById('modalProduto'));
                modalProduto.show();
            }
        } catch (e) {
            console.error(e);
            alert('Erro ao abrir o editor de produtos: ' + e.message);
        }
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Novo Produto';
        document.getElementById('formSubtitle').innerText = 'Criar ficha técnica';
        document.getElementById('acao_produto').value = 'novo';
        document.getElementById('id_produto').value = '';
        document.getElementById('nome_input').value = '';
        document.getElementById('desc_input').value = '';
        document.getElementById('min_input').value = '10';
        
        document.getElementById('boxEntradaInteligente').classList.remove('d-none');
        document.getElementById('btnSalvar').innerText = 'Salvar Cadastro';
        document.getElementById('btnSalvar').classList.replace('btn-warning', 'btn-primary');
        
        document.getElementById('qtd_entrada').value = '0';
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>
