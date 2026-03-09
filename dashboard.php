<?php
/**
 * ARQUIVO: dashboard.php
 * PAINEL DE CONTROLE DO SISTEMA
 * 
 * Este arquivo é o "coração" da aplicação. Dependendo de quem fizer login 
 * (Comum, Almoxarife ou Admin), a interface se adapta para mostrar menus 
 * diferentes, botões diferentes e dados diferentes. 
 * Ocultar informações de quem não tem permissão é a base da segurança!
 */

// 1. Precisamos da sessão pois o usuário já logou lá no index.php
session_start();

// 2. Trazemos a conexão com o banco
require_once 'conexao.php';

// 3. SEGURANÇA: Bloqueia acesso de curiosos que não estão logados.
// Se a variável 'usuario_id' não existir na Sessão, joga a pessoa de volta pro Login (index)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// 4. ATRIBUIÇÕES PRÁTICAS:
// Criamos variáveis booleanas (true ou false) baseadas no nível de acesso da pessoa logada.
// Isso evita que fiquemos digitando "if ($_SESSION['nivel...'] == '...')" no meio do TML
$nivel_acesso = $_SESSION['nivel_acesso'];
$is_almoxarife = ($nivel_acesso === 'almoxarife' || $nivel_acesso === 'admin'); // O admin herda todos os poderes do almoxarife
$is_admin = ($nivel_acesso === 'admin'); // Somente Admin tem isso true

// =========================================================================
// BLOCO A - LÓGICA DO ALMOXARIFE E ADMIN (MÉTRICAS E ALERTS DA LOGÍSTICA)
// =========================================================================
$alertas_estoque = [];
$total_entradas = 0;
$total_pendentes = 0;

if ($is_almoxarife) {
    // A.1: Buscar produtos cujo 'estoque_atual' esteja menor ou igual ao 'estoque_minimo'
    // Ideal para sabermos quais itens precisam ser comprados urgentemente.
    $stmt = $pdo->query("SELECT nome, estoque_atual, estoque_minimo FROM produtos WHERE estoque_atual <= estoque_minimo");
    $alertas_estoque = $stmt->fetchAll();

    // A.2: Definindo as datas do mês e ano que estamos rodando o sistema agora
    $mes_atual = date('m');
    $ano_atual = date('Y');

    // A.3: Qual foi o Total de Entradas (qtd) neste mês? 
    // Usamos SUM() para somar uma coluna e MONTH/YEAR diretamente na query SQL
    $stmt = $pdo->prepare("SELECT SUM(quantidade) as total FROM entradas WHERE MONTH(data_entrada) = :mes AND YEAR(data_entrada) = :ano");
    $stmt->execute([':mes' => $mes_atual, ':ano' => $ano_atual]);
    $result = $stmt->fetch();
    $total_entradas = $result['total'] ?? 0; // Se o Total for nulo (ninguém enviou nada), vira Zero.

    // A.4: Quantos pedidos a escola fez que ninguém do Almoxarifado analisou ainda?
    // Usamos COUNT(*) para apenas "contar as linhas", é mais rápido no SQL
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM requisicoes WHERE status = 'Pendente'");
    $total_pendentes = $stmt->fetch()['total'] ?? 0;
}

