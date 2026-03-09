<?php
/**
 * ARQUIVO: admin_usuarios.php
 * RESPONSABILIDADE: Painel MASTER ROOT onde apenas o Administrador 
 * visualiza todos os CPFs que acessam o sistema. Permite criar novos
 * perfis atribuindo seu Nível Hierarquizadado (Roles) e Banir/Apagar funcionários.
 */
session_start();
require_once 'conexao.php';

// Controle Exclusivo de Admins
if (!isset($_SESSION['usuario_id']) || $_SESSION['nivel_acesso'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$erro = '';
$sucesso = '';

/**
 * =========================================================================
 * SUBMIT: INTERPRETADOR DE AÇÕES CADASTRAR x EXCLUIR VINDAS DO FORMHTML
 * =========================================================================
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? ''; // Verifica se o POST veio do Box Verde ou do Botão Vermelho

    // 1- CRIACAO
    if ($acao === 'salvar_usuario') {
        $nome = trim($_POST['nome']);
        $login = trim($_POST['login']);
        $senha = trim($_POST['senha']);
        $nivel = $_POST['nivel_acesso'];

        if (!empty($nome) && !empty($login) && !empty($nivel) && !empty($senha)) {
            try {
                // Prevenção de BUG: Na estrutura, Logins são Únicos (Unique). Alguém pode usar joao.vitor2 se existir o joao.vitor
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE login = :login");
                $stmt->execute([':login' => $login]);
                if ($stmt->rowCount() > 0) {
                    $erro = "Falha Cadastral: O Username '$login' já pertence à outra pessoa na instituição.";
                } else {
                    // Segurança Clássica e Educacional PHP: Criptografa MD5 (Ponteiro de Mão Única) para que nem Hackers saibam a senha literal
                    $senha_hash = md5($senha);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, nivel_acesso) VALUES (:nome, :login, :senha, :nivel)");
                    $stmt->execute([':nome' => $nome, ':login' => $login, ':senha' => $senha_hash, ':nivel' => $nivel]);
                    $sucesso = "Usuário cadastrado com sucesso! '$nome' tem acesso de " . strtoupper($nivel) . ".";
                }
            } catch (Exception $e) {
                $erro = "Erro ao inserir Usuário no banco: " . $e->getMessage();
            }
        } else {
            $erro = "Atenção: É necessário preencher todos os campos do formulário.";
        }
    }
    // 2- EDIÇÃO
    elseif ($acao === 'editar_usuario') {
        $id_edit = $_POST['id_usuario'];
        $nome = trim($_POST['nome']);
        $nivel = $_POST['nivel_acesso'];
        $senha = trim($_POST['senha']);

        if (!empty($id_edit) && !empty($nome) && !empty($nivel)) {
            try {
                if (!empty($senha)) {
                    // Criptografa a nova senha se fornecida
                    $senha_hash = md5($senha);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = :nome, nivel_acesso = :nivel, senha = :senha WHERE id = :id");
                    $stmt->execute([':nome' => $nome, ':nivel' => $nivel, ':senha' => $senha_hash, ':id' => $id_edit]);
                } else {
                    // Mantém a senha atual
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = :nome, nivel_acesso = :nivel WHERE id = :id");
                    $stmt->execute([':nome' => $nome, ':nivel' => $nivel, ':id' => $id_edit]);
                }
                $sucesso = "Dados de '$nome' atualizados com sucesso!";
            } catch (Exception $e) {
                $erro = "Erro ao atualizar usuário: " . $e->getMessage();
            }
        } else {
            $erro = "Preencha os campos obrigatórios para editar.";
        }
    }
    // 3- BANIMENTO (Exclusão Cascata)
    elseif ($acao === 'excluir_usuario') {
        $id_del = $_POST['id_usuario'];

        // Bloqueio de Suícidio Virtual: Impedir que o Admin que está logado se delete usando o proprio mouse.
        if ($id_del == $_SESSION['usuario_id']) {
            $erro = "Bloqueio de Segurança: Você não tem pode excluir o próprio usuário que está logado.";
        } else {
            try {
                // BANIMENTO EM CASCATA EXPLICADO:
                // Se o funcionário "Pedro" que pediu 5 borrachas for Deletado, e depois formos olhar a requisição 12 dele, 
                // o sistema ia quebrar pois o BD não vai achar o "ID do Pedro".
                // Logo, deletamos TUDO que tem conexão com o ID dele pra manter o sistema limpo.
                $pdo->beginTransaction();

                // Passo A: Encontra Todas Requisições dele e deleta os itens de Cada uma Delas (Filhos de 2º Grau)
                $stmtReqs = $pdo->prepare("SELECT id FROM requisicoes WHERE id_usuario = :id_u");
                $stmtReqs->execute([':id_u' => $id_del]);
                $reqs = $stmtReqs->fetchAll(PDO::FETCH_COLUMN);

                if (count($reqs) > 0) {
                    // Implode array de IDS pra botar no SQL 'IN (1, 5, 8)' de uma vez sem loop pra performance.
                    $inQuery = implode(',', array_fill(0, count($reqs), '?'));
                    $stmtDelItens = $pdo->prepare("DELETE FROM itens_requisicao WHERE id_requisicao IN ($inQuery)");
                    $stmtDelItens->execute($reqs);

                    // Deleta as Requisições "Pai"
                    $stmtDelReqs = $pdo->prepare("DELETE FROM requisicoes WHERE id_usuario = ?");
                    $stmtDelReqs->execute([$id_del]);
                }

                // Passo B: Deleta as Entradas Logísticas dele (Se ele for um Almoxarife Despedido)
                $stmtDelEntradas = $pdo->prepare("DELETE FROM entradas WHERE id_usuario = ?");
                $stmtDelEntradas->execute([$id_del]);

                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
                $stmt->execute([':id' => $id_del]);

                $pdo->commit();
                $sucesso = "Usuário excluído com sucesso do sistema.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = "Falha ao Excluir o Usuário: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// SELECT GRID PRA TELA
// -------------------------------------------------------------
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC");
$usuarios = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Usuários</title>
    <!-- Template UI Premium Root Preto/Amarelo -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Outfit', 'Inter', sans-serif;
            color: #334155;
        }

        .navbar {
            background: #ffffff !important;
            border-bottom: 1px solid #e2e8f0;
        }

        .navbar-brand {
            color: #0f172a !important;
            font-weight: 800;
        }

        .glass-box {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: none;
            padding: 2rem;
        }

        .table-custom {
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table-custom tbody tr {
            background: white;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .table-custom td {
            border: none !important;
            padding: 1rem;
            vertical-align: middle;
        }

        .table-custom thead th {
            border: none;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }

        /* AJUSTES DE IMPRESSÃO */
        @media print {

            .no-print,
            nav,
            .btn,
            .badge-você {
                display: none !important;
            }

            body {
                background: white !important;
                padding: 0 !important;
            }

            .glass-box {
                box-shadow: none !important;
                border: 1px solid #eee !important;
                padding: 1rem !important;
            }

            .container {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
            }

            .table-custom tbody tr {
                box-shadow: none !important;
                border: 1px solid #eee !important;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">

    <?php include 'header.php'; ?>

    <div class="container flex-grow-1 mt-4 pb-5" style="max-width: 1100px;">

        <?php if ($erro): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4"><i
                    class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><i
                    class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Formulário Criação -->
            <div class="col-lg-4">
                <div class="glass-box">
                    <h5 class="fw-bold text-dark mb-4">Novo Usuário</h5>

                    <form method="POST" action="admin_usuarios.php">
                        <input type="hidden" name="acao" value="salvar_usuario">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome Completo</label>
                            <input type="text" name="nome" class="form-control" required
                                placeholder="Ex: João da Silva">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Login</label>
                            <input type="text" name="login" class="form-control" required placeholder="joao.silva">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Senha Inicial</label>
                            <input type="password" name="senha" class="form-control" required placeholder="**********">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Nível de Acesso</label>
                            <select name="nivel_acesso" class="form-select" required>
                                <option value="solicitante">Solicitante (Funcionário)</option>
                                <option value="almoxarife">Almoxarife (Estoque)</option>
                                <option value="admin">Administrador (Total)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold py-3 rounded-pill shadow-sm">
                            Criar Conta
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabela -->
            <div class="col-lg-8">
                <div class="glass-box">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold text-dark mb-0">Contas Ativas</h5>
                        <span class="badge bg-light text-primary rounded-pill px-3 py-2 border">Total:
                            <?= count($usuarios) ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Usuário</th>
                                    <th class="text-center">Login</th>
                                    <th class="text-center">Nível</th>
                                    <th class="text-end pe-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                                <b class="text-dark"><?= htmlspecialchars($u['nome']) ?></b>
                                            </div>
                                        </td>

                                        <td class="text-center text-muted small"><?= htmlspecialchars($u['login']) ?></td>

                                        <td class="text-center">
                                            <?php if ($u['nivel_acesso'] == 'admin'): ?>
                                                <span
                                                    class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">Admin</span>
                                            <?php elseif ($u['nivel_acesso'] == 'almoxarife'): ?>
                                                <span
                                                    class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Almoxarife</span>
                                            <?php else: ?>
                                                <span
                                                    class="badge bg-light text-muted border rounded-pill px-3">Solicitante</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end pe-3">
                                            <button class="btn btn-outline-primary btn-sm rounded-pill border-0 me-1"
                                                onclick="abrirModalEdicao(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>', '<?= $u['nivel_acesso'] ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                                <form method="POST" action="admin_usuarios.php"
                                                    onsubmit="return confirm('Excluir este usuário?');" style="display:inline;">
                                                    <input type="hidden" name="acao" value="excluir_usuario">
                                                    <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                                                    <button type="submit"
                                                        class="btn btn-outline-danger btn-sm rounded-pill border-0">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-primary text-white rounded-pill px-2 py-1 small"
                                                    style="font-size: 0.7rem;">Você</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de Edição -->
    <div class="modal fade" id="modalEdicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content overflow-hidden">
                <div class="modal-header bg-light">
                    <h5 class="fw-bold mb-0">Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="admin_usuarios.php">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar_usuario">
                        <input type="hidden" name="id_usuario" id="edit_id">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome Completo</label>
                            <input type="text" name="nome" id="edit_nome" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nível de Acesso</label>
                            <select name="nivel_acesso" id="edit_nivel" class="form-select" required>
                                <option value="solicitante">Solicitante (Funcionário)</option>
                                <option value="almoxarife">Almoxarife (Estoque)</option>
                                <option value="admin">Administrador (Total)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nova Senha (opcional)</label>
                            <input type="password" name="senha" class="form-control"
                                placeholder="Deixe em branco para manter a atual">
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill fw-bold"
                            data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Salvar
                            Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function abrirModalEdicao(id, nome, nivel) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_nivel').value = nivel;

            var myModal = new bootstrap.Modal(document.getElementById('modalEdicao'));
            myModal.show();
        }
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>