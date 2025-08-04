-- SQL para criação do banco de dados e tabelas para o sistema Life Church

CREATE DATABASE IF NOT EXISTS lifechurchfinanc_lifechurch_db;
USE lifechurchfinanc_lifechurch_db;

-- Tabela de Igrejas
CREATE TABLE IF NOT EXISTS churches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    balance DECIMAL(10, 2) DEFAULT 0.00
);

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    city VARCHAR(100),
    role ENUM("membro", "lider", "pastor", "pastor_principal") NOT NULL DEFAULT "membro",
    church_id INT,
    FOREIGN KEY (church_id) REFERENCES churches(id) ON DELETE SET NULL
);

-- Tabela de Membros (pode ser redundante com users, mas para marcar presença é útil)
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    church_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (church_id) REFERENCES churches(id) ON DELETE CASCADE
);

-- Tabela de Categorias de Despesas
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    type ENUM("entrada", "saida") NOT NULL
);

-- Tabela de Finanças (entradas e saídas)
CREATE TABLE IF NOT EXISTS finances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    church_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM("entrada", "saida") NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    category_id INT,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (church_id) REFERENCES churches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tabela de Presenças
CREATE TABLE IF NOT EXISTS attendances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    church_id INT NOT NULL,
    cult_date DATE NOT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (church_id) REFERENCES churches(id) ON DELETE CASCADE
);

-- Inserir algumas categorias padrão
INSERT IGNORE INTO categories (name, type) VALUES
("Dízimos", "entrada"),
("Ofertas", "entrada"),
("Aluguel", "saida"),
("Contas de Consumo", "saida"),
("Salários", "saida"),
("Manutenção", "saida");

-- Inserir um Pastor Principal de exemplo
INSERT IGNORE INTO users (name, email, password, phone, city, role) VALUES
("Pastor João", "pastor.joao@example.com", "" /* Senha será hashed no PHP */, "11987654321", "São Paulo", "pastor_principal");


