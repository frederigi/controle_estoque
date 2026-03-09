<?php
/**
 * ARQUIVO: nova_requisicao.php
 * RESPONSABILIDADE: Interface para os funcionários comuns (não-almoxarifes)
 * criarem e enviarem sua lista de pedidos de produtos ao setor de logística.
 */

// 1. Inicia o controle de sessão no PHP. Retoma os dados de quem logou.
session_start();

// 2. Conexão obrigatória com banco (sem ela a página interrompe a execução)
require_once 'conexao.php';

// 3. SEGURANÇA: Bloqueio de Acesso. 
// Apenas usuários logados que possuam Nível 'solicitante' podem fazer pedir material.
if (!isset($_SESSION['usuario_id']) || $_SESSION['nivel_acesso'] !== 'solicitante') {
    // Quem for admin/almoxarife será devolvido à dashboard.
    header("Location: dashboard.php");
    exit;
}

$erro = '';
$sucesso = '';

/**
 * 4. BUSCA DO CATÁLOGO DE PRODUTOS
 * Trazemos Nome e Descrição de tudo que existe no sistema.
 * NOTA EDUCACIONAL: Repare que o Solicitante NÃO vê a coluna "estoque_atual"!
 * Foi um requisito do projeto: Ele pede às cegas, o Almoxarife que resolverá na tela dele
 * se atende o pedido inteiro, parcialmente, ou se nega por falta no galpão.
 */
$stmt = $pdo->query("SELECT id, nome, descricao FROM produtos ORDER BY nome ASC");
$produtos = $stmt->fetchAll();