// =========================================================================
// BLOCO B - BUSCAR A LISTA DE PEDIDOS EXISTENTES NO BANCO
// =========================================================================
if ($is_almoxarife) {
    // Almoxarifes vêm os pedidos "Pendentes" de todas as pessoas, ordenados do mais velho para o mais novo
    // Utilizamos o JOIN para puxar o Nome do funcionário com base no ID dele gravado na requisição.
    $stmt = $pdo->query("SELECT r.id, u.nome as solicitante, r.data_pedido, r.status 
                         FROM requisicoes r 
                         JOIN usuarios u ON r.id_usuario = u.id 
                         WHERE r.status = 'Pendente' 
                         ORDER BY r.data_pedido ASC");
    $requisicoes = $stmt->fetchAll();
} else {
    // Servidor / Funcionário comum 
    // Vê APENAS os seus próprios pedidos (histórico inteiro), do mais recente para o mais antigo (DESC).
    $stmt = $pdo->prepare("SELECT id, data_pedido, status, justificativa 
                           FROM requisicoes 
                           WHERE id_usuario = :id_usuario 
                           ORDER BY data_pedido DESC");
    // Passamos a variável de segurança $_SESSION['usuario_id'] evitando que ele espione o ID dos outros.
    $stmt->execute([':id_usuario' => $_SESSION['usuario_id']]);
    $requisicoes = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    
    <!-- TAG VIEWPORT PARA CELULAR: Adquiriendo as proporções exatas do aparelho -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle de Estoque</title>
    
    <!-- BOOTSTRAP 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ÍCONES BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        /* FONTE E FUNDO MODERNOS */
        body { background-color: #f0f2f5; font-family: 'Outfit', 'Inter', sans-serif; color: #334155; }

        .navbar { background: #ffffff !important; border-bottom: 1px solid #e2e8f0; }
        .navbar-brand { color: #0f172a !important; font-weight: 800; }
        .nav-link { color: #64748b !important; font-weight: 600; }
        .nav-link.active { color: #0ea5e9 !important; }

        /* CARDS PRINCIPAIS */
        .dash-card {
            background: #ffffff;
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.5rem;
            height: 100%;
        }
        
        .dash-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .icon-box {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 2rem;
        }

        .bg-card-blue { background: #e0f2fe; color: #0ea5e9; }
        .bg-card-green { background: #dcfce7; color: #22c55e; }
        .bg-card-purple { background: #f3e8ff; color: #a855f7; }
        .bg-card-orange { background: #ffedd5; color: #f97316; }

        .card-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
        .card-desc { font-size: 0.875rem; color: #64748b; text-align: center; }

        /* ALERTAS */
        .alert-pill {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 15px;
            padding: 1.5rem;
        }

        /* MODAIS CUSTOM */
        .modal-content { border-radius: 24px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 1.5rem 2rem; }
        .modal-body { padding: 2rem; }
        .form-control { border-radius: 12px; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; background: #f8fafc; }
        .form-control:focus { border-color: #0ea5e9; box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1); }

        @media (max-width: 768px) {
            .navbar-brand span { display: none; }
        }

        /* REGRAS DE IMPRESSÃO - Focar na Tabela de Requisições */
        @media print {
            .no-print, .dash-card, .alert-pill, .navbar, .icon-box, .btn { display: none !important; }
            body { background: white !important; font-size: 10pt; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            .card-header { border-bottom: 2px solid #000 !important; padding: 0 0 10px 0 !important; }
            .table { width: 100% !important; border: 1px solid #000 !important; }
            .table-custom tr { background: transparent !important; border-bottom: 1px solid #000 !important; }
            .table th, .table td { border: 1px solid #000 !important; padding: 5px !important; color: #000 !important; }
            .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; }
            .d-print-block { display: block !important; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    </style>
</head>
<body>

<!-- BARRA DE NAVEGAÇÃO BRANCA E LIMPA -->
<?php include 'header.php'; ?>

<main class="container mb-5" style="max-width: 1100px;">
    
    <!-- CABEÇALHO PARA IMPRESSÃO -->
    <div class="d-none d-print-block text-center mb-4 mt-2">
        <h3 class="fw-bold">RELAÇÃO DE REQUISIÇÕES PENDENTES</h3>
        <p class="text-muted small">Prefeitura Municipal de São Carlos - Emprego e Renda</p>
        <hr class="border-2 border-dark opacity-100">
    </div>
    
    <!-- ÁREA DE BOAS VINDAS -->
    <div class="row align-items-center mb-5 no-print">
        <div class="col-12 text-center text-lg-start d-lg-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-dark mb-0">Olá, <?= explode(' ', $_SESSION['usuario_nome'])[0] ?>! 👋</h2>
                <p class="text-muted mb-0">Bem-vindo ao painel de controle do estoque.</p>
            </div>
            <div class="mt-3 mt-lg-0">
                 <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill border fw-bold">
                    <i class="bi bi-shield-check me-1"></i> Perfil: <?= ucfirst($_SESSION['nivel_acesso']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ALERTAS CRÍTICOS -->
    <?php if ($is_almoxarife && count($alertas_estoque) > 0): ?>
        <div class="alert alert-pill mb-5 no-print shadow-sm">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-danger text-white p-2 rounded-circle me-3">
                    <i class="bi bi-bell-fill fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0">Estoque Baixo</h5>
                    <p class="mb-0 small opacity-75">Os itens abaixo precisam de reposição urgente.</p>
                </div>
            </div>
            <div class="row g-2">
                <?php foreach ($alertas_estoque as $alerta): ?>
                    <div class="col-sm-6 col-md-3">
                        <div class="bg-white p-2 px-3 rounded-pill border d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-dark"><?= htmlspecialchars($alerta['nome']) ?></span>
                            <span class="badge bg-danger rounded-pill"><?= $alerta['estoque_atual'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>


    <!-- GRID DE OPÇÕES (Cards Grandes) -->
    <div class="row g-4 mb-5">
        
        <?php if ($is_almoxarife): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="admin_produtos.php" class="dash-card">
                    <div class="icon-box bg-card-blue">
                        <i class="bi bi-plus-circle"></i>
                    </div>
                    <span class="card-title text-center">Produtos</span>
                    <span class="card-desc">Gerenciar itens</span>
                </a>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <a href="entradas.php" class="dash-card">
                    <div class="icon-box bg-card-green">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <span class="card-title text-center">Entradas</span>
                    <span class="card-desc">Adicionar estoque</span>
                </a>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <a href="relatorios.php" class="dash-card">
                    <div class="icon-box bg-card-purple">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <span class="card-title text-center">Relatórios</span>
                    <span class="card-desc">Ver histórico</span>
                </a>
            </div>

            <?php if ($is_admin): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="admin_usuarios.php" class="dash-card">
                        <div class="icon-box bg-card-orange">
                            <i class="bi bi-people"></i>
                        </div>
                        <span class="card-title text-center">Usuários</span>
                        <span class="card-desc">Configurações</span>
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- SE FOR SOLICITANTE -->
            <div class="col-12 col-md-6">
                <a href="nova_requisicao.php" class="dash-card">
                    <div class="icon-box bg-card-blue">
                        <i class="bi bi-cart-plus"></i>
                    </div>
                    <span class="card-title text-center">Fazer Pedido</span>
                    <span class="card-desc">Solicitar materiais ao estoque</span>
                </a>
            </div>
            <div class="col-12 col-md-6">
                <a href="meus_pedidos.php" class="dash-card">
                    <div class="icon-box bg-card-orange">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <span class="card-title text-center">Meus Pedidos</span>
                    <span class="card-desc">Total: <?= count($requisicoes) ?> solicitados</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TABELA CENTRAL DE REQUISIÇÕES (Design Premium) -->
    <div class="card bg-white shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        
        <div class="card-header bg-white d-flex justify-content-between align-items-center p-4 border-bottom">
            <div>
                <h5 class="mb-1 text-dark fw-bold">
                    <i class="bi bi-inboxes text-primary me-2"></i> 
                    <?= $is_almoxarife ? 'Requisições Pendentes' : 'Meu Histórico de Solicitações' ?>
                </h5>
                <p class="mb-0 text-muted small">
                    <?= $is_almoxarife ? 'Pedidos aguardando sua separação.' : 'Acompanhe o status dos pedidos que você enviou ao almoxarifado.' ?>
                </p>
            </div>
            
            <div class="no-print">
                <?php if ($is_almoxarife): ?>
                    <button onclick="window.print()" class="btn btn-light border text-secondary fw-bold px-4 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-printer me-2"></i> Imprimir Relação
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (count($requisicoes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead class="bg-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3 fw-bold border-0">Nº Recibo</th>
                                <?php if ($is_almoxarife): ?><th class="py-3 fw-bold border-0">Requerente</th><?php endif; ?>
                                <th class="py-3 fw-bold border-0">Lançamento</th>
                                <th class="py-3 fw-bold border-0 text-center">Status Físico</th>
                                <th class="no-print text-center pe-4 py-3 fw-bold border-0">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requisicoes as $req): ?>
                                <tr>
                                    <td class="ps-4 text-muted fw-bold">#<?= str_pad($req['id'], 5, "0", STR_PAD_LEFT) ?></td>
                                    
                                    <?php if ($is_almoxarife): ?>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($req['solicitante']) ?></td>
                                    <?php endif; ?>
                                    
                                    <td class="text-muted">
                                        <i class="bi bi-calendar2 ms-1 me-2"></i><?= date('d/m/Y', strtotime($req['data_pedido'])) ?>
                                        <span class="ms-1 small opacity-75"><?= date('H:i', strtotime($req['data_pedido'])) ?></span>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php
                                            $badge_class = 'bg-secondary bg-opacity-10 text-secondary border border-secondary';
                                            if ($req['status'] === 'Pendente') $badge_class = 'bg-warning bg-opacity-10 text-dark border border-warning';
                                            elseif ($req['status'] === 'Atendido') $badge_class = 'bg-success bg-opacity-10 text-success border border-success';
                                            elseif ($req['status'] === 'Negado') $badge_class = 'bg-danger bg-opacity-10 text-danger border border-danger';
                                        ?>
                                        <span class="badge <?= $badge_class ?> rounded-pill px-3 py-2 fw-bold w-75"><?= $req['status'] ?></span>
                                    </td>
                                    
                                    <td class="no-print text-center pe-4">
                                        <?php if ($is_almoxarife && $req['status'] === 'Pendente'): ?>
                                            <a href="processar_pedido.php?id=<?= $req['id'] ?>" class="btn btn-primary btn-sm px-4 rounded-pill fw-bold shadow-sm">
                                                Avaliar
                                            </a>
                                        <?php else: ?>
                                            <a href="detalhes_pedido.php?id=<?= $req['id'] ?>" class="btn btn-light border btn-sm rounded-pill px-4 fw-bold text-primary shadow-sm hover-btn">
                                                Detalhes
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted p-5 my-3">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-inbox fs-1 text-muted opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Nenhum Registro Localizado</h5>
                    <p class="small">A fila de processos operacionais encontra-se zerada no momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </main>

<?php include 'footer.php'; ?>

</body>
</html>
