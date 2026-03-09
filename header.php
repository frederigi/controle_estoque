<?php
/**
 * COMPONENTE: header.php
 * Cabeçalho padronizado e centralizado.
 */
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top mb-4 py-3">
    <div class="container" style="max-width: 1100px;">
        <!-- Brand Esquerda -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <div class="bg-primary text-white p-2 rounded-3 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="bi bi-box-seam-fill fs-5"></i>
            </div>
            <span class="fw-bold tracking-tight">Estoque Fácil - DERSC</span>
        </a>

        <!-- Menu Centralizado -->
        <div class="collapse navbar-collapse justify-content-center" id="navBarMenuCentral">
            <ul class="navbar-nav gap-3">
                <li class="nav-item">
                    <a class="nav-link fw-bold text-dark px-3 py-2 rounded-pill hover-bg-light" href="dashboard.php">
                        <i class="bi bi-house-door me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold text-dark px-3 py-2 rounded-pill hover-bg-light" href="sobre.php">
                        <i class="bi bi-info-circle me-1"></i> Sobre
                    </a>
                </li>
            </ul>
        </div>

        <!-- Botão Mobile -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navBarMenuCentral">
            <i class="bi bi-list fs-2"></i>
        </button>

        <!-- Lado Direito (Perfil) -->
        <div class="collapse navbar-collapse justify-content-end" id="navBarMenuRight">
            <ul class="navbar-nav align-items-center gap-2">
                <!-- Dropdown do Usuário -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 text-dark fw-bold px-3 py-2 rounded-pill bg-light border-0" href="#" id="dropPerfil" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.8rem;">
                            <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 1)) ?>
                        </div>
                        <span>Olá, <?= explode(' ', $_SESSION['usuario_nome'])[0] ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 rounded-4 mt-2" aria-labelledby="dropPerfil">
                        <li>
                            <a class="dropdown-item rounded-3 py-2" href="#" data-bs-toggle="modal" data-bs-target="#modalTrocarSenha">
                                <i class="bi bi-key me-2"></i> Trocar Senha
                            </a>
                        </li>
                        <li><hr class="dropdown-divider opacity-50"></li>
                        <li>
                            <a class="dropdown-item rounded-3 py-2 text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Sair do Sistema
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Botão Sair Rápido (Opcional, mas o user pediu no print) -->
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm d-none d-lg-block">Sair</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Modal de Troca de Senha (Global) -->
<div class="modal fade" id="modalTrocarSenha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content overflow-hidden border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light py-3">
                <h5 class="fw-bold mb-0">Trocar Minha Senha</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTrocaSenha" onsubmit="event.preventDefault(); processarTrocaSenha();">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">Mantenha sua conta segura com uma senha forte.</p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nova Senha</label>
                        <input type="password" id="nova_senha" class="form-control rounded-3" required placeholder="Digite a nova senha">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Repetir Nova Senha</label>
                        <input type="password" id="repetir_senha" class="form-control rounded-3" required placeholder="Repita a nova senha">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm" id="btnSalvarSenha">Atualizar Senha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function processarTrocaSenha() {
    const s1 = document.getElementById('nova_senha').value;
    const s2 = document.getElementById('repetir_senha').value;
    const btn = document.getElementById('btnSalvarSenha');

    if (s1 !== s2) {
        alert('As senhas não coincidem!');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';

    try {
        const response = await fetch('alterar_senha.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `senha=${encodeURIComponent(s1)}`
        });
        const res = await response.json();
        
        if (res.success) {
            alert('Senha atualizada com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + res.error);
        }
    } catch (e) {
        alert('Falha na comunicação com o servidor.');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Atualizar Senha';
    }
}
</script>
