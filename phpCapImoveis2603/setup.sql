CREATE DATABASE IF NOT EXISTS sistema_captacao;
USE sistema_captacao;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS proprietarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    cpf VARCHAR(14),
    endereco VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS imoveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proprietario_id INT,
    titulo VARCHAR(150),
    endereco VARCHAR(200),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    cep VARCHAR(10),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    preco DECIMAL(12,2),
    quartos INT,
    banheiros INT,
    area DECIMAL(10,2),
    tipo ENUM('casa','apartamento','terreno','comercial','outro'),
    descricao TEXT,
    status ENUM('captado','em_negociacao','vendido') DEFAULT 'captado',
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proprietario_id) REFERENCES proprietarios(id)
);

CREATE TABLE IF NOT EXISTS fotos_imoveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imovel_id INT,
    caminho VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS visitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imovel_id INT,
    data_visita DATETIME,
    visitante VARCHAR(100),
    observacoes TEXT,
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id)
);

CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imovel_id INT,
    tipo VARCHAR(50),
    valor DECIMAL(10,2),
    data_despesa DATE,
    descricao TEXT,
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id)
);

CREATE TABLE IF NOT EXISTS historico_imoveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imovel_id INT,
    data_historico DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT,
    acao VARCHAR(50),
    descricao TEXT,
    FOREIGN KEY (imovel_id) REFERENCES imoveis(id)
);

CREATE TABLE IF NOT EXISTS log_acessos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    data_acesso DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);