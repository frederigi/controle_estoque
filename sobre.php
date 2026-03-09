<?php
/**
 * ARQUIVO: sobre.php
 * RESPONSABILIDADE: Apresentar informações sobre o sistema, projeto e desenvolvedor.
 */
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre o Sistema - Estoque Fácil - DERSC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Outfit', sans-serif;
            color: #334155;
        }

        .glass-box {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: none;
            padding: 3rem;
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="container pb-5" style="max-width: 1100px;">
        <div class="glass-box mb-4">
            <!-- CABEÇALHO -->
            <div class="text-center mb-5">
                <div class="bg-primary text-white p-3 rounded-4 d-inline-flex align-items-center justify-content-center mb-4 shadow-sm"
                    style="width: 80px; height: 80px;">
                    <i class="bi bi-box-seam-fill fs-1"></i>
                </div>
                <h2 class="fw-bold text-dark mb-2">Estoque Fácil - DERSC</h2>
                <p class="text-muted">Sistema Inteligente para Gestão de Almoxarifado</p>
            </div>

            <div class="row g-5">
                <!-- COLUNA ESQUERDA: IDENTIFICAÇÃO E DESCRIÇÃO -->
                <div class="col-lg-7">
                    <section class="mb-5">
                        <h5 class="fw-bold text-primary mb-3"><i class="bi bi-mortarboard-fill me-2"></i>Identificação
                            do Projeto</h5>
                        <div class="bg-light p-4 rounded-4 border-start border-primary border-4 shadow-sm">
                            <p class="mb-1"><strong>Projeto Integrador em Computação I</strong></p>
                            <p class="mb-1 text-muted small">Disciplina: DRP09 - Turma 001 | <strong>Grupo: 16</strong>
                            </p>
                            <p class="mb-0 text-muted small">Instituição: <strong>Universidade Virtual do Estado de São
                                    Paulo (UNIVESP)</strong></p>
                        </div>
                    </section>

                    <section class="mb-5">
                        <h5 class="fw-bold text-dark mb-3">Descrição do Projeto</h5>
                        <p class="text-muted leading-relaxed">
                            Este sistema foi desenvolvido como parte do currículo acadêmico da UNIVESP, com o objetivo
                            de aplicar conhecimentos de computação na resolução de problemas reais em setores públicos
                            ou comunitários.
                        </p>
                        <p class="text-muted leading-relaxed">
                            O projeto consiste em uma plataforma de Gestão de Almoxarifado criada especificamente para o
                            <strong>Departamento de Emprego e Renda da Prefeitura de São Carlos/SP</strong>. O sistema
                            foi idealizado para modernizar o controle de materiais, substituindo métodos manuais por um
                            fluxo digital mais seguro e organizado.
                        </p>
                    </section>

                    <section class="mb-5">
                        <h5 class="fw-bold text-dark mb-3">Por que foi criado?</h5>
                        <p class="text-muted small leading-relaxed">
                            A criação deste software visa trazer transparência e eficiência para a administração pública
                            municipal. Ao centralizar as requisições, o Departamento de Emprego e Renda passa a ter um
                            histórico fiel do consumo de cada setor, facilitando o planejamento de novas compras,
                            reduzindo o desperdício e garantindo que os recursos da Prefeitura de São Carlos sejam
                            geridos de forma mais inteligente.
                        </p>
                    </section>
                </div>

                <!-- COLUNA DIREITA: FUNCIONALIDADES E TIME -->
                <div class="col-lg-5">
                    <section class="mb-5">
                        <h5 class="fw-bold text-dark mb-4">O que o sistema faz:</h5>

                        <div class="d-flex mb-4">
                            <div class="feature-icon bg-blue-light text-primary bg-opacity-10 min-w-50px"
                                style="background: rgba(13, 110, 253, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="bi bi-cart-check fs-5"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1 small">Gerenciamento de Pedidos</h6>
                                <p class="text-muted mb-0" style="font-size: 0.85rem;">Permite que os funcionários do
                                    departamento solicitem materiais de consumo de forma digital.</p>
                            </div>
                        </div>

                        <div class="d-flex mb-4">
                            <div class="feature-icon bg-success bg-opacity-10 text-success min-w-50px"
                                style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="bi bi-person-check fs-5"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1 small">Controle do Almoxarife</h6>
                                <p class="text-muted mb-0" style="font-size: 0.85rem;">O responsável pode autorizar,
                                    negar ou ajustar quantidades conforme a disponibilidade real.</p>
                            </div>
                        </div>

                        <div class="d-flex mb-4">
                            <div class="feature-icon bg-warning bg-opacity-10 text-warning min-w-50px"
                                style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="bi bi-graph-up-arrow fs-5"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-bold mb-1 small">Monitoramento de Estoque</h6>
                                <p class="text-muted mb-0" style="font-size: 0.85rem;">Inventário atualizado
                                    automaticamente com alertas visuais para níveis críticos.</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="bg-light p-4 rounded-4 border">
                            <h6 class="fw-bold text-primary mb-3 small text-uppercase"><i
                                    class="bi bi-terminal me-2"></i>Desenvolvedores (Grupo 16)</h6>
                            <ul class="list-unstyled mb-0" style="font-size: 0.9rem;">
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Flávio
                                    Fernandes dos Santos</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Andersom
                                    L. Gonçalez Frederigi</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Jessika
                                    Moretti</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Regiane de
                                    Oliveira Gaspar</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Jackson
                                    Castro Munhoz</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Beatriz
                                    Zerbinati Custódio</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Davi Balbino
                                    Siqueira Lima</li>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Naelton Alves
                                    da Silva</li>
                            </ul>
                            <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                                <span class="small text-muted">Versão 1.0</span>
                                <span class="small text-muted">UNIVESP</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>

</html>