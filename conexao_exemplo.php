<?php
// Configurações do Banco de Dados
$host = 'localhost';
$dbname = 'nome_do_banco';
$user = 'usuario_banco'; // Ajuste conforme seu servidor local (XAMPP/WAMP geralmente usam root)
$pass = 'senha_banco'; // Senha padrão geralmente é vazia

try {
    // String de conexão DSN (Data Source Name)
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Conexão via PDO proteje contra SQL Injection com prepares statements
    $pdo = new PDO($dsn, $user, $pass);
    
    // Configura o PDO para lançar exceções em caso de erros de banco
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configura o fetch padrão para retornar arrays associativos
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Interrompe execução se haver problema de conexão
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>
