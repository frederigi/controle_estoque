<?php
/**
 * ARQUIVO PRINCIPAL DO SISTEMA: index.php
 * Criado para o Controle de Estoque
 * 
 * OBJETIVO DESTE ARQUIVO:
 * Exibir a tela de login inicial do sistema, validar as informações 
 * digitadas pelo usuário (usuário e senha) no banco de dados e, caso 
 * estejam corretas, criar a SESSÃO (session_start) para mantê-lo logado 
 * e redirecioná-lo para a tela principal (dashboard.php).
 */

// 1. Inicia o controle de sessão no PHP. Permite guardar informações 
// como 'usuario_nome' ou 'usuario_id' entre várias páginas sem precisar logar de novo.
session_start();

// 2. Inclui o arquivo de conexão com o banco de dados. 
// O 'require_once' garante que o script PHP vai parar com um erro fatal 
// caso o 'conexao.php' não exista (evitando que o login tente rodar sem banco).
require_once 'conexao.php';

// Variável que guarda possíveis erros de digitação de senha para mostrar na tela (em vermelho)
$erro = '';

// 3. Verifica se o usuário clicou no botão "Entrar" (enviou dados por POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Pega os dados que vieram do formulário HTML lá embaixo e limpa os espaços vazios
    // a função trim() previne que o usuário coloque " admin " e o sistema não reconheça
    $login = trim($_POST['login']);
    $senha = trim($_POST['senha']);

    // Verifica se os campos não estão em branco
    if (!empty($login) && !empty($senha)) {

        // 4. CRIPTOGRAFIA - Segurança Básica
        // md5() converte a senha "123456" em algo como "e10adc3949ba59abbe56e057f20f883e"
        // NOTA DE ESTUDO: O MD5 é ótimo para aprendizado rápido, mas em sistemas reais 
        // ou corporativos, prefiram a função nativa do PHP: password_hash() pelo seu nível de segurança.
        $senha_hash = md5($senha);

        // 5. BANCO DE DADOS: PREVENÇÃO DE SQL INJECTION
        // Usamos PDO (PHP Data Objects) e preparamos a query (comando prepare).
        // Evitamos passar variáveis diretas no texto do SELECT (Ex: SELECT * FROM WHERE x = '$login')
        // Substituímos por ':login' (binds). Isso impede invasões no banco.
        $stmt = $pdo->prepare("SELECT id, nome, nivel_acesso FROM usuarios WHERE login = :login AND senha = :senha LIMIT 1");
        $stmt->bindParam(':login', $login);
        $stmt->bindParam(':senha', $senha_hash);

        // Executamos a consulta agora que temos segurança
        $stmt->execute();

        // 6. Buscamos o resultado da consulta e jogamos na variável '$usuario'
        $usuario = $stmt->fetch();

        // Se $usuario contiver dados, a senha está certa
        if ($usuario) {
            // Guardamos os dados importantes do banco direto na sessão da pessoa logada
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['nivel_acesso'] = $usuario['nivel_acesso']; // Importante: diz se ele é admin, solicitante ou almoxarife

            // 7. Redireciona para dentro do sistema
            header("Location: dashboard.php");
            exit; // Sempre usar exit; depois do header, para impedir que o resto do código abaixo rode
        } else {
            // A query retornou vazia, senha ou login errado
            $erro = "Credenciais incorretas. Tente novamente!";
        }
    } else {
        $erro = "Faltam informações! Preencha usuário e senha.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">

    <!-- TAG VIEWPORT PARA CELULAR: Garante que a tela vai se adaptar nas telas de smartphones e tablets e não ficará minúscula. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Controle de Estoque</title>

    <!-- 
      BOOTSTRAP 5
      Estamos usando Bootstrap via CDN. Ele nos dá toda a estrutura base de botões 
      redondos, margens, cores sem a necessidade de escrever dezenas de linhas de CSS. 
    -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- BOOTSTRAP ICONS: Para colocar pequenos ícones modernos nos botões -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 3rem;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            border: none;
        }

        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .input-group-text {
            border-radius: 12px 0 0 12px !important;
            border: 1px solid #e2e8f0;
            border-right: none;
            background: #f8fafc;
        }

        .form-control:focus {
            background: white;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>

<body>

    <!-- ENVELOPE DO FORMULÁRIO -->
    <div class="login-card mx-3">
        <div class="text-center mb-5">
            <div class="bg-primary text-white p-3 rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm"
                style="width: 60px; height: 60px;">
                <i class="bi bi-box-seam-fill fs-2"></i>
            </div>
            <h3 class="fw-extrabold text-dark mb-1" style="letter-spacing: -1px;">Estoque Fácil - DERSC</h3>
            <p class="text-muted small fw-bold text-uppercase opacity-50">Departamento de Emprego e Renda</p>
        </div>

        <!-- BLOCO DE ERRO -->
        <!-- O código PHP verifica se aquela variável $erro que definimos lá no topo possui alguma String de texto. Se tiver, exibe essa caixa vermelha. -->
        <?php if ($erro): ?>
            <!-- Repare no flexbox (d-flex e align-items-center) em conjunto com texto pequeno (small) e ícone de alerta -->
            <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm py-2 px-3 small fw-bold">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" style="font-size: 1.2rem;"></i>
                <div><?= htmlspecialchars($erro) ?></div>
            </div>
        <?php endif; ?>

        <!-- 
          FORMULÁRIO HTML DE ENVIO:
          - method="POST": envia os dados invisivelmente no pacote de rede do navegador, de foma bem mais segura. NINGUÉM quer senhas voando livremente por GET via URL.
          - action="index.php": envia esses dados para este mesmo arquivo e recarrega.
        -->
        <form method="POST" action="index.php">
            <div class="mb-3">
                <label for="login" class="form-label text-muted fw-bold small">Nome de Usuário</label>
                <!-- O "required" previne que o usuário clique em "Entrar" com campo em branco antes de chegar no backend -->
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="login" name="login" required
                        placeholder="Digite seu login cadastrado">
                </div>
            </div>

            <div class="mb-4">
                <label for="senha" class="form-label text-muted fw-bold small">Senha Pessoal</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="senha" name="senha" required
                        placeholder="Sua senha numérica">
                </div>
            </div>
            <br>
            <!-- btn-primary (cor principal botão azul), w-100 (tamanho ocupa 100% da tela pai), py-2 (padding y=cima e y=baixo) -->
            <button type="submit"
                class="btn btn-primary w-100 fw-bold py-3 rounded-pill shadow-sm mt-2 translate-middle-y">
                Acessar Sistema <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </form>

        <!-- CAIXA INFORMATIVA PARA AJUDA NA FACULDADE (Teste Acadêmico) -->
        <!-- mt-4 cria distância no topo. text-center serve para alinhar parágrafos. border e bg-light garantem estilo secundário -->


    </div>
</body>

</html>