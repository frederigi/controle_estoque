
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel_acesso ENUM('solicitante', 'almoxarife', 'admin') NOT NULL
);

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    estoque_atual INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 0
);

-- Tabela para gerenciar entradas/lotes
CREATE TABLE IF NOT EXISTS entradas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    lote VARCHAR(50),
    validade DATE,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT NOT NULL, -- Quem registrou a entrada
    FOREIGN KEY (id_produto) REFERENCES produtos(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS requisicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pendente', 'Atendido', 'Negado') DEFAULT 'Pendente',
    justificativa TEXT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE IF NOT EXISTS itens_requisicao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_requisicao INT NOT NULL,
    id_produto INT NOT NULL,
    qtd_pedida INT NOT NULL,
    qtd_entregue INT DEFAULT 0,
    motivo_parcial TEXT, -- Novo campo para justificar entrega menor
    FOREIGN KEY (id_requisicao) REFERENCES requisicoes(id) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id)
);

-- Inserindo dados de teste (Senhas: 123456)
INSERT IGNORE INTO usuarios (nome, login, senha, nivel_acesso) VALUES
('Chefe Admin', 'admin', MD5('123456'), 'admin'),
('João Almoxarife', 'almoxarife', MD5('123456'), 'almoxarife'),
('Maria Funcionária', 'maria', MD5('123456'), 'solicitante');

INSERT IGNORE INTO produtos (nome, descricao, estoque_atual, estoque_minimo) VALUES
('Papel A4', 'Caixa com 500 folhas', 50, 10),
('Caneta Azul', 'Caixa com 50 unidades', 5, 20),
('Cartucho de Tinta', 'Cartucho HP Preto', 2, 5);
