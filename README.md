# 📦 Estoque Fácil - DERSC 
**Sistema Inteligente para Gestão de Almoxarifado**

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap_5-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white)

Este sistema foi desenvolvido como parte do currículo acadêmico da **Universidade Virtual do Estado de São Paulo (UNIVESP)**, com o objetivo de aplicar conhecimentos de computação na resolução de problemas reais em setores públicos ou comunitários (Projeto Integrador em Computação I).

O projeto consiste em uma plataforma de Gestão de Almoxarifado criada especificamente para o **Departamento de Emprego e Renda da Prefeitura de São Carlos/SP (DERSC)**. O sistema foi idealizado para modernizar o controle de materiais, substituindo métodos manuais por um fluxo digital mais seguro, transparente e organizado.

---

## 🎯 Objetivos do Sistema

A criação deste software visa trazer **transparência e eficiência** para a administração pública municipal. Ao centralizar as requisições, o Departamento de Emprego e Renda passa a ter:
- Histórico fiel do consumo de cada setor.
- Facilidade no planejamento de novas compras.
- Redução expressiva de desperdícios.
- Garantia de que os recursos públicos sejam geridos de forma mais inteligente.

---

## 🚀 Funcionalidades Principais

O sistema é dividido em perfis de acesso, cada um com suas responsabilidades e permissões:

### 1. 🛒 Solicitante (Funcionário)
- Solicitação digital de materiais de consumo (Requisições).
- Acompanhamento do status de seus pedidos (Pendente, Atendido, Negado).
- Visualização do histórico de requisições.

### 2. 📋 Almoxarife (Gerente de Estoque)
- **Controle de Pedidos:** Autorização, negação ou ajuste de quantidades conforme a disponibilidade real em estoque.
- **Gestão de Inventário:** Processamento de requisições, o qual debita automaticamente as quantidades do estoque do almoxarifado.
- **Entrada de Materiais:** Registro de novos lotes de produtos, com data de validade e quantidades.

### 3. ⚙️ Administrador (Chefe)
Todas as permissões do Almoxarife, mais:
- **Gestão de Usuários:** Cadastro, edição e remoção de usuários do sistema, além de definição de seus níveis de acesso.
- **Gestão de Produtos:** Cadastro, edição e deleção de produtos do catálogo de materiais.
- **Relatórios:** Monitoramento de estoque com alertas visuais para níveis críticos (estoque mínimo) e relatórios de consumo.

---

## 💻 Tecnologias Utilizadas

- **Back-end:** PHP (utilizando PDO para comunicação segura com o banco de dados e prevenção a SQL Injection).
- **Banco de Dados:** MySQL (Tabelas relacionais com chaves estrangeiras).
- **Front-end:** HTML5, CSS3, e JavaScript.
- **Estilo e UI:** Bootstrap 5.3.2, Bootstrap Icons e Google Fonts (Família 'Outfit').

---

## 🛠️ Como Instalar e Executar Localmente

### Pré-requisitos
- Servidor local (ex: **XAMPP**, **WAMP**, ou **Laragon**).
- PHP versão 7.4 ou superior.
- MySQL ou MariaDB.

### Passo a passo

1. **Clone ou mova os arquivos:**
   Coloque a pasta do projeto (`dersc`) dentro do diretório raiz do seu servidor local (ex: `C:\xampp\htdocs\dersc` no XAMPP ou `C:\wamp64\www\dersc` no WAMP).

2. **Configuração do Banco de Dados:**
   - Abra o **phpMyAdmin** (geralmente em `http://localhost/phpmyadmin`).
   - Crie um banco de dados chamado `controle_estoque` (ou o nome que preferir, atualizando no passo 3).
   - Importe o arquivo `database.sql` incluído no projeto para dentro deste banco criado. Ele criará as tabelas e inserirá os dados padrão de teste.

3. **Configuração da Conexão:**
   - Abra o arquivo `conexao.php` na raiz do projeto.
   - Altere as credenciais (usuário e senha) para corresponder ao seu servidor local.
   
   ```php
   // Exemplo para XAMPP local padrão:
   $host = 'localhost';
   $dbname = 'controle_estoque'; // ou o nome do BD que você criou
   $user = 'root'; 
   $pass = ''; // Senha padrão do XAMPP é vazia
   ```

4. **Acesso:**
   Acesse no seu navegador: `http://localhost/dersc`

---

## 🔑 Usuários para Teste

Ao importar o arquivo `database.sql`, os seguintes perfis já estarão cadastrados (A senha padrão para todos é `123456`):

| Perfil de Acesso | Login | Senha | Nome |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | `123456` | Chefe Admin |
| **Almoxarife** | `almoxarife` | `123456` | João Almoxarife |
| **Solicitante** | `maria` | `123456` | Maria Funcionária |

> **Nota:** É altamente recomendável alterar as senhas padrão em um ambiente de produção real.

---

## 🗄️ Estrutura do Banco de Dados

O banco de dados é gerido por cinco tabelas principais:
- `usuarios`: Armazena dados de login e nível de acesso.
- `produtos`: Catálogo geral de materiais com controle de estoque atual e mínimo.
- `entradas`: Histórico de entrada/lotes de materiais adicionados ao estoque.
- `requisicoes`: Pedidos realizados pelos solicitantes, vinculados a usuários e status de atendimento.
- `itens_requisicao`: Tabela intermediária associativa que lista os produtos contidos dentro de cada requisição.

---

## 👨‍💻 Equipe de Desenvolvimento

Projeto desenvolvido pelo **Grupo 16 - DRP09 (Turma 001)** - UNIVESP:

- Flávio Fernandes dos Santos
- Andersom Leandro Gonçalez Frederigi
- Jessika Moretti
- Regiane de Oliveira Gaspar
- Jackson Castro Munhoz
- Beatriz Zerbinati Custódio
---
*Versão 1.0 - Sistema desenvolvido para fins acadêmicos e doação à Gestão Pública.*