// 5. PROCESSAMENTO DO FORMULÁRIO QUANDO O FUNCIONÁRIO CLICA EM "ENVIAR REQUISIÇÃO"
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // O array $_POST['produtos'] guarda todos os ID de produtos atrelados à quantidade que o usuário digitou
    // O ?? [] previne erro caso a página seja enviada com todos os campos completamente nulos.
    $itens = $_POST['produtos'] ?? []; 
    
    // 6. FILTRO DE INTEGRIDADE
    // array_filter vai rodar item por item. O que tiver número maior que ZERO (0), ele guarda e aprova.
    // Isso evita processar pedidos de 0 Papel Sulfite (apenas "lixo" no banco de dados).
    $itens_validos = array_filter($itens, function($qtd) {
        return is_numeric($qtd) && $qtd > 0;
    });

    // Se sobrou algum item que de fato foi pedido uma quantidade legítima:
    if (count($itens_validos) > 0) {
        
        try {
            // 7. TRANSAÇÃO (beginTransaction)
            // Imagine que ao salvar, a internet cai no meio. 
            // O beginTransaction impede que as tabelas fiquem pela metade (Ex: Criou a requisição mas os itens não).
            $pdo->beginTransaction();

            // Passo A: Gravar a "Folha de Rosto" (A Requisição)
            $stmt = $pdo->prepare("INSERT INTO requisicoes (id_usuario, status) VALUES (:id_usuario, 'Pendente')");
            $stmt->execute([':id_usuario' => $_SESSION['usuario_id']]);
            
            // lastInsertId() pega a numeração Automática (ID) que o Banco MySQL gerou agora mesmo para a Requisição
            $id_requisicao = $pdo->lastInsertId();

            // Passo B: Gravar Produto por Produto (Os Itens) vinculando à "Folha de Rosto"
            $stmtItem = $pdo->prepare("INSERT INTO itens_requisicao (id_requisicao, id_produto, qtd_pedida) VALUES (:id_requisicao, :id_produto, :qtd_pedida)");
            
            // Para cada Produto Válido, execute um INSERT
            foreach ($itens_validos as $id_produto => $qtd_pedida) {
                $stmtItem->execute([
                    ':id_requisicao' => $id_requisicao,
                    ':id_produto' => $id_produto,
                    ':qtd_pedida' => (int)$qtd_pedida
                ]);
            }

            // Se tudo até aqui deu certo, confirmamos a Transação para ser gravada de forma definitiva. (Tudo ou Nada)
            $pdo->commit();
            
            $sucesso = "Sua requisição foi enviada com sucesso ao Controle de Estoque!";
            
            // O header refresh manda a pessoa para a dashboard após 3 segundos automaticamente
            header("refresh:3;url=dashboard.php");

        } catch (Exception $e) {
            // Se algo explodiu, o rollBack cancela todos os INSERTS que já tinham roda desde BeginTransaction.
            $pdo->rollBack();
            $erro = "Falha no sistema. Contate a TI: " . $e->getMessage();
        }
    } else {
        // Se após o filtro, não sobrou NADA, significa que a pessoa não pediu quantidade de nenhum produto.
        $erro = "Aviso: Nenhuma quantidade superior a zero foi selecionada.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário de Solicitação - Controle de Estoque</title>
    <!-- BOOTSTRAP 5 VIA CDN (Estilos Modernos base) -->
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

        /* Estilo da Busca */
        #searchWrapper { position: relative; }
        #searchResults { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); 
            z-index: 1000;
            display: none;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            margin-top: 5px;
        }
        .search-item { 
            padding: 12px 20px; 
            cursor: pointer; 
            border-bottom: 1px solid #f8fafc;
            transition: background 0.2s;
        }
        .search-item:hover { background: #f0f9ff; }
        .search-item:last-child { border-bottom: none; }

        /* Estilo do Carrinho */
        .table-cart { border-collapse: separate; border-spacing: 0 8px; }
        .table-cart tbody tr { background: white; border-radius: 12px; transition: all 0.2s ease; }
        .table-cart td { border: none !important; padding: 1rem; vertical-align: middle; }
        .table-cart thead th { border: none; font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; }
        
        .form-control { border-radius: 12px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; background: #f8fafc; }
        .form-control:focus { background: #fff; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); border-color: #0ea5e9; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'header.php'; ?>

<div class="container pb-5" style="max-width: 1100px;">
    
    <div class="text-center mb-5">
        <h3 class="fw-bold text-dark mb-2">Pedir Material</h3>
        <p class="text-muted">Busque o material pelo nome e adicione à sua lista.</p>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 text-center py-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($sucesso): ?>
        <div class="glass-box text-center py-5">
            <div class="bg-success bg-opacity-10 text-success p-4 rounded-circle d-inline-block mb-4">
                <i class="bi bi-check-lg display-4"></i>
            </div>
            <h3 class="fw-bold mb-2">Pedido Enviado!</h3>
            <p class="text-muted mb-4"><?= htmlspecialchars($sucesso) ?></p>
            <div class="spinner-border text-success" role="status"></div>
            <p class="small text-muted mt-3">Redirecionando...</p>
        </div>
    <?php else: ?>
        
        <div class="row g-4">
            <!-- Coluna de Busca -->
            <div class="col-lg-5">
                <div class="glass-box mb-4">
                    <label class="form-label fw-bold"><i class="bi bi-search me-2"></i>Buscar Produto</label>
                    <div id="searchWrapper">
                        <input type="text" id="searchInput" class="form-control" placeholder="Escreva o nome do item..." autocomplete="off">
                        <div id="searchResults" class="shadow-lg"></div>
                    </div>
                    <div class="mt-3 small text-muted">
                        <i class="bi bi-lightbulb me-1"></i> Digite pelo menos 2 letras para pesquisar.
                    </div>
                </div>
            </div>

            <!-- Coluna da Lista de Pedidos -->
            <div class="col-lg-7">
                <form id="formRequisicao" method="POST" action="nova_requisicao.php">
                    <div class="glass-box h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0">Itens no Pedido</h5>
                            <span id="contadorItens" class="badge bg-light text-primary rounded-pill px-3 border">0 itens</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-cart align-middle" id="tabelaItens">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-center" style="width: 120px;">Qtd.</th>
                                        <th class="text-end" style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cartBody">
                                    <tr id="emptyRow">
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <i class="bi bi-cart shadow-sm p-3 rounded-circle bg-light d-inline-block mb-3 fs-3"></i>
                                            <p class="mb-0">Sua lista está vazia.<br>Use a busca ao lado para adicionar itens.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 pt-4 border-top">
                            <button type="submit" id="btnEnviar" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm d-none">
                                Enviar Pedido para o Estoque <i class="bi bi-send-fill ms-2"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const cartBody = document.getElementById('cartBody');
    const emptyRow = document.getElementById('emptyRow');
    const btnEnviar = document.getElementById('btnEnviar');
    const contadorItens = document.getElementById('contadorItens');

    let debounceTimer;

    // 1. Busca via AJAX enquanto digita
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`buscar_produtos_ajax.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'search-item d-flex justify-content-between align-items-center';
                            div.innerHTML = `
                                <div>
                                    <b class="text-dark">${item.nome}</b><br>
                                    <small class="text-muted">${item.descricao || ''}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3">Adicionar</button>
                            `;
                            div.onclick = () => adicionarAoCarrinho(item);
                            searchResults.appendChild(div);
                        });
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = '<div class="p-3 text-muted">A TI não encontrou nada com esse nome.</div>';
                        searchResults.style.display = 'block';
                    }
                });
        }, 300);
    });

    // 2. Lógica de adicionar ao carrinho
    function adicionarAoCarrinho(produto) {
        // Verifica se já existe
        if (document.getElementById(`item_${produto.id}`)) {
            const input = document.getElementById(`qtd_${produto.id}`);
            input.value = parseInt(input.value) + 1;
            input.focus();
        } else {
            if (emptyRow) emptyRow.style.display = 'none';
            
            const tr = document.createElement('tr');
            tr.id = `item_${produto.id}`;
            tr.innerHTML = `
                <td>
                    <b class="text-dark">${produto.nome}</b><br>
                    <small class="text-muted">${produto.descricao || ''}</small>
                </td>
                <td class="text-center">
                    <input type="number" name="produtos[${produto.id}]" id="qtd_${produto.id}" 
                           class="form-control text-center fw-bold text-primary mx-auto" 
                           value="1" min="1" style="width: 85px;">
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="removerItem(${produto.id})">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            `;
            cartBody.appendChild(tr);
        }

        searchResults.style.display = 'none';
        searchInput.value = '';
        atualizarInterface();
    }

    // 3. Remover item
    window.removerItem = function(id) {
        const tr = document.getElementById(`item_${id}`);
        if (tr) tr.remove();
        atualizarInterface();
    };

    // 4. Atualiza UI (botões e contadores)
    function atualizarInterface() {
        const totalItems = cartBody.querySelectorAll('tr:not(#emptyRow)').length;
        contadorItens.innerText = `${totalItems} ítem(ns)`;

        if (totalItems > 0) {
            btnEnviar.classList.remove('d-none');
            if (emptyRow) emptyRow.style.display = 'none';
        } else {
            btnEnviar.classList.add('d-none');
            if (emptyRow) emptyRow.style.display = 'table-row';
        }
    }

    // Fecha a busca ao clicar fora
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

</script>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
